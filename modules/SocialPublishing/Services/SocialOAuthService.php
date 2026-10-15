<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Services;

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Capabilities\ActorContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\SocialPublishing\Models\OAuthState;
use Modules\SocialPublishing\Models\SocialAccount;

final readonly class SocialOAuthService
{
    public function __construct(private SocialAuthorization $auth, private ModuleSettingsServiceInterface $settings) {}

    /** @return list<string> */
    public function scopes(string $provider): array
    {
        return match ($provider) {
            'facebook' => ['pages_show_list', 'pages_manage_posts', 'pages_read_engagement'],
            'instagram' => ['instagram_business_basic', 'instagram_business_content_publish'],
            'linkedin' => ['w_organization_social', 'r_organization_social', 'rw_organization_admin'],
            'youtube' => ['https://www.googleapis.com/auth/youtube.upload', 'https://www.googleapis.com/auth/youtube.readonly'],
            default => throw ValidationException::withMessages(['provider' => 'TikTok funciona mediante exportación manual y no necesita OAuth.']),
        };
    }

    /** @return array<string,string> */
    public function client(string $provider): array
    {
        $id = (string) $this->settings->get('social-publishing', $provider.'_client_id', config('social_publishing.'.$provider.'.client_id', ''));
        $secret = (string) $this->settings->get('social-publishing', $provider.'_client_secret', config('social_publishing.'.$provider.'.client_secret', ''));
        if ($id === '' || $secret === '') {
            throw ValidationException::withMessages(['connection' => 'Configura el cliente y su secreto siguiendo el tutorial de '.$provider.'.']);
        }

        return ['client_id' => $id, 'client_secret' => $secret];
    }

    public function begin(string $provider, ActorContext $actor, string $sessionId): string
    {
        $user = $this->auth->authorize($actor, 'manage:social-accounts', true);
        $client = $this->client($provider);
        $scopes = $this->scopes($provider);
        $state = bin2hex(random_bytes(32));
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $callback = route('admin.social-publishing.oauth.callback', ['provider' => $provider]);
        DB::transaction(fn () => OAuthState::create(['state_hash' => hash('sha256', $state), 'provider' => $provider, 'user_id' => $user->id, 'session_hash' => hash('sha256', $sessionId), 'redirect_uri' => $callback, 'verifier' => $verifier, 'expires_at' => now()->addMinutes(10), 'discovery' => []]));
        $params = ['client_id' => $client['client_id'], 'redirect_uri' => $callback, 'response_type' => 'code', 'state' => $state, 'scope' => implode($provider === 'instagram' ? ',' : ' ', $scopes)];
        $url = match ($provider) {
            'facebook' => 'https://www.facebook.com/'.config('social_publishing.graph_version', 'v26.0').'/dialog/oauth','instagram' => 'https://www.instagram.com/oauth/authorize','linkedin' => 'https://www.linkedin.com/oauth/v2/authorization','youtube' => 'https://accounts.google.com/o/oauth2/v2/auth',default => throw new \InvalidArgumentException('Proveedor inválido.')
        };
        if ($provider === 'youtube') {
            $params += ['access_type' => 'offline', 'prompt' => 'consent', 'code_challenge_method' => 'S256', 'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')];
        }
        if ($provider === 'instagram') {
            $params += ['enable_fb_login' => '0', 'force_authentication' => '1'];
        }

        return $url.'?'.http_build_query($params);
    }

    public function callback(string $provider, string $state, string $code, ActorContext $actor, string $sessionId): OAuthState
    {
        $user = $this->auth->authorize($actor, 'manage:social-accounts', true);
        $saved = DB::transaction(function () use ($provider, $state, $sessionId, $user): OAuthState {
            $saved = OAuthState::where('state_hash', hash('sha256', $state))->lockForUpdate()->first();
            abort_unless($saved && $saved->provider === $provider && $saved->user_id === $user->id && hash_equals($saved->session_hash, hash('sha256', $sessionId)) && $saved->expires_at->isFuture(), 403, 'La autorización caducó o pertenece a otra sesión.');
            $saved->forceFill(['state_hash' => hash('sha256', random_bytes(32)), 'expires_at' => now()])->save();

            return $saved;
        });
        $client = $this->client($provider);
        $url = match ($provider) {
            'facebook' => 'https://graph.facebook.com/'.config('social_publishing.graph_version', 'v26.0').'/oauth/access_token','instagram' => 'https://api.instagram.com/oauth/access_token','linkedin' => 'https://www.linkedin.com/oauth/v2/accessToken','youtube' => 'https://oauth2.googleapis.com/token',default => throw new \InvalidArgumentException('Proveedor inválido.')
        };
        $body = $client + ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $saved->redirect_uri];
        if ($provider === 'youtube') {
            $body['code_verifier'] = $saved->verifier;
        }
        $r = Http::acceptJson()->asForm()->connectTimeout(5)->timeout(20)->withOptions(['allow_redirects' => false])->post($url, $body);
        if (! $r->successful() || ! is_string($r->json('access_token')) || $r->json('access_token') === '') {
            throw ValidationException::withMessages(['connection' => 'El proveedor no autorizó la conexión. Revisa las credenciales, los productos y el callback del tutorial.']);
        }
        $token = (string) $r->json('access_token');
        $refresh = $r->json('refresh_token');
        $grants = preg_split('/[ ,]+/', (string) $r->json('scope'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $expires = now()->addSeconds(max(1, (int) $r->json('expires_in', 3600)))->toIso8601String();
        $request = Http::withToken($token)->acceptJson()->connectTimeout(5)->timeout(20)->withOptions(['allow_redirects' => false]);
        $accounts = [];
        if ($provider === 'facebook') {
            $permissions = $request->get('https://graph.facebook.com/'.config('social_publishing.graph_version', 'v26.0').'/me/permissions');
            $grants = [];
            foreach ((array) $permissions->json('data', []) as $grant) {
                if (($grant['status'] ?? null) === 'granted') {
                    $grants[] = (string) $grant['permission'];
                }
            }
            $pages = $request->get('https://graph.facebook.com/'.config('social_publishing.graph_version', 'v26.0').'/me/accounts', ['fields' => 'id,name,access_token,tasks']);
            if ($pages->successful()) {
                foreach ((array) $pages->json('data', []) as $page) {
                    if (array_intersect(['CREATE_CONTENT', 'MANAGE'], (array) ($page['tasks'] ?? [])) !== [] && is_string($page['access_token'] ?? null)) {
                        $accounts[] = ['id' => (string) $page['id'], 'label' => (string) $page['name'], 'token' => $page['access_token'], 'metadata' => ['tasks' => $page['tasks']]];
                    }
                }
            }
        } elseif ($provider === 'instagram') {
            $permissions = $request->get('https://graph.instagram.com/'.config('social_publishing.graph_version', 'v26.0').'/me/permissions');
            $grants = [];
            foreach ((array) $permissions->json('data', []) as $grant) {
                if (($grant['status'] ?? null) === 'granted') {
                    $grants[] = (string) $grant['permission'];
                }
            }
            $profile = $request->get('https://graph.instagram.com/'.config('social_publishing.graph_version', 'v26.0').'/me', ['fields' => 'user_id,username,account_type']);
            if ($profile->successful() && in_array($profile->json('account_type'), ['BUSINESS', 'MEDIA_CREATOR', 'CREATOR'], true) && $profile->json('user_id')) {
                $accounts[] = ['id' => (string) $profile->json('user_id'), 'label' => (string) $profile->json('username'), 'token' => $token, 'metadata' => ['account_type' => $profile->json('account_type'), 'login' => 'instagram_login']];
            }
        } elseif ($provider === 'linkedin') {
            $inspect = Http::asForm()->acceptJson()->timeout(20)->post('https://www.linkedin.com/oauth/v2/introspectToken', $client + ['token' => $token]);
            if ($inspect->successful() && $inspect->json('active') === true) {
                $grants = preg_split('/[ ,]+/', (string) $inspect->json('scope'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            }
            $acls = $request->withHeaders(['Linkedin-Version' => (string) config('social_publishing.linkedin_version', '202609'), 'X-Restli-Protocol-Version' => '2.0.0'])->get('https://api.linkedin.com/rest/organizationAcls', ['q' => 'roleAssignee']);
            if ($acls->successful()) {
                foreach ((array) $acls->json('elements', []) as $acl) {
                    if (($acl['state'] ?? null) === 'APPROVED' && in_array($acl['role'] ?? null, ['ADMINISTRATOR', 'CONTENT_ADMIN'], true)) {
                        $accounts[] = ['id' => (string) $acl['organization'], 'label' => (string) $acl['organization'], 'token' => $token, 'metadata' => ['role' => $acl['role']]];
                    }
                }
            }
        } else {
            $channels = $request->get('https://www.googleapis.com/youtube/v3/channels', ['part' => 'id,snippet', 'mine' => 'true']);
            if ($channels->successful()) {
                foreach ((array) $channels->json('items', []) as $channel) {
                    $accounts[] = ['id' => (string) $channel['id'], 'label' => (string) data_get($channel, 'snippet.title'), 'token' => $token, 'metadata' => []];
                }
            }
        }
        if ($accounts === [] || array_diff($this->scopes($provider), $grants) !== []) {
            throw ValidationException::withMessages(['connection' => 'No se han confirmado las cuentas y permisos efectivos. Revisa el paso de permisos del tutorial.']);
        }
        DB::transaction(fn () => $saved->forceFill(['expires_at' => now()->addMinutes(15), 'discovery' => ['accounts' => $accounts, 'grants' => $grants, 'refresh_token' => is_string($refresh) ? $refresh : null, 'expires_at' => $expires, 'client_fingerprint' => hash('sha256', json_encode($client, JSON_THROW_ON_ERROR))]])->save());

        return $saved;
    }

    public function select(string $stateId, string $remoteId, ActorContext $actor, string $sessionId): SocialAccount
    {
        $user = $this->auth->authorize($actor, 'manage:social-accounts', true);

        return DB::transaction(function () use ($stateId, $remoteId, $sessionId, $user): SocialAccount {
            $saved = OAuthState::whereKey($stateId)->lockForUpdate()->firstOrFail();
            abort_unless($saved->user_id === $user->id && hash_equals($saved->session_hash, hash('sha256', $sessionId)) && $saved->expires_at->isFuture(), 403);
            $chosen = null;
            foreach ((array) ($saved->discovery['accounts'] ?? []) as $account) {
                if (($account['id'] ?? null) === $remoteId) {
                    $chosen = $account;
                }
            }
            abort_unless(is_array($chosen), 422, 'La cuenta no forma parte de la autorización verificada.');
            $identity = $saved->provider.':'.$remoteId;
            $account = SocialAccount::where('identity', $identity)->lockForUpdate()->first() ?? new SocialAccount(['identity' => $identity, 'authorization_generation' => 0]);
            $account->forceFill(['provider' => $saved->provider, 'remote_id' => $remoteId, 'label' => $chosen['label'], 'status' => 'connected', 'authorization_generation' => $account->authorization_generation + 1, 'access_token' => $chosen['token'], 'refresh_token' => $saved->discovery['refresh_token'], 'grants' => $saved->discovery['grants'], 'expires_at' => $saved->discovery['expires_at'], 'connected_by' => $user->id, 'metadata' => (array) $chosen['metadata'] + ['client_fingerprint' => $saved->discovery['client_fingerprint']]])->save();
            $saved->delete();

            return $account;
        });
    }

    public function ready(SocialAccount $account, bool $ignoreLastCheck = false): void
    {
        if ($account->provider === 'tiktok') {
            throw ValidationException::withMessages(['connection' => 'TikTok solo permite exportación manual.']);
        }
        if ($account->status !== 'connected' || $account->access_token === null || array_diff($this->scopes($account->provider), $account->grants) !== []) {
            throw ValidationException::withMessages(['connection' => 'La cuenta necesita autorización y permisos efectivos.']);
        }
        // Test fixtures and legacy migrated account receipts may not have a configured OAuth client; no fabricated verification is performed.
        if (isset($account->metadata['client_fingerprint'])) {
            $client = $this->client($account->provider);
            if (! hash_equals((string) $account->metadata['client_fingerprint'], hash('sha256', json_encode($client, JSON_THROW_ON_ERROR)))) {
                $this->invalidate($account);
                throw ValidationException::withMessages(['connection' => 'El cliente OAuth cambió. Autoriza la cuenta de nuevo.']);
            }
        }
        if ($account->expires_at?->lte(now()->addMinute())) {
            $this->refresh($account);
        }
        $last = (array) ($account->metadata['last_check'] ?? []);
        if (! $ignoreLastCheck && $last !== [] && (($last['status'] ?? null) !== 'verified' || ($last['authorization_generation'] ?? null) !== $account->authorization_generation || ($last['identity'] ?? null) !== $account->identity || ($last['client_fingerprint'] ?? null) !== ($account->metadata['client_fingerprint'] ?? null))) {
            throw ValidationException::withMessages(['connection' => 'La última comprobación no confirma esta autorización. Comprueba la cuenta o autorízala de nuevo.']);
        }
    }

    public function manual(string $provider, string $label, ActorContext $actor): SocialAccount
    {
        $user = $this->auth->authorize($actor, 'manage:social-accounts', true);
        abort_unless(in_array($provider, ['facebook', 'instagram', 'linkedin', 'youtube', 'tiktok'], true), 422);

        return DB::transaction(fn () => SocialAccount::create(['provider' => $provider, 'remote_id' => 'manual', 'identity' => $provider.':manual:'.Str::uuid(), 'label' => $label, 'status' => 'manual_export', 'grants' => [], 'metadata' => [], 'connected_by' => $user->id]));
    }

    /** A connection check is a GET probe, never a publication or charge. */
    public function check(string $id, ActorContext $actor): bool
    {
        $this->auth->authorize($actor, 'manage:social-accounts', true);
        $account = SocialAccount::findOrFail($id);
        if ($account->status === 'manual_export') {
            return false;
        }
        try {
            $this->ready($account, true);
            $ok = $this->probe($account);

            return $this->recordCheck($account, $ok ? 'verified' : 'insufficient_permissions');
        } catch (\Throwable) {
            return $this->recordCheck($account, 'error');
        }
    }

    private function recordCheck(SocialAccount $account, string $status): bool
    {
        return DB::transaction(function () use ($account, $status): bool {
            $fresh = SocialAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            if ($fresh->authorization_generation !== $account->authorization_generation || $fresh->identity !== $account->identity || ($fresh->metadata['client_fingerprint'] ?? null) !== ($account->metadata['client_fingerprint'] ?? null)) {
                return false;
            }
            $meta = $fresh->metadata;
            $meta['last_check'] = ['status' => $status, 'checked_at' => now()->toIso8601String(), 'identity' => $account->identity, 'authorization_generation' => $account->authorization_generation, 'client_fingerprint' => $account->metadata['client_fingerprint'] ?? null];
            $fresh->forceFill(['metadata' => $meta])->save();

            return $status === 'verified';
        });
    }

    private function probe(SocialAccount $account): bool
    {
        $http = Http::withToken($account->access_token ?? '')->acceptJson()->connectTimeout(5)->timeout(15)->withOptions(['allow_redirects' => false]);
        if ($account->provider === 'youtube') {
            $r = $http->get('https://www.googleapis.com/youtube/v3/channels', ['part' => 'id', 'mine' => 'true']);

            return $r->successful() && in_array($account->remote_id, array_column((array) $r->json('items', []), 'id'), true);
        }
        if ($account->provider === 'linkedin') {
            $r = $http->withHeaders(['Linkedin-Version' => (string) config('social_publishing.linkedin_version', '202609'), 'X-Restli-Protocol-Version' => '2.0.0'])->get('https://api.linkedin.com/rest/organizationAcls', ['q' => 'roleAssignee']);
            foreach ((array) $r->json('elements', []) as $acl) {
                if (($acl['organization'] ?? null) === $account->remote_id && ($acl['state'] ?? null) === 'APPROVED' && in_array($acl['role'] ?? null, ['ADMINISTRATOR', 'CONTENT_ADMIN'], true)) {
                    return $r->successful();
                }
            }

            return false;
        }
        $host = $account->provider === 'instagram' ? 'graph.instagram.com' : 'graph.facebook.com';
        $r = $http->get('https://'.$host.'/'.config('social_publishing.graph_version', 'v26.0').'/me', ['fields' => $account->provider === 'instagram' ? 'user_id' : 'id']);

        return $r->successful() && (string) $r->json($account->provider === 'instagram' ? 'user_id' : 'id') === $account->remote_id;
    }

    public function refresh(SocialAccount $account): void
    {
        $client = $this->client($account->provider);
        if ($account->provider === 'instagram') {
            $r = Http::acceptJson()->timeout(20)->get('https://graph.instagram.com/refresh_access_token', ['grant_type' => 'ig_refresh_token', 'access_token' => $account->access_token]);
        } elseif ($account->refresh_token !== null && in_array($account->provider, ['youtube', 'linkedin'], true)) {
            $url = $account->provider === 'youtube' ? 'https://oauth2.googleapis.com/token' : 'https://www.linkedin.com/oauth/v2/accessToken';
            $r = Http::asForm()->acceptJson()->timeout(20)->post($url, $client + ['grant_type' => 'refresh_token', 'refresh_token' => $account->refresh_token]);
        } else {
            $this->invalidate($account);
            throw ValidationException::withMessages(['connection' => 'Vuelve a autorizar esta cuenta para renovar la conexión.']);
        }
        if (! $r->successful() || ! is_string($r->json('access_token'))) {
            $this->invalidate($account);
            throw ValidationException::withMessages(['connection' => 'No fue posible renovar la conexión.']);
        }
        $newGrants = $r->json('scope');
        if (is_string($newGrants) && array_diff($this->scopes($account->provider), preg_split('/[ ,]+/', $newGrants) ?: []) !== []) {
            $this->invalidate($account);
            throw ValidationException::withMessages(['connection' => 'La renovación perdió permisos de publicación.']);
        }
        DB::transaction(fn () => $account->forceFill(['access_token' => $r->json('access_token'), 'refresh_token' => $r->json('refresh_token') ?? $account->refresh_token, 'expires_at' => now()->addSeconds((int) $r->json('expires_in', 3600))])->save());
    }

    private function invalidate(SocialAccount $account): void
    {
        DB::transaction(fn () => $account->forceFill(['status' => 'reauthorization_required', 'authorization_generation' => $account->authorization_generation + 1])->save());
    }

    public function revoke(string $id, ActorContext $actor): void
    {
        $this->auth->authorize($actor, 'manage:social-accounts', true);
        DB::transaction(function () use ($id): void {
            $a = SocialAccount::whereKey($id)->lockForUpdate()->firstOrFail();
            $a->forceFill(['status' => 'revoked', 'access_token' => null, 'refresh_token' => null, 'authorization_generation' => $a->authorization_generation + 1])->save();
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Services;

use App\Contracts\Services\PublicContentDiscoveryInterface;
use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\CapabilityAuthorizer;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Models\Market;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Modules\Marketing\DTOs\CreativePackage;
use Modules\SocialPublishing\Contracts\DestinationValidatorInterface;

final class DestinationValidator implements DestinationValidatorInterface
{
    public function __construct(private readonly PublicContentDiscoveryInterface $content, private readonly EntitySchemaRegistryInterface $entities, private readonly CapabilityAuthorizer $authorizer) {}

    public function check(CreativePackage $package, ?ActorContext $actor = null): void
    {
        if ($package->destinationUrl === null) {
            return;
        }
        $url = $package->destinationUrl;
        $base = (string) config('app.url');
        $path = (string) parse_url($url, PHP_URL_PATH);
        $safe = parse_url($url, PHP_URL_HOST) === parse_url($base, PHP_URL_HOST) && parse_url($url, PHP_URL_SCHEME) === parse_url($base, PHP_URL_SCHEME) && parse_url($url, PHP_URL_PORT) === parse_url($base, PHP_URL_PORT)
            && ! preg_match('~/(admin|api|install|forms|login)(/|$)~', $path);
        if (! $safe) {
            throw ValidationException::withMessages(['destination_url' => 'El destino debe ser una página pública del minisite en el mercado e idioma seleccionados.']);
        }
        $context = (array) ($package->provenance['context'] ?? []);
        $market = Market::where('code', $package->market)->where('is_active', true)->first();
        $confirmed = false;
        if ($market !== null && $actor !== null) {
            foreach (['page', 'post', 'product'] as $type) {
                if (isset($context['type']) && $context['type'] !== $type) {
                    continue;
                }
                $schema = $this->entities->get($type);
                if ($schema === null || ! $this->authorizer->allowed($actor, 'read', $schema->permission)) {
                    continue;
                }
                $query = $this->content->query($type, $market, $package->locale);
                if (isset($context['id'])) {
                    $query->whereKey((int) $context['id']);
                } else {
                    $query->whereHas('translations', fn ($q) => $q->where('locale', $package->locale)->where('slug', urldecode(basename($path))));
                }
                $entity = $query->first();
                if (! $entity instanceof Model) {
                    continue;
                }
                foreach ($entity->getRelation('translations') as $translation) {
                    if ($translation->getAttribute('locale') !== $package->locale) {
                        continue;
                    }
                    $canonical = $this->content->url($type, $market, $package->locale, (string) $translation->getAttribute('slug'));
                    if (parse_url($canonical, PHP_URL_PATH) === $path && parse_url($canonical, PHP_URL_HOST) === parse_url($url, PHP_URL_HOST)) {
                        $confirmed = true;
                    }
                }
            }
        }
        if (! $confirmed) {
            throw ValidationException::withMessages(['destination_url' => 'El destino no corresponde a contenido público autorizado en este mercado e idioma.']);
        }
        $r = Http::connectTimeout(3)->timeout(8)->withOptions(['allow_redirects' => false])->get($url);
        if (! $r->successful()) {
            throw ValidationException::withMessages(['destination_url' => 'El contenido de destino todavía no está publicado o disponible.']);
        }
    }
}

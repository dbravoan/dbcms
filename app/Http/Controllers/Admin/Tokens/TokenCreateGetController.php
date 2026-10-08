<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Tokens;

use App\Enums\OAuthScope;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\View\View;

final class TokenCreateGetController extends Controller
{
    public function __invoke(): View
    {
        $users = User::query()->where('is_service_account', false)->orderBy('name')->get();
        $availableScopes = OAuthScope::definitions();

        // Detect module authenticatable providers
        $moduleProviders = [];
        $providers = (array) Config::get('auth.providers', []);
        foreach ($providers as $providerKey => $providerConfig) {
            if ($providerKey === 'users') {
                continue;
            }
            $modelClass = (string) ($providerConfig['model'] ?? '');
            if ($modelClass !== '' && class_exists($modelClass)) {
                $moduleProviders[$providerKey] = [
                    'key' => $providerKey,
                    'model' => $modelClass,
                    'name' => class_basename($modelClass),
                    'records' => $modelClass::all(),
                ];
            }
        }

        return view('admin.tokens.create', [
            'users' => $users,
            'moduleProviders' => $moduleProviders,
            'availableScopes' => $availableScopes,
            'serviceRoles' => Role::query()->orderBy('name')->get(),
        ]);
    }
}

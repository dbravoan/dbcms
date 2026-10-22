<?php

declare(strict_types=1);

use App\Enums\OAuthScope;
use App\Http\Middleware\CheckPermission;
use App\Models\User;
use App\Services\Auth\OAuthScopeGrantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/generate-token', function (Request $request) {
    /** @var User $user */
    $user = $request->user();
    $grants = app(OAuthScopeGrantService::class);
    $scopes = array_values(array_filter([OAuthScope::MCP_ACCESS->value, OAuthScope::MCP_READ->value, OAuthScope::MCP_WRITE->value, OAuthScope::MCP_DESTRUCTIVE->value], fn (string $scope): bool => $grants->allows($user, $scope)));
    $tokenResult = $user->createToken('MCP Agent ('.$user->name.')', $scopes);

    return back()->with('mcp_token_generated', [
        'token' => $tokenResult->accessToken,
        'name' => 'MCP Agent ('.$user->name.')',
    ])->with('success', 'Personal Access Token generado correctamente para el servidor MCP.');
})->middleware(CheckPermission::class.':access:admin')->name('admin.modules.mcp.generate-token');

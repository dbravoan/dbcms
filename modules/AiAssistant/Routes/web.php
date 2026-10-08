<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\AiAssistant\Http\Controllers\Admin\BrandProfile;
use Modules\AiAssistant\Http\Controllers\Admin\CreateElement;
use Modules\AiAssistant\Http\Controllers\Admin\Generate;
use Modules\AiAssistant\Http\Controllers\Admin\Preset;
use Modules\AiAssistant\Http\Controllers\Admin\PreviewPrompt;
use Modules\AiAssistant\Http\Controllers\Admin\Providers;
use Modules\AiAssistant\Http\Controllers\Admin\TestConnection;
use Modules\AiAssistant\Http\Controllers\Admin\Workbench;
use Modules\AiAssistant\Http\Middleware\EnsureAiAuthorized;

Route::get('/', Workbench\GetController::class)
    ->middleware(EnsureAiAuthorized::class.':use:ai')
    ->name('admin.ai_assistant.workbench');

Route::post('/generate', Generate\PostController::class)
    ->middleware(EnsureAiAuthorized::class.':use:ai')
    ->name('admin.ai_assistant.generate');

Route::post('/generate/async', Generate\Async\PostController::class)
    ->middleware(EnsureAiAuthorized::class.':use:ai')
    ->name('admin.ai_assistant.generate_async');

Route::get('/generate/tasks/{task}', Generate\Status\GetController::class)
    ->middleware(EnsureAiAuthorized::class.':use:ai')
    ->name('admin.ai_assistant.generate_status');

Route::post('/create-element', CreateElement\PostController::class)
    ->middleware(EnsureAiAuthorized::class.':use:ai')
    ->name('admin.ai_assistant.create_element');

Route::post('/test-connection', TestConnection\PostController::class)
    ->middleware(EnsureAiAuthorized::class.':manage:ai')
    ->name('admin.ai_assistant.test_connection');

Route::match(['post', 'put'], '/brand-profile', BrandProfile\PutController::class)
    ->middleware(EnsureAiAuthorized::class.':manage:ai')
    ->name('admin.ai_assistant.save_brand_profile');

Route::post('/apply-preset', Preset\PostController::class)
    ->middleware(EnsureAiAuthorized::class.':manage:ai')
    ->name('admin.ai_assistant.apply_preset');

Route::post('/preview-prompt', PreviewPrompt\PostController::class)
    ->middleware(EnsureAiAuthorized::class.':use:ai')
    ->name('admin.ai_assistant.preview_prompt');

Route::post('/providers/save', Providers\Save\PostController::class)
    ->middleware(EnsureAiAuthorized::class.':manage:ai')
    ->name('admin.ai_assistant.providers.save');

Route::get('/providers/models', Providers\Catalog\GetController::class)
    ->middleware(EnsureAiAuthorized::class.':manage:ai')
    ->name('admin.ai_assistant.providers.models');

Route::post('/providers/set-default', Providers\SetDefault\PostController::class)
    ->middleware(EnsureAiAuthorized::class.':manage:ai')
    ->name('admin.ai_assistant.providers.set_default');

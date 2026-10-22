<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateMcpPassport;
use Illuminate\Support\Facades\Route;
use Modules\AiAssistant\Http\Controllers\Api\AiAssistantApiController;
use Modules\AiAssistant\Http\Controllers\Api\EntityTranslationPostController;
use Modules\AiAssistant\Http\Middleware\EnsureAiAuthorized;

Route::middleware([AuthenticateMcpPassport::class.':ai:use', EnsureAiAuthorized::class.':use:ai'])->group(function (): void {
    Route::post('/generate-block', [AiAssistantApiController::class, 'generateBlock']);
    Route::post('/draft-article', [AiAssistantApiController::class, 'draftArticle']);
    Route::post('/translate-entity', EntityTranslationPostController::class);
    Route::post('/humanize', [AiAssistantApiController::class, 'humanize']);
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\AiAssistant\Http\Middleware\EnsureAiAuthorized;

Route::prefix('orchestration')->name('orchestration.')->middleware(EnsureAiAuthorized::class.':use:ai')->group(__DIR__.'/orchestration.php');

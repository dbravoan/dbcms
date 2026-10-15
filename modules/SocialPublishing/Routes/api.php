<?php

declare(strict_types=1);
use App\Enums\OAuthScope;
use App\Http\Middleware\AuthenticateMcpPassport;
use Illuminate\Support\Facades\Route;
use Modules\SocialPublishing\Http\Controllers\Api;

Route::middleware(AuthenticateMcpPassport::class.':'.OAuthScope::SOCIAL_READ->value)->group(function (): void {
    Route::get('/publications', Api\PublicationsGetController::class);
    Route::get('/publications/{publication}', Api\PublicationResultGetController::class);
});
Route::middleware(AuthenticateMcpPassport::class.':'.OAuthScope::SOCIAL_WRITE->value)->group(function (): void {
    Route::post('/publications', Api\PublicationDraftPostController::class);
    Route::post('/publications/{publication}/schedule', Api\PublicationSchedulePostController::class);
    Route::post('/deliveries/{delivery}/cancel', Api\DeliveryCancelPostController::class);
});

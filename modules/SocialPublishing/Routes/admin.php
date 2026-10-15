<?php

declare(strict_types=1);
use App\Http\Middleware\CheckPermission;
use Illuminate\Support\Facades\Route;
use Modules\SocialPublishing\Http\Controllers\Admin;

Route::prefix('social-publishing')->name('social-publishing.')->group(function (): void {
    Route::middleware(CheckPermission::class.':read:social-publishing')->group(function (): void {
        Route::get('/', Admin\SocialPublishingGetController::class)->name('index');
        Route::get('/publications/{publication}', Admin\PublicationPreviewGetController::class)->name('preview');
        Route::get('/publications/{publication}/assets/{index}', Admin\PublicationAssetGetController::class)->whereNumber('index')->name('preview-asset');
        Route::get('/publications/{publication}/export', Admin\PublicationExportGetController::class)->name('export');
    });
    Route::middleware(CheckPermission::class.':manage:social-publishing')->group(function (): void {
        Route::post('/publications', Admin\PublicationPostController::class)->name('store');
        Route::post('/publications/{publication}/schedule', Admin\PublicationSchedulePostController::class)->name('schedule');
        Route::post('/deliveries/{delivery}/cancel', Admin\DeliveryCancelPostController::class)->name('cancel');
        Route::post('/deliveries/{delivery}/reconcile', Admin\DeliveryReconcilePostController::class)->name('reconcile');
    });
    Route::post('/publications/{publication}/approve', Admin\PublicationApprovePostController::class)->middleware(CheckPermission::class.':approve:social-publishing')->name('approve');
    Route::middleware(CheckPermission::class.':manage:social-accounts')->group(function (): void {
        Route::post('/accounts/manual', Admin\AccountManualPostController::class)->name('manual-account');
        Route::post('/accounts/{account}/check', Admin\AccountCheckPostController::class)->name('check');
        Route::post('/oauth/{provider}', Admin\OAuthBeginPostController::class)->whereIn('provider', ['facebook', 'instagram', 'linkedin', 'youtube'])->name('oauth.begin');
        Route::get('/oauth/{provider}/callback', Admin\OAuthCallbackGetController::class)->whereIn('provider', ['facebook', 'instagram', 'linkedin', 'youtube'])->name('oauth.callback');
        Route::post('/accounts/select', Admin\OAuthSelectPostController::class)->name('oauth.select');
        Route::post('/accounts/{account}/revoke', Admin\AccountRevokePostController::class)->name('revoke');
    });
});

<?php

declare(strict_types=1);

use App\Http\Middleware\CheckPermission;
use Illuminate\Support\Facades\Route;
use Modules\Forms\Http\Controllers\Admin\FormCreateGetController;
use Modules\Forms\Http\Controllers\Admin\FormDeleteController;
use Modules\Forms\Http\Controllers\Admin\FormEditGetController;
use Modules\Forms\Http\Controllers\Admin\FormPostController;
use Modules\Forms\Http\Controllers\Admin\FormPutController;
use Modules\Forms\Http\Controllers\Admin\FormsGetController;
use Modules\Forms\Http\Controllers\Admin\FormSubmissionDeleteController;
use Modules\Forms\Http\Controllers\Admin\FormSubmissionsGetController;

Route::middleware([CheckPermission::class.':manage:forms'])->prefix('forms')->name('forms.')->group(function (): void {
    Route::get('/', FormsGetController::class)->name('index');
    Route::get('/create', FormCreateGetController::class)->name('create');
    Route::post('/', FormPostController::class)->name('store');
    Route::get('/{form}/edit', FormEditGetController::class)->name('edit');
    Route::put('/{form}', FormPutController::class)->name('update');
    Route::delete('/{form}', FormDeleteController::class)->name('destroy');
    Route::get('/submissions', FormSubmissionsGetController::class)->name('submissions');
    Route::delete('/submissions/{submission}', FormSubmissionDeleteController::class)->name('submissions.destroy');
});

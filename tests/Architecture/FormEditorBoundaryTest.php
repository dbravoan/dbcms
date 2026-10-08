<?php

declare(strict_types=1);

use App\Models\Language;
use App\Models\Market;
use App\Services\Content\EditorialDraftService;
use Modules\Forms\Http\Controllers\Admin\FormCreateGetController;
use Modules\Forms\Http\Controllers\Admin\FormEditGetController;
use Modules\Forms\Models\FormField;
use Modules\Forms\Services\FormSchemaService;
use Modules\Forms\Support\FieldTypeOptions;

test('form editor transports delegate presentation instead of querying or rebuilding domain state', function (): void {
    expect([FormCreateGetController::class, FormEditGetController::class])
        ->not->toUse([Language::class, Market::class, FormField::class, EditorialDraftService::class, FormSchemaService::class, FieldTypeOptions::class]);
});

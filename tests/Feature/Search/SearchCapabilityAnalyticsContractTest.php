<?php

declare(strict_types=1);

use App\Contracts\Services\SearchAnalyticsInterface;
use App\Core\Modules\ModuleManager;
use App\Services\Search\NoSearchAnalytics;
use Modules\Search\Models\SearchTerm;
use Tests\Support\SearchAnalyticsComposition;

uses(SearchAnalyticsComposition::class);

test('neutral search analytics require no optional module data', function (): void {
    expect((new NoSearchAnalytics)->topTerms(null, 10))->toBe([]);
});

test('search analytics preserve their core contract and disappear when the module is disabled', function (): void {
    SearchTerm::create(['term' => 'calidad', 'locale' => 'es', 'market_code' => 'es', 'hits' => 4, 'results' => 2]);
    SearchTerm::create(['term' => 'quality', 'locale' => 'en', 'market_code' => 'eu', 'hits' => 7, 'results' => 3]);
    $analytics = app(SearchAnalyticsInterface::class);
    expect($analytics->topTerms('es', 10))->toBe([['term' => 'calidad', 'hits' => 4, 'results' => 2]]);
    app(ModuleManager::class)->deactivate('search');
    expect($analytics->topTerms(null, 10))->toBe([]);
});

<?php

declare(strict_types=1);

use App\Core\Blocks\BlockPackageManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Modules\AiAssistant\Contracts\AiAssistantDomainServiceInterface;
use Modules\AiAssistant\Services\BlockIntelligenceService;
use Tests\Support\AiComposition;

uses(AiComposition::class);

test('disabled block packages reject generation before any provider request', function (): void {
    Http::preventStrayRequests();
    app(BlockPackageManager::class)->setEnabled('basic', false);
    expect(fn () => app(AiAssistantDomainServiceInterface::class)->generateBlock('hero', 'Generate a landing'))->toThrow(ValidationException::class);
    expect(fn () => app(BlockIntelligenceService::class)->sanitizeBlockPayload('hero', ['title' => 'Cannot insert']))->toThrow(ValidationException::class);
    Http::assertNothingSent();
});

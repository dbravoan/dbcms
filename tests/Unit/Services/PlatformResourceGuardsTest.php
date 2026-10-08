<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Language;
use App\Models\Market;
use App\Services\Resources\LanguageMutationHandler;
use App\Services\Resources\MarketMutationHandler;
use App\Services\Resources\ResourceMutationException;
use PHPUnit\Framework\TestCase;

final class PlatformResourceGuardsTest extends TestCase
{
    public function test_default_market_cannot_be_deleted_through_a_resource_handler(): void
    {
        $this->expectException(ResourceMutationException::class);
        (new MarketMutationHandler)->deleting(new Market(['is_default' => true]));
    }

    public function test_default_language_cannot_be_deleted_through_a_resource_handler(): void
    {
        $this->expectException(ResourceMutationException::class);
        (new LanguageMutationHandler)->deleting(new Language(['is_default' => true]));
    }
}

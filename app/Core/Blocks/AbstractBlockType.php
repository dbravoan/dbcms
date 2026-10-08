<?php

declare(strict_types=1);

namespace App\Core\Blocks;

use App\Core\Blocks\Contracts\BlockTypeInterface;
use App\Services\Cms\FrontViewResolver;

abstract class AbstractBlockType implements BlockTypeInterface
{
    public function getRequiredModule(): ?string
    {
        return null;
    }

    public function isDynamic(): bool
    {
        return $this->getRequiredModule() !== null;
    }

    public function isFullWidth(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return '';
    }

    /**
     * Renders a Blade view checking theme override first, then fallback to base component.
     *
     * @param  array<string, mixed>  $data
     */
    protected function renderView(string $viewName, array $data = []): string
    {
        $resolved = app(FrontViewResolver::class)->block($viewName);

        if ($resolved !== null) {
            return view($resolved, $data)->render();
        }

        return "<!-- View [{$viewName}] not found -->";
    }
}

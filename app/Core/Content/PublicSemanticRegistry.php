<?php

declare(strict_types=1);

namespace App\Core\Content;

use App\Core\Content\Contracts\PublicSemanticContributorInterface;
use App\Core\Content\Contracts\PublicWidgetSemanticContributorInterface;
use App\Core\Modules\ModuleManager;
use App\Models\Market;
use InvalidArgumentException;

final class PublicSemanticRegistry
{
    /** @var array<string,PublicSemanticContributorInterface> */
    private array $contributors = [];

    /** @var array<string,PublicWidgetSemanticContributorInterface> */
    private array $widgets = [];

    public function __construct(private readonly ModuleManager $modules) {}

    public function register(PublicSemanticContributorInterface $contributor): void
    {
        if (isset($this->contributors[$contributor->key()])) {
            throw new InvalidArgumentException('Duplicate public semantic contributor.');
        }
        $this->contributors[$contributor->key()] = $contributor;
    }

    public function registerWidget(PublicWidgetSemanticContributorInterface $contributor): void
    {
        foreach ($contributor->keys() as $key) {
            if (isset($this->widgets[$key])) {
                throw new InvalidArgumentException('Duplicate public widget semantic contributor.');
            }
            $this->widgets[$key] = $contributor;
        }
    }

    public function index(Market $market, string $locale): string
    {
        return $this->collect($market, $locale, false);
    }

    public function corpus(Market $market, string $locale): string
    {
        return $this->collect($market, $locale, true);
    }

    /** @param array<string,mixed> $payload */
    public function widgetText(array $payload, Market $market, string $locale): string
    {
        if (! $this->contextIsPublic($market, $locale)) {
            return '';
        }
        $key = $payload['widget_key'] ?? $payload['type'] ?? '';
        $widget = is_string($key) ? ($this->widgets[$key] ?? null) : null;

        return $widget !== null && $this->modules->isModuleEnabled($widget->ownerModule()) ? $widget->text($payload, $market, $locale) : '';
    }

    private function collect(Market $market, string $locale, bool $full): string
    {
        if (! $this->contextIsPublic($market, $locale)) {
            return '';
        }
        $output = '';
        foreach ($this->contributors as $contributor) {
            if ($this->modules->isModuleEnabled($contributor->ownerModule())) {
                $output .= $full ? $contributor->corpus($market, $locale) : $contributor->index($market, $locale);
            }
        }

        return $output;
    }

    private function contextIsPublic(Market $market, string $locale): bool
    {
        return $market->is_active && $market->languages()->where('code', $locale)->where('is_active', true)->exists();
    }
}

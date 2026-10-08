<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Core\Blocks\Contracts\TranslatableBlockInterface;

/** Additive descriptors: undeclared payload fields and unknown types stay protected. */
final class BlockTranslationRegistry
{
    public function __construct(private readonly ?BlockRegistryInterface $blocks = null) {}

    /** @var array<string,list<string>> */
    private array $paths = [
        'text' => ['title', 'content'],
        'hero' => ['title', 'headline', 'content', 'subtitle', 'subheadline', 'body', 'cta_text', 'cta_label', 'secondary_cta_text', 'badge'],
        'cta' => ['title', 'content', 'description', 'cta_text', 'button_text', 'secondary_cta_text'],
        'features' => ['title', 'content', 'items.*.title', 'items.*.description', 'items.*.desc'],
        'faq' => ['title', 'content', 'items.*.question', 'items.*.answer', 'items.*.q', 'items.*.a'],
        'testimonials' => ['title', 'items.*.quote', 'items.*.text', 'items.*.role'],
        'carousel' => ['title', 'slides.*.title', 'slides.*.subtitle', 'slides.*.cta_text', 'items.*.title', 'items.*.description', 'items.*.caption', 'items.*.alt'],
        'pricing' => ['title', 'content', 'items.*.name', 'items.*.description', 'items.*.features.*', 'items.*.cta_text', 'items.*.period'],
        'video' => ['title', 'content', 'caption'],
        'before_after' => ['title', 'content', 'before_label', 'after_label'],
        'search_box' => ['title', 'placeholder', 'button_text'],
        'roi' => ['title', 'content', 'description'],
        'kpi' => ['title', 'content', 'items.*.label', 'items.*.description'],
        'code_embed' => ['title'],
    ];

    /** @param list<string> $paths */
    public function register(string $type, array $paths): void
    {
        $this->paths[$type] = $paths;
    }

    /** @return list<string>|null */
    public function paths(string $type): ?array
    {
        if ($this->blocks !== null) {
            $active = $this->blocks->getActiveBlocks();
            $block = $active[$type] ?? null;
            if ($block === null) {
                return null;
            }
            if ($block instanceof TranslatableBlockInterface) {
                return $block->getTranslationPaths();
            }
        }

        return $this->paths[$type] ?? null;
    }
}

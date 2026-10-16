<?php

declare(strict_types=1);

namespace App\Core\Blocks;

use App\Core\Blocks\Contracts\BlockRegistryInterface;

/** Prepares picker definitions and dynamic fields before rendering Blade. */
final class BlockEditorCatalogue
{
    public function __construct(private readonly BlockRegistryInterface $blocks) {}

    /** @return array<string,mixed> */
    public function data(): array
    {
        $active = $this->blocks->getActiveBlocks();
        $types = [];
        $dynamic = [];
        foreach ($active as $block) {
            $dynamic = array_replace($dynamic, $block->getEditorData());
            $types[] = [
                'type' => $block->getType(),
                'name' => $block->getName(),
                'icon' => $block->getIcon(),
                'category' => in_array($block->getCategory(), ['dynamic', 'widget', 'interactive'], true) ? 'widget' : 'content',
                'raw_category' => $block->getCategory(),
                'desc' => $block->getDescription(),
                'fullWidth' => $block->isFullWidth(),
                'defaultPayload' => $block->getDefaultPayload(),
                'schema' => $block->getEditorSchema(),
                'editorView' => $block->getEditorView(),
            ];
        }

        return ['activeBlockTypes' => $active, 'dynamicPayloads' => $dynamic, 'availableTypesJs' => $types];
    }
}

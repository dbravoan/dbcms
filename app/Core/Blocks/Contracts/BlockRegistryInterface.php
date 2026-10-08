<?php

declare(strict_types=1);

namespace App\Core\Blocks\Contracts;

interface BlockRegistryInterface
{
    /**
     * Registers a block type definition.
     */
    public function register(BlockTypeInterface $block): void;

    /**
     * Retrieves a block type by its identifier.
     */
    public function get(string $type): ?BlockTypeInterface;

    /**
     * Retrieves all registered block types.
     *
     * @return array<string, BlockTypeInterface>
     */
    public function all(): array;

    /**
     * Retrieves all active blocks whose required module is currently installed & active.
     *
     * @return array<string, BlockTypeInterface>
     */
    public function getActiveBlocks(): array;

    /**
     * Retrieves active blocks grouped by their category.
     *
     * @return array<string, array<string, BlockTypeInterface>>
     */
    public function getBlocksByCategory(): array;

    /**
     * Retrieves live dynamic data payloads for select dropdowns in the block manager.
     * E.g. available forms, blog categories, blog posts, shop categories, products, gallery albums.
     *
     * @return array<string, mixed>
     */
    public function getDynamicDataPayloads(): array;
}

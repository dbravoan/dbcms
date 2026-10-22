<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Core\Blocks\Contracts\BlockTypeInterface;
use App\Core\Blocks\Contracts\TranslatableBlockInterface;
use BlockPackages\BeforeAfter\Blocks\BeforeAfterBlock;
use BlockPackages\Charts\Blocks\ChartsBlock;
use BlockPackages\Charts\Blocks\KpiBlock;
use BlockPackages\Charts\Services\ChartRendererService;
use Modules\AiAssistant\Services\StructuredTranslationPayload;
use Modules\Blog\Blocks\BlogPostsBlock;
use Modules\Forms\Blocks\CustomFormBlock;
use Modules\Gallery\Blocks\GalleryAlbumBlock;
use Modules\Shop\Blocks\ShopProductsBlock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3).'/block-packages/Charts/Services/ChartRendererService.php';
require_once dirname(__DIR__, 3).'/block-packages/Charts/Blocks/ChartsBlock.php';
require_once dirname(__DIR__, 3).'/block-packages/Charts/Blocks/KpiBlock.php';
require_once dirname(__DIR__, 3).'/block-packages/BeforeAfter/Blocks/BeforeAfterBlock.php';

final class ModuleBlockTranslationDescriptorTest extends TestCase
{
    /** @return iterable<string,array{BlockTypeInterface,array<string,mixed>,array<string,mixed>}> */
    public static function modulePayloads(): iterable
    {
        yield 'Blog references' => [new BlogPostsBlock, ['title' => 'Artículos', 'category' => '5', 'post_id' => '17', 'limit' => 3, 'grid_columns' => '3'], ['title' => 'EN:Artículos']];
        yield 'Shop references' => [new ShopProductsBlock, ['title' => 'Productos', 'category' => '8', 'product_id' => '42', 'limit' => 4, 'grid_columns' => '4'], ['title' => 'EN:Productos']];
        yield 'Gallery references' => [new GalleryAlbumBlock, ['title' => 'Galerías', 'album_slug' => 'exposicion', 'grid_columns' => '3', 'limit' => 6], ['title' => 'EN:Galerías']];
        yield 'Form references' => [new CustomFormBlock, ['title' => 'Contacto', 'button_text' => 'Enviar', 'form_slug' => 'solicitud'], ['title' => 'EN:Contacto', 'button_text' => 'EN:Enviar']];
        yield 'Comparison images' => [new BeforeAfterBlock, ['title' => 'Comparación', 'image_url' => '/before.jpg', 'after_image_url' => '/after.jpg', 'before_label' => 'Antes', 'after_label' => 'Después'], ['title' => 'EN:Comparación', 'before_label' => 'EN:Antes', 'after_label' => 'EN:Después']];
        yield 'KPI descriptions' => [new KpiBlock, ['title' => 'Resultados', 'items' => [['value' => '42%', 'label' => 'Ahorro', 'description' => 'Resultado observado']]], ['title' => 'EN:Resultados', 'items' => [['value' => '42%', 'label' => 'EN:Ahorro', 'description' => 'EN:Resultado observado']]]];
        yield 'Chart data' => [new ChartsBlock(new ChartRendererService), ['title' => 'Rendimiento', 'chart_type' => 'bar', 'items' => [['label' => 'Velocidad', 'value' => 98, 'unit' => '%', 'color' => '#38BDF8']], 'donut_percentage' => 88, 'donut_label' => 'Eficiencia', 'donut_subtext' => 'Reducción', 'metric_value' => '99.99%', 'metric_label' => 'Disponibilidad', 'metric_description' => 'Servicio global', 'currency_symbol' => '€'], ['title' => 'EN:Rendimiento', 'items' => [['label' => 'EN:Velocidad', 'value' => 98, 'unit' => '%', 'color' => '#38BDF8']], 'donut_label' => 'EN:Eficiencia', 'donut_subtext' => 'EN:Reducción', 'metric_label' => 'EN:Disponibilidad', 'metric_description' => 'EN:Servicio global']];
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $changes */
    #[DataProvider('modulePayloads')]
    public function test_module_owns_text_paths_and_translation_keeps_references_and_numeric_data(BlockTypeInterface $block, array $data, array $changes): void
    {
        self::assertInstanceOf(TranslatableBlockInterface::class, $block);
        $blocks = $this->createStub(BlockRegistryInterface::class);
        $blocks->method('getActiveBlocks')->willReturn([$block->getType() => $block]);
        $translator = new StructuredTranslationPayload($blocks);
        $source = ['id' => 'section-17', 'type' => $block->getType()] + $data;
        $snapshot = $translator->extract([], [$source], []);
        self::assertSame([], $snapshot->protectedBlockTypes);
        $translated = array_map(static fn (array $segment): array => ['id' => $segment['id'], 'text' => 'EN:'.$segment['text']], $snapshot->segments);
        self::assertSame([array_replace($source, $changes)], $translator->restore($snapshot, $translated)['sections']);
        self::assertSame(['fields' => [], 'sections' => [$source]], $snapshot->original);
    }

    public function test_inactive_module_keeps_entire_block_protected_without_emitting_text(): void
    {
        $blocks = $this->createStub(BlockRegistryInterface::class);
        $blocks->method('getActiveBlocks')->willReturn([]);
        $translator = new StructuredTranslationPayload($blocks);
        $source = ['id' => 'hidden', 'type' => 'widget_products', 'title' => 'No traducir', 'product_id' => '42'];
        $snapshot = $translator->extract([], [$source], []);
        self::assertSame([], $snapshot->segments);
        self::assertSame(['widget_products'], $snapshot->protectedBlockTypes);
        self::assertSame([$source], $translator->restore($snapshot, [])['sections']);
    }
}

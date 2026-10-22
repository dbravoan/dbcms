<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Blocks\Contracts\BlockRegistryInterface;
use BlockPackages\Basic\Blocks\FeaturesBlock;
use BlockPackages\Basic\Blocks\HeroBlock;
use BlockPackages\Basic\Blocks\PricingBlock;
use Modules\AiAssistant\Services\StructuredTranslationPayload;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

require_once dirname(__DIR__, 3).'/block-packages/Basic/Blocks/HeroBlock.php';
require_once dirname(__DIR__, 3).'/block-packages/Basic/Blocks/FeaturesBlock.php';
require_once dirname(__DIR__, 3).'/block-packages/Basic/Blocks/PricingBlock.php';

final class StructuredTranslationPayloadTest extends TestCase
{
    public function test_reassembles_html_without_exposing_or_changing_attributes_code_or_media(): void
    {
        $source = '<p class="copy">Hola <strong>mundo</strong><a href="/es/ayuda">Ayuda</a><code>echo "hola";</code><img src="/logo.png" alt="Logo"></p>';
        $payload = $this->payload();
        $snapshot = $payload->extract(['title' => 'Título', 'slug' => 'titulo', 'body' => $source], [], ['title', 'body']);
        self::assertSame(['Título', 'Hola', 'mundo', 'Ayuda'], array_column($snapshot->segments, 'text'));
        $result = $payload->restore($snapshot, $this->translated($snapshot->segments));
        self::assertSame('titulo', $result['fields']['slug']);
        self::assertSame('<p class="copy">EN:Hola <strong>EN:mundo</strong><a href="/es/ayuda">EN:Ayuda</a><code>echo "hola";</code><img src="/logo.png" alt="Logo"></p>', $result['fields']['body']);
    }

    public function test_nested_columns_translate_only_registered_text_paths_preserving_structure(): void
    {
        $sections = [['id' => 's1', 'columns_layout' => '2', 'columns' => [['id' => 'c1', 'blocks' => [['id' => 'b1', 'type' => 'hero', 'title' => 'Hola', 'cta_text' => 'Comprar', 'cta_url' => '/tienda', 'image_url' => '/cover.jpg', 'grid_columns' => 2], ['id' => 'b2', 'type' => 'custom_private', 'content' => 'Protegido']]]]]];
        $payload = $this->payload();
        $snapshot = $payload->extract(['title' => 'Producto', 'price' => 12, 'sku' => 'ABC'], $sections, ['title']);
        self::assertSame(['Producto', 'Hola', 'Comprar'], array_column($snapshot->segments, 'text'));
        $result = $payload->restore($snapshot, $this->translated($snapshot->segments));
        $expected = $sections;
        $expected[0]['columns'][0]['blocks'][0]['title'] = 'EN:Hola';
        $expected[0]['columns'][0]['blocks'][0]['cta_text'] = 'EN:Comprar';
        self::assertSame($expected, $result['sections']);
        self::assertSame(12, $result['fields']['price']);
        self::assertSame('ABC', $result['fields']['sku']);
        self::assertContains('custom_private', $snapshot->protectedBlockTypes);
    }

    public function test_markdown_delimiters_destinations_and_code_remain_byte_identical(): void
    {
        $source = "## Hola **mundo**\n\n[Ayuda](https://example.com/es?q=1) y `echo hola`\n```php\necho 'hola';\n```\n![Logo](/image.png)";
        $payload = $this->payload();
        $snapshot = $payload->extract(['body' => $source], [], ['body']);
        $restored = $payload->restore($snapshot, $this->translated($snapshot->segments))['fields']['body'];
        self::assertStringContainsString('## EN:Hola **EN:mundo**', $restored);
        self::assertStringContainsString('[EN:Ayuda](https://example.com/es?q=1)', $restored);
        self::assertStringContainsString("```php\necho 'hola';\n```", $restored);
        self::assertStringContainsString('`echo hola`', $restored);
        self::assertStringContainsString('![Logo](/image.png)', $restored);
    }

    public function test_model_cannot_add_omit_duplicate_segments_or_inject_markup(): void
    {
        $payload = $this->payload();
        $snapshot = $payload->extract(['title' => 'Hola'], [], ['title']);
        foreach ([[], [['id' => 'invented', 'text' => 'Hello']], [['id' => 't0', 'text' => 'Hello'], ['id' => 't0', 'text' => 'Again']], [['id' => 't0', 'text' => '<script>alert(1)</script>']]] as $result) {
            try {
                $payload->restore($snapshot, $result);
                self::fail('Invalid model segment result accepted.');
            } catch (UnexpectedValueException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_unavailable_container_keeps_its_fields_and_translates_available_nested_children(): void
    {
        $sections = [['type' => 'custom_container', 'id' => 'disabled-container', 'title' => 'No traducir', 'items' => [['title' => 'Dato protegido']], 'columns' => [['id' => 'c1', 'blocks' => [['type' => 'hero', 'title' => 'Texto disponible', 'image_url' => '/image.jpg'], ['type' => 'private', 'content' => 'También protegido']]]]]];
        $blocks = $this->createStub(BlockRegistryInterface::class);
        $blocks->method('getActiveBlocks')->willReturn(['hero' => new HeroBlock]);
        $payload = new StructuredTranslationPayload($blocks);
        $snapshot = $payload->extract(['title' => 'Título'], $sections, ['title']);
        self::assertSame(['Título', 'Texto disponible'], array_column($snapshot->segments, 'text'));
        $expected = $sections;
        $expected[0]['columns'][0]['blocks'][0]['title'] = 'EN:Texto disponible';
        self::assertSame($expected, $payload->restore($snapshot, $this->translated($snapshot->segments))['sections']);
        self::assertSame($sections, $snapshot->original['sections']);
        self::assertSame(['custom_container', 'private'], $snapshot->protectedBlockTypes);
    }

    public function test_nested_repeater_and_list_text_uses_owner_paths_without_translating_prices_or_links(): void
    {
        $sections = [
            ['type' => 'features', 'items' => [['title' => 'Rápido', 'description' => 'Menos espera', 'icon' => 'bolt']]],
            ['type' => 'pricing', 'items' => [['name' => 'Equipo', 'price' => '29€/mes', 'period' => 'Cada mes', 'features' => ['Cinco sitios'], 'cta_url' => '/comprar']]],
        ];
        $payload = $this->payload();
        $snapshot = $payload->extract([], $sections, []);
        self::assertSame(['Rápido', 'Menos espera', 'Equipo', 'Cinco sitios', 'Cada mes'], array_column($snapshot->segments, 'text'));
        $restored = $payload->restore($snapshot, $this->translated($snapshot->segments))['sections'];
        self::assertSame('29€/mes', $restored[1]['items'][0]['price']);
        self::assertSame('/comprar', $restored[1]['items'][0]['cta_url']);
        self::assertSame(['EN:Cinco sitios'], $restored[1]['items'][0]['features']);
        self::assertSame('bolt', $restored[0]['items'][0]['icon']);
    }

    private function payload(): StructuredTranslationPayload
    {
        $blocks = $this->createStub(BlockRegistryInterface::class);
        $blocks->method('getActiveBlocks')->willReturn(['hero' => new HeroBlock, 'features' => new FeaturesBlock, 'pricing' => new PricingBlock]);

        return new StructuredTranslationPayload($blocks);
    }

    /**
     * @param  list<array{id:string,text:string}>  $segments
     * @return list<array{id:string,text:string}>
     */
    private function translated(array $segments): array
    {
        return array_map(static fn (array $segment): array => ['id' => $segment['id'], 'text' => 'EN:'.$segment['text']], $segments);
    }
}

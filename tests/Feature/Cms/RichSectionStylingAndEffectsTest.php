<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Core\Sections\SectionRendererService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RichSectionStylingAndEffectsTest extends TestCase
{
    use RefreshDatabase;

    protected SectionRendererService $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = app(SectionRendererService::class);
    }

    protected function tearDown(): void
    {
        config(['cms.theme' => 'db-cyber']);
        parent::tearDown();
    }

    public function test_renderer_renders_custom_background_color_and_borders(): void
    {
        $section = [
            'type' => 'text',
            'title' => 'Sección con Estilo Personalizado',
            'content' => 'Contenido de prueba.',
            'bg_color' => '#1A202C',
            'border_top' => [
                'enabled' => true,
                'color' => '#00FF99',
                'width' => '3px',
                'style' => 'solid',
            ],
            'border_bottom' => [
                'enabled' => true,
                'color' => '#E2E8F0',
                'width' => '2px',
                'style' => 'dashed',
            ],
        ];

        $html = $this->renderer->renderSection($section);

        $this->assertStringContainsString('background-color: #1A202C', $html);
        $this->assertStringContainsString('border-top: 3px solid #00FF99', $html);
        $this->assertStringContainsString('border-bottom: 2px dashed #E2E8F0', $html);
    }

    public function test_renderer_renders_svg_shape_dividers(): void
    {
        $section = [
            'type' => 'text',
            'title' => 'Sección con Separadores Decorativos',
            'content' => 'Texto con olas y corte diagonal.',
            'divider_top' => [
                'type' => 'waves',
                'color' => '#0E0E0E',
                'height' => 60,
                'flip' => false,
            ],
            'divider_bottom' => [
                'type' => 'tilt',
                'color' => '#111827',
                'height' => 80,
                'flip' => true,
            ],
        ];

        $html = $this->renderer->renderSection($section);

        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('fill="#0E0E0E"', $html);
        $this->assertStringContainsString('fill="#111827"', $html);
        $this->assertStringContainsString('height: 60px', $html);
        $this->assertStringContainsString('height: 80px', $html);
    }

    public function test_renderer_renders_content_width_full_and_centered(): void
    {
        // 1. Full width (100%)
        $fullWidthSection = [
            'type' => 'text',
            'title' => 'Ancho Completo',
            'content' => 'Sección que ocupa el 100% de la pantalla.',
            'content_width' => 'full',
        ];

        $fullHtml = $this->renderer->renderSection($fullWidthSection);
        $this->assertStringContainsString('w-full', $fullHtml);
        $this->assertStringNotContainsString('max-w-6xl mx-auto', $fullHtml);

        // 2. Centered width
        $centeredSection = [
            'type' => 'text',
            'title' => 'Centrado',
            'content' => 'Sección centrada.',
            'content_width' => 'centered',
        ];

        $centeredHtml = $this->renderer->renderSection($centeredSection);
        $this->assertStringContainsString('max-w-7xl mx-auto', $centeredHtml);
    }

    public function test_renderer_renders_background_image_and_video_with_overlays(): void
    {
        // 1. Background Image
        $imageSection = [
            'type' => 'text',
            'title' => 'Fondo con Imagen',
            'content' => 'Contenido sobre imagen.',
            'bg_type' => 'image',
            'bg_image_url' => 'https://example.com/hero-bg.jpg',
            'bg_image_overlay' => 'rgba(0,0,0,0.6)',
        ];

        $imageHtml = $this->renderer->renderSection($imageSection);
        $this->assertStringContainsString('background-image: url(\'https://example.com/hero-bg.jpg\')', $imageHtml);
        $this->assertStringContainsString('background-color: rgba(0,0,0,0.6)', $imageHtml);

        // 2. Background Video
        $videoSection = [
            'type' => 'text',
            'title' => 'Fondo con Vídeo',
            'content' => 'Contenido sobre vídeo.',
            'bg_type' => 'video',
            'bg_video_url' => 'https://example.com/tech-background.mp4',
            'bg_video_poster' => 'https://example.com/poster.jpg',
            'bg_video_overlay' => 'rgba(10,10,15,0.7)',
        ];

        $videoHtml = $this->renderer->renderSection($videoSection);
        $this->assertStringContainsString('<video', $videoHtml);
        $this->assertStringContainsString('https://example.com/tech-background.mp4', $videoHtml);
        $this->assertStringContainsString('poster="https://example.com/poster.jpg"', $videoHtml);
        $this->assertStringContainsString('background-color: rgba(10,10,15,0.7)', $videoHtml);
    }

    public function test_renderer_supports_scroll_magic_video_playback_and_scroll_rotation(): void
    {
        $scrollSection = [
            'type' => 'text',
            'title' => 'Sección Interactiva ScrollMagic',
            'content' => 'Animación al hacer scroll.',
            'bg_type' => 'video',
            'bg_video_url' => 'https://example.com/loop.mp4',
            'scroll_magic' => [
                'scrub' => 'rotate',
                'rotate_speed' => 1.5,
                'behavior' => 'video-playback',
            ],
        ];

        $html = $this->renderer->renderSection($scrollSection);

        $this->assertStringContainsString('data-scroll-scrub="rotate"', $html);
        $this->assertStringContainsString('data-scroll-video="playback"', $html);
    }
}

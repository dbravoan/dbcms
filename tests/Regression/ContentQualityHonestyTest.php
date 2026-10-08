<?php

declare(strict_types=1);

use App\Services\Seo\ContentQualityAuditorService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use PHPUnit\Framework\TestCase;

final class ContentQualityHonestyTest extends TestCase
{
    public function test_legacy_scores_are_labelled_as_editorial_estimates_without_observed_citations(): void
    {
        $audit = (new ContentQualityAuditorService)->audit('Título público', '<h2>Qué es</h2><p>Información verificable.</p>');
        self::assertSame('editorial_heuristic', $audit['score_kind']);
        self::assertFalse($audit['metrics']['citations_measured']);
        self::assertStringContainsString('heuristic', $audit['metrics']['llm_citability_index_label']);
        self::assertArrayHasKey('llm_citability_index', $audit['metrics']);
        self::assertStringNotContainsString('rank better', implode(' ', $audit['recommendations']));
    }

    public function test_drawer_translations_do_not_promise_perfect_scores_or_external_citability(): void
    {
        $loader = new FileLoader(new Filesystem, dirname(__DIR__, 2).'/lang');
        foreach (['es', 'en', 'ca'] as $locale) {
            $translator = new Translator($loader, $locale);
            $translator->setFallback('es');
            foreach (['tips_desc', 'excellent_desc'] as $key) {
                $message = $translator->get('admin.front_bar.'.$key);
                self::assertIsString($message);
                self::assertStringContainsString('editorial', $message);
                foreach (['100%', 'ChatGPT', 'Gemini', 'Perplexity'] as $claim) {
                    self::assertStringNotContainsString($claim, $message);
                }
            }
            self::assertStringNotContainsString('admin.front_bar.', $translator->get('admin.front_bar.heuristic_notice'));
        }
    }
}

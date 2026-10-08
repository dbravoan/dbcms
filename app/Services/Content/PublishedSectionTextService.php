<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Contracts\Services\PublishedSectionTextInterface;
use App\Core\Content\PublicSemanticRegistry;
use App\Core\Sections\SectionLayout;
use App\Models\Market;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** One extraction contract for public GEO and indexed authored block text. */
final class PublishedSectionTextService implements PublishedSectionTextInterface
{
    public function __construct(private readonly PublicSemanticRegistry $widgets) {}

    public function text(Model $entity, Market $market, string $locale, bool $includeWidgets = true): string
    {
        if (! method_exists($entity, 'publishedSections')) {
            return '';
        }
        $entity->loadMissing('publishedSections.translations');
        $sections = $entity->getRelationValue('publishedSections');
        if (! is_iterable($sections)) {
            return '';
        }
        $output = '';
        foreach ($sections as $section) {
            if (! $section instanceof Model) {
                continue;
            }
            $translations = $section->getRelationValue('translations');
            $translation = $translations instanceof Collection ? $translations->firstWhere('locale', $locale) : null;
            $payload = SectionLayout::resolve((array) $section->getAttribute('styling_json'), $translation instanceof Model ? (array) $translation->getAttribute('content_json') : []);
            $body = SectionLayout::plainText($payload, ['title', 'headline', 'content', 'body', 'summary', 'subtitle', 'description', 'question', 'answer', 'caption', 'alt', 'label', 'value']);
            if ($includeWidgets) {
                $body .= $this->widgetTexts($payload, $market, $locale);
            }
            if ($body !== '') {
                $output .= "### Published section\n{$body}\n\n";
            }
        }

        return $output;
    }

    /** @param array<string,mixed> $payload */
    private function widgetTexts(array $payload, Market $market, string $locale): string
    {
        $text = $this->widgets->widgetText($payload, $market, $locale);
        foreach ($payload as $value) {
            if (! is_array($value)) {
                continue;
            }
            if (array_is_list($value)) {
                foreach ($value as $child) {
                    if (is_array($child)) {
                        $text .= $this->widgetTexts($child, $market, $locale);
                    }
                }
            } else {
                $text .= $this->widgetTexts($value, $market, $locale);
            }
        }

        return $text;
    }
}

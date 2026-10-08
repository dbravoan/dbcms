<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Contracts;

interface AiBrandProfileServiceInterface
{
    /**
     * Retrieves the complete brand and editorial persona profile for this instance.
     *
     * @return array{
     *     brand_name: string,
     *     brand_tagline: string,
     *     company_name: string,
     *     brand_archetype: string,
     *     brand_treatment: string,
     *     brand_target_audience: string,
     *     brand_value_proposition: string,
     *     brand_preferred_terms: string,
     *     brand_taboo_terms: string,
     *     brand_cta_goals: string,
     *     brand_custom_directives: string
     * }
     */
    public function getProfile(): array;

    /**
     * Persists brand profile parameters to module settings.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveProfile(array $data): void;

    /**
     * Returns the catalog of predefined industry presets.
     *
     * @return array<string, array{
     *     name: string,
     *     description: string,
     *     icon: string,
     *     brand_archetype: string,
     *     brand_treatment: string,
     *     brand_target_audience: string,
     *     brand_value_proposition: string,
     *     brand_preferred_terms: string,
     *     brand_taboo_terms: string,
     *     brand_cta_goals: string,
     *     brand_custom_directives: string
     * }>
     */
    public function getPresets(): array;

    /**
     * Applies a predefined industry preset to this instance.
     */
    public function applyPreset(string $presetKey): bool;

    /**
     * Compiles the brand profile into an authoritative prompt section.
     */
    public function buildBrandPromptSegment(string $locale = 'es'): string;
}

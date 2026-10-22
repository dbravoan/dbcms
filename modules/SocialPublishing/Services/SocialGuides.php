<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Services;

use App\DTOs\Integrations\GuideStep;
use App\DTOs\Integrations\SetupGuideDescriptor;

final class SocialGuides
{
    /** @return list<SetupGuideDescriptor> */
    public function all(): array
    {
        $raw = file_get_contents(__DIR__.'/../Resources/guides/catalog.json');
        if ($raw === false) {
            throw new \RuntimeException('Los tutoriales locales no están disponibles.');
        }
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $guides = [];
        foreach ($data as $item) {
            $steps = [];
            foreach ($item['steps'] as $step) {
                $steps[] = new GuideStep($step['id'], $step['title'], $step['externalAction'], $step['cmsAction'], $step['expectedResult'], $step['links'], $step['callbackRoute'] ?? null, $step['callbackParameters'] ?? []);
            }
            $guides[] = new SetupGuideDescriptor($item['id'], $item['platform'], $item['title'], $item['prerequisites'], $steps, $item['troubleshooting'], $item['officialSources'], $item['reviewedAt'], $item['version']);
        }

        return $guides;
    }
}

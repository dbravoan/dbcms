<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Services;

use Modules\ContentAudit\Contracts\Services\DiffEngineServiceInterface;
use Modules\ContentAudit\DTOs\DiffResultDTO;

class DiffEngineService implements DiffEngineServiceInterface
{
    /**
     * @param  array<string, mixed>  $oldSnapshot
     * @param  array<string, mixed>  $newSnapshot
     */
    public function computeDiff(array $oldSnapshot, array $newSnapshot): DiffResultDTO
    {
        $fieldDiffs = [];
        $textDiffs = [];
        $hasChanges = false;

        // 1. Compare top-level attributes
        $keys = array_unique(array_merge(array_keys($oldSnapshot), array_keys($newSnapshot)));
        foreach ($keys as $key) {
            if ($key === 'translations' || $key === 'id') {
                continue;
            }

            $oldVal = $oldSnapshot[$key] ?? null;
            $newVal = $newSnapshot[$key] ?? null;

            if ($oldVal !== $newVal) {
                $fieldDiffs[$key] = [
                    'before' => $oldVal,
                    'after' => $newVal,
                ];
                $hasChanges = true;
            }
        }

        // 2. Compare translations
        $oldTrans = (array) ($oldSnapshot['translations'] ?? []);
        $newTrans = (array) ($newSnapshot['translations'] ?? []);
        $locales = array_unique(array_merge(array_keys($oldTrans), array_keys($newTrans)));

        foreach ($locales as $loc) {
            $oldFields = (array) ($oldTrans[$loc] ?? []);
            $newFields = (array) ($newTrans[$loc] ?? []);
            $fields = array_unique(array_merge(array_keys($oldFields), array_keys($newFields)));

            foreach ($fields as $f) {
                $o = (string) ($oldFields[$f] ?? '');
                $n = (string) ($newFields[$f] ?? '');

                if ($o !== $n) {
                    $compositeKey = "{$loc}.{$f}";
                    $fieldDiffs[$compositeKey] = [
                        'before' => $o,
                        'after' => $n,
                    ];
                    $hasChanges = true;

                    // If it's a long text field like content or summary, compute line diff
                    if ($f === 'content' || strlen($o) > 80 || strlen($n) > 80) {
                        $textDiffs[$compositeKey] = $this->computeTextDiff($o, $n);
                    }
                }
            }
        }

        return new DiffResultDTO($fieldDiffs, $textDiffs, $hasChanges);
    }

    /**
     * Compute line-by-line diff between two text strings.
     *
     * @return array<int, array{type: string, content: string}>
     */
    public function computeTextDiff(string $oldText, string $newText): array
    {
        $oldLines = preg_split("/\r\n|\n|\r/", $oldText) ?: [];
        $newLines = preg_split("/\r\n|\n|\r/", $newText) ?: [];

        $diff = [];
        $max = max(count($oldLines), count($newLines));

        for ($i = 0; $i < $max; $i++) {
            $hasOld = isset($oldLines[$i]);
            $hasNew = isset($newLines[$i]);

            if ($hasOld && $hasNew) {
                if ($oldLines[$i] === $newLines[$i]) {
                    $diff[] = ['type' => 'unchanged', 'content' => $oldLines[$i]];
                } else {
                    $diff[] = ['type' => 'removed', 'content' => $oldLines[$i]];
                    $diff[] = ['type' => 'added', 'content' => $newLines[$i]];
                }
            } elseif ($hasOld) {
                $diff[] = ['type' => 'removed', 'content' => $oldLines[$i]];
            } elseif ($hasNew) {
                $diff[] = ['type' => 'added', 'content' => $newLines[$i]];
            }
        }

        return $diff;
    }
}

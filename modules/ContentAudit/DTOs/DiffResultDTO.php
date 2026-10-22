<?php

declare(strict_types=1);

namespace Modules\ContentAudit\DTOs;

readonly class DiffResultDTO
{
    /**
     * @param  array<string, array{before: mixed, after: mixed}>  $fieldDiffs
     * @param  array<string, array<int, array{type: string, content: string}>>  $textDiffs
     */
    public function __construct(
        public array $fieldDiffs,
        public array $textDiffs = [],
        public bool $hasChanges = false
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'has_changes' => $this->hasChanges,
            'field_diffs' => $this->fieldDiffs,
            'text_diffs' => $this->textDiffs,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Forms\DTOs;

use App\Models\Language;
use App\Models\Market;
use Illuminate\Database\Eloquent\Collection;
use Modules\Forms\Models\CustomForm;

final readonly class FormEditorData
{
    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, array<string, string>>  $translationsMap
     * @param  Collection<int, Language>  $languages
     * @param  Collection<int, Market>  $markets
     * @param  list<int>  $assignedMarketIds
     * @param  list<array<string, mixed>>  $fieldTypes
     */
    public function __construct(
        public CustomForm $form,
        public array $fields,
        public array $translationsMap,
        public Collection $languages,
        public Collection $markets,
        public array $assignedMarketIds,
        public array $fieldTypes,
    ) {}

    /** @return array<string, mixed> */
    public function viewData(): array
    {
        return get_object_vars($this);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Pages\Contracts;

use App\Models\Market;
use App\Models\User;
use Modules\Pages\Models\Page;

interface PageDomainServiceInterface
{
    /** @param array<string,mixed> $data */
    public function createPage(array $data, ?User $actor = null): Page;

    /** @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function updatePage(Page $page, array $data): array;

    /** @return array<string,mixed> */
    public function editData(Page $page): array;

    public function deletePage(Page $page): void;

    public function resolvePageBySlug(string $slug, ?Market $market = null, ?string $locale = null): ?Page;
}

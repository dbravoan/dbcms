<?php

declare(strict_types=1);

namespace Modules\Gallery\Contracts;

use Modules\Gallery\Models\Gallery;

interface GalleryEditorialServiceInterface
{
    /** @param array<string,mixed> $input */
    public function create(array $input): Gallery;

    /** @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function update(Gallery $gallery, array $input): array;

    public function delete(Gallery $gallery): void;

    /** @return array<string,mixed> */
    public function editData(Gallery $gallery): array;
}

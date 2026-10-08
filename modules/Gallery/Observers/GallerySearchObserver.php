<?php

declare(strict_types=1);

namespace Modules\Gallery\Observers;

use App\Core\Search\SearchEntityObserver;
use Illuminate\Database\Eloquent\Model;
use Modules\Gallery\Models\Gallery;
use Modules\Gallery\Models\GalleryItem;
use Modules\Gallery\Models\GalleryTranslation;

final class GallerySearchObserver extends SearchEntityObserver
{
    protected function type(): string
    {
        return 'gallery';
    }

    protected function entityId(Model $model): int|string|null
    {
        $attributes = $model->getAttributes();
        $id = match (true) {
            $model instanceof Gallery => $attributes[$model->getKeyName()] ?? null,
            $model instanceof GalleryTranslation, $model instanceof GalleryItem => $attributes['gallery_id'] ?? null,
            default => null,
        };

        return is_int($id) || is_string($id) ? $id : null;
    }
}

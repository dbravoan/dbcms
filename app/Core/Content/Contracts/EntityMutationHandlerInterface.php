<?php

declare(strict_types=1);

namespace App\Core\Content\Contracts;

use Illuminate\Database\Eloquent\Model;

interface EntityMutationHandlerInterface
{
    /** @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public function prepare(Model $entity, array $args, bool $creating): array;
}

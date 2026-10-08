<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Admin\DatatableQueryData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

interface DatatableQueryServiceInterface
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<int, string>  $searchColumns
     * @param  array<string, string>  $filterableColumns
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginate(Builder $query, DatatableQueryData $input, array $searchColumns = [], array $filterableColumns = [], string $defaultSort = 'id', string $defaultDir = 'desc'): LengthAwarePaginator;
}

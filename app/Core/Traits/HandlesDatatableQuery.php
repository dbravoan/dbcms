<?php

declare(strict_types=1);

namespace App\Core\Traits;

use App\Contracts\Services\DatatableQueryServiceInterface;
use App\DTOs\Admin\DatatableQueryData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait HandlesDatatableQuery
{
    /**
     * Apply standardized live search, filters, column sorting, and pagination to an Eloquent query.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<int, string>  $searchColumns  Columns to search (supports relations like 'translations.title')
     * @param  array<string, string>  $filterableColumns  Map of request param => database column
     * @return LengthAwarePaginator<int, TModel>
     */
    protected function applyDatatable(
        Builder $query,
        Request $request,
        array $searchColumns = [],
        array $filterableColumns = [],
        string $defaultSort = 'id',
        string $defaultDir = 'desc'
    ): LengthAwarePaginator {
        return app(DatatableQueryServiceInterface::class)->paginate(
            $query, DatatableQueryData::fromInput($request->all()),
            $searchColumns, $filterableColumns, $defaultSort, $defaultDir
        );
    }
}

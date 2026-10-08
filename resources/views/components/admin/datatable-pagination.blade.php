@props([
    'paginator' => null,
])

@if($paginator && $paginator->total() > 0)
    <div class="admin-pagination px-6 py-4 bg-[#121212] border-t border-gray-800 font-mono flex flex-col sm:flex-row items-center justify-between gap-4 text-xs">
        <div class="text-gray-400">
            {{ __('admin.table.showing', [
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
                'total' => $paginator->total(),
            ]) }}
        </div>

        <div class="flex items-center space-x-1">
            {{-- Previous Page Link --}}
            @if($paginator->onFirstPage())
                <span class="px-3 py-1.5 rounded bg-gray-800/40 text-gray-600 cursor-not-allowed">&laquo;</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="admin-pagination-item px-3 py-1.5 rounded bg-[#1A1A1A] hover:bg-gray-800 border border-gray-700 text-gray-300 hover:text-white transition-colors">&laquo;</a>
            @endif

            {{-- Pagination Elements --}}
            @foreach($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
                @if($page == $paginator->currentPage())
                    <span class="admin-pagination-active px-3 py-1.5 rounded bg-[#00FF99]/10 border border-[#00FF99] text-[#00FF99] font-bold">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" class="admin-pagination-item px-3 py-1.5 rounded bg-[#1A1A1A] hover:bg-gray-800 border border-gray-700 text-gray-300 hover:text-white transition-colors">{{ $page }}</a>
                @endif
            @endforeach

            {{-- Next Page Link --}}
            @if($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="admin-pagination-item px-3 py-1.5 rounded bg-[#1A1A1A] hover:bg-gray-800 border border-gray-700 text-gray-300 hover:text-white transition-colors">&raquo;</a>
            @else
                <span class="px-3 py-1.5 rounded bg-gray-800/40 text-gray-600 cursor-not-allowed">&raquo;</span>
            @endif
        </div>
    </div>
@endif

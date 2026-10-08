@extends('admin.layout')

@section('title', __('admin.page_titles.shop_orders'))

@section('content')
<div class="space-y-6 font-mono text-xs">

    <!-- Unified Datatable Header with Search & Filters -->
    <x-admin.datatable-header
        title="{{ __('admin.orders.title') }}"
        description="{{ __('admin.orders.desc') }}"
        searchPlaceholder="Buscar por número de pedido, cliente o email..."
    >
        <x-slot:actions>
            <a href="{{ route('admin.shop.products.index') }}" class="px-3.5 py-2 bg-[#1A1A1A] hover:bg-gray-800 text-gray-300 rounded-lg border border-gray-700 text-xs flex items-center gap-1.5 transition-colors">
                <span>&larr;</span>
                <span>{{ __('admin.orders.back_to_catalog') }}</span>
            </a>
        </x-slot:actions>

        <!-- Filter: Order Status -->
        <select name="status" class="bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs">
            <option value="all">{{ __('admin.orders.all_statuses') }}</option>
            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ __('admin.orders.pending') }}</option>
            <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>{{ __('admin.orders.processing') }}</option>
            <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>{{ __('admin.orders.completed') }}</option>
            <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ __('admin.orders.cancelled') }}</option>
        </select>

        <!-- Filter: Payment Status -->
        <select name="payment_status" class="bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs">
            <option value="all">{{ __('admin.orders.col_payment_status') }}</option>
            <option value="paid" {{ request('payment_status') === 'paid' ? 'selected' : '' }}>{{ __('admin.orders.paid') }}</option>
            <option value="unpaid" {{ request('payment_status') === 'unpaid' ? 'selected' : '' }}>{{ __('admin.orders.unpaid') }}</option>
        </select>
    </x-admin.datatable-header>

    <!-- Orders Datatable Card -->
    <x-admin.table
        :empty="$orders->isEmpty()"
        emptyTitle="{{ __('admin.orders.empty_title') }}"
        emptyDescription="{{ __('admin.orders.empty_desc') }}"
    >
        <x-slot:header>
            <th class="py-3 px-2 md:hidden w-8"></th>
            <x-admin.sortable-th column="order_number" label="{{ __('admin.orders.col_order') }}" />
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400">{{ __('admin.orders.col_customer') }}</th>
            <x-admin.sortable-th column="created_at" label="{{ __('admin.listing.date') }}" class="hidden lg:table-cell" />
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400 hidden xl:table-cell">{{ __('admin.orders.col_items') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400">{{ __('admin.orders.col_total') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400 hidden sm:table-cell">{{ __('admin.orders.col_payment') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400 hidden md:table-cell">{{ __('admin.listing.status') }}</th>
            <th class="py-3 px-4 text-right font-bold uppercase tracking-wider text-gray-400">{{ __('admin.listing.actions') }}</th>
        </x-slot:header>

        @foreach($orders as $ord)
            <tr class="hover:bg-gray-800/30 transition-colors">
                <td class="py-3.5 px-2 md:hidden text-center w-8">
                    <button type="button" @click="expanded = expanded === $el.closest('tr') ? null : $el.closest('tr')" class="text-gray-400 hover:text-white p-1 focus:outline-none" aria-label="{{ __('admin.orders.view_details') }}">
                        <i class="fa-solid text-[10px]" :class="expanded === $el.closest('tr') ? 'fa-chevron-up text-[#00FF99]' : 'fa-chevron-down'"></i>
                    </button>
                </td>
                <td class="py-3.5 px-4 font-bold text-[#00FF99]">
                    <a href="{{ route('admin.shop.orders.show', $ord) }}" class="hover:underline">
                        {{ $ord->order_number }}
                    </a>
                </td>
                <td class="py-3.5 px-4">
                    <div class="font-bold text-white">{{ $ord->customer_name }}</div>
                    <div class="text-[11px] text-gray-500">{{ $ord->customer_email }}</div>
                </td>
                <td class="py-3.5 px-4 text-gray-400 whitespace-nowrap hidden lg:table-cell">{{ $ord->created_at?->format('Y-m-d H:i') }}</td>
                <td class="py-3.5 px-4 text-gray-300 hidden xl:table-cell">
                    <x-admin.chip>
                        {{ $ord->items->sum('quantity') }} unid.
                    </x-admin.chip>
                </td>
                <td class="py-3.5 px-4 font-bold text-white">{{ $ord->getFormattedGrandTotal() }}</td>
                <td class="py-3.5 px-4 hidden sm:table-cell">
                    <x-admin.status-badge :status="$ord->payment_status" />
                </td>
                <td class="py-3.5 px-4 hidden md:table-cell">
                    <x-admin.status-badge :status="$ord->status" />
                </td>
                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                    <div class="inline-flex items-center justify-end gap-1.5">
                        <x-admin.action-button
                            as="a"
                            href="{{ route('admin.shop.orders.show', $ord) }}"
                            icon="fa-solid fa-eye"
                            tooltip="Ver detalle del pedido"
                            variant="primary"
                        />
                    </div>
                </td>
            </tr>

            <x-admin.table-row-detail colSpan="9">
                <x-admin.table-row-detail-item label="{{ __('admin.orders.col_date') }}">
                    <span class="text-gray-300">{{ $ord->created_at?->format('Y-m-d H:i') }}</span>
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.orders.col_items') }}">
                    <x-admin.chip>{{ $ord->items->sum('quantity') }} unidades</x-admin.chip>
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.orders.col_payment_status') }}">
                    <x-admin.status-badge :status="$ord->payment_status" />
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.orders.col_order_status') }}">
                    <x-admin.status-badge :status="$ord->status" />
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.orders.col_email') }}">
                    <span class="text-gray-400">{{ $ord->customer_email }}</span>
                </x-admin.table-row-detail-item>
            </x-admin.table-row-detail>
        @endforeach

        <x-slot:pagination>
            <x-admin.datatable-pagination :paginator="$orders" />
        </x-slot:pagination>
    </x-admin.table>

</div>
@endsection

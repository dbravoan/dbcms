@extends('admin.layout')

@section('title', __('admin.page_titles.shop_order') . ' ' . $order->order_number)

@section('content')
<div class="admin-page space-y-6">

    <div class="flex items-center justify-between pb-4 border-b border-[color:var(--admin-border)]">
        <div>
            <h2 class="text-xl font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                <span class="text-[color:var(--admin-accent)]">&gt;</span>
                <span>Detalle de Pedido: {{ $order->order_number }}</span>
            </h2>
            <p class="text-[color:var(--admin-muted)] text-xs mt-1">Registrado el {{ $order->created_at?->format('d/m/Y a las H:i') }}</p>
        </div>
        <a href="{{ route('admin.shop.orders.index') }}" class="text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] text-xs font-mono">
            &larr; Volver a pedidos
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Line items table & Totals (2 cols) -->
        <div class="lg:col-span-2 space-y-6">
            <x-admin.form-card class="overflow-hidden">
                <div class="p-4 bg-[color:var(--admin-surface)] border-b border-[color:var(--admin-border)] font-bold text-[color:var(--admin-text)] text-xs uppercase">
                    &gt; Artículos del Pedido
                </div>
                <table class="w-full text-left font-mono text-xs">
                    <thead class="bg-[color:var(--admin-surface)] border-b border-[color:var(--admin-border)] text-[color:var(--admin-muted)] uppercase text-[10px]">
                        <tr>
                            <th class="p-3.5">{{ __('admin.orders.product') }}</th>
                            <th class="p-3.5">{{ __('admin.orders.sku') }}</th>
                            <th class="p-3.5 text-center">{{ __('admin.orders.qty') }}</th>
                            <th class="p-3.5 text-right">{{ __('admin.orders.unit_price') }}</th>
                            <th class="p-3.5 text-right">{{ __('admin.orders.col_total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[color:var(--admin-border)]">
                        @foreach($order->items as $item)
                            <tr>
                                <td class="p-3.5 font-bold text-[color:var(--admin-text)]">{{ $item->product_name }}</td>
                                <td class="p-3.5 text-[color:var(--admin-muted)]">{{ $item->sku ?? '-' }}</td>
                                <td class="p-3.5 text-center font-bold">{{ $item->quantity }}</td>
                                <td class="p-3.5 text-right">{{ number_format($item->unit_price, 2) }} {{ $order->currency }}</td>
                                <td class="p-3.5 text-right font-bold text-[color:var(--admin-accent)]">{{ number_format($item->total, 2) }} {{ $order->currency }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="p-5 bg-[color:var(--admin-surface)] border-t border-[color:var(--admin-border)] space-y-2 text-xs font-mono">
                    <div class="flex justify-between text-[color:var(--admin-muted)]">
                        <span>{{ __('admin.orders.subtotal') }}</span>
                        <span>{{ number_format($order->subtotal, 2) }} {{ $order->currency }}</span>
                    </div>
                    <div class="flex justify-between text-[color:var(--admin-muted)]">
                        <span>{{ __('admin.orders.taxes') }}</span>
                        <span>{{ number_format($order->tax_total, 2) }} {{ $order->currency }}</span>
                    </div>
                    @if($order->shipping_total > 0)
                    <div class="flex justify-between text-[color:var(--admin-muted)]">
                        <span>{{ __('admin.orders.shipping') }}</span>
                        <span>{{ number_format($order->shipping_total, 2) }} {{ $order->currency }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between text-[color:var(--admin-text)] font-bold text-sm pt-2 border-t border-[color:var(--admin-border)]">
                        <span>{{ __('admin.orders.total_order') }}</span>
                        <span class="text-[color:var(--admin-accent)]">{{ $order->getFormattedGrandTotal() }}</span>
                    </div>
                </div>
            </x-admin.form-card>

            <!-- Customer & Shipping Details -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <x-admin.form-card class="space-y-3 text-xs font-mono">
                    <h4 class="font-bold text-[color:var(--admin-text)] uppercase text-xs pb-2 border-b border-[color:var(--admin-border)]">
                        &gt; Datos del Cliente
                    </h4>
                    <div>
                        <span class="text-[color:var(--admin-muted)]">{{ __('admin.orders.name') }}</span>
                        <span class="text-[color:var(--admin-text)] font-bold block">{{ $order->customer_name }}</span>
                    </div>
                    <div>
                        <span class="text-[color:var(--admin-muted)]">{{ __('admin.orders.email') }}</span>
                        <span class="text-[color:var(--admin-text)] block">{{ $order->customer_email }}</span>
                    </div>
                    <div>
                        <span class="text-[color:var(--admin-muted)]">{{ __('admin.orders.phone') }}</span>
                        <span class="text-[color:var(--admin-text)] block">{{ $order->customer_phone ?? 'No especificado' }}</span>
                    </div>
                </x-admin.form-card>

                <x-admin.form-card class="space-y-3 text-xs font-mono">
                    <h4 class="font-bold text-[color:var(--admin-text)] uppercase text-xs pb-2 border-b border-[color:var(--admin-border)]">
                        &gt; Dirección de Envío
                    </h4>
                    @if(!empty($order->shipping_address))
                        <div class="text-[color:var(--admin-text)] space-y-1">
                            <div>{{ $order->shipping_address['address'] ?? '' }}</div>
                            <div>{{ $order->shipping_address['city'] ?? '' }}, {{ $order->shipping_address['postal_code'] ?? '' }}</div>
                            <div>{{ $order->shipping_address['country'] ?? '' }}</div>
                        </div>
                    @else
                        <div class="text-[color:var(--admin-muted)]">{{ __('admin.orders.no_shipping') }}</div>
                    @endif
                </x-admin.form-card>
            </div>
        </div>

        <!-- Status Management Action Card (1 col) -->
        <div class="space-y-6 font-mono text-xs">
            <x-admin.form-card class="space-y-4">
                <h4 class="font-bold text-[color:var(--admin-text)] uppercase text-xs pb-2 border-b border-[color:var(--admin-border)]">
                    &gt; Gestión de Estado
                </h4>

                <form method="POST" action="{{ route('admin.shop.orders.update-status', $order) }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">{{ __('admin.orders.order_status') }}</label>
                        <select name="status" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] font-bold">
                            <option value="pending" {{ $order->status === 'pending' ? 'selected' : '' }}>{{ __('admin.orders.pending') }}</option>
                            <option value="processing" {{ $order->status === 'processing' ? 'selected' : '' }}>{{ __('admin.orders.processing') }}</option>
                            <option value="completed" {{ $order->status === 'completed' ? 'selected' : '' }}>{{ __('admin.orders.completed') }}</option>
                            <option value="cancelled" {{ $order->status === 'cancelled' ? 'selected' : '' }}>{{ __('admin.orders.cancelled') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">{{ __('admin.orders.payment_status') }}</label>
                        <select name="payment_status" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]">
                            <option value="unpaid" {{ $order->payment_status === 'unpaid' ? 'selected' : '' }}>{{ __('admin.orders.unpaid') }}</option>
                            <option value="paid" {{ $order->payment_status === 'paid' ? 'selected' : '' }}>{{ __('admin.orders.paid') }}</option>
                            <option value="refunded" {{ $order->payment_status === 'refunded' ? 'selected' : '' }}>{{ __('admin.orders.refunded') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">{{ __('admin.orders.internal_notes') }}</label>
                        <textarea name="notes" rows="3" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]" placeholder="{{ __('admin.orders.notes_placeholder') }}">{{ $order->notes }}</textarea>
                    </div>

                    <x-admin.button variant="primary" type="submit" class="w-full py-2.5">Actualizar Pedido &rarr;</x-admin.button>
                </form>
            </x-admin.form-card>
        </div>

    </div>

</div>
@endsection

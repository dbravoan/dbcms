@extends('admin.layout')
@section('title', __('Conciliación de pagos históricos'))
@section('content')
<div class="space-y-6">
    <x-admin.datatable-header :title="__('Conciliación de pagos históricos')" :description="__('El antiguo simulador marcaba tarjeta/Stripe como pagado sin evidencia de cobro. Este informe no modifica pedidos; comprueba los registros de caja antes de conciliar.')" />
    <x-admin.table :empty="$orders->isEmpty()" :emptyTitle="__('No hay pedidos pendientes de comprobar')">
        <x-slot:header><th>{{ __('Pedido') }}</th><th>{{ __('Método') }}</th><th>{{ __('Importe') }}</th></x-slot:header>
        @foreach($orders as $order)
            <tr><td class="p-4"><a href="{{ route('admin.shop.orders.show', $order) }}">{{ $order->order_number }}</a></td><td class="p-4">{{ $order->payment_method }}</td><td class="p-4">{{ $order->getFormattedGrandTotal() }}</td></tr>
        @endforeach
    </x-admin.table>
    {{ $orders->links() }}
</div>
@endsection

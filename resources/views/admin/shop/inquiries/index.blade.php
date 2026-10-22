@extends('admin.layout')
@section('title', __('Solicitudes de presupuesto'))
@section('content')
<div class="admin-page space-y-6">
    <x-admin.datatable-header :title="__('Solicitudes de presupuesto')" :description="__('Contactos recibidos del catálogo.')" />
    <x-admin.table :empty="$inquiries->isEmpty()" :emptyTitle="__('No hay solicitudes')">
        <x-slot:header>
            <th>{{ __('Contacto') }}</th><th>{{ __('Mensaje') }}</th><th>{{ __('Estado') }}</th>
        </x-slot:header>
        @foreach($inquiries as $inquiry)
            <tr>
                <td class="p-4">{{ $inquiry->name }}<br>{{ $inquiry->email }}<br>{{ $inquiry->phone }}<br>{{ $inquiry->created_at }}</td>
                <td class="p-4 whitespace-pre-wrap">{{ $inquiry->message }}<br>{{ __('Producto') }} #{{ $inquiry->product_id }} · {{ $inquiry->locale }}</td>
                <td class="p-4">
                    @if($inquiry->is_reviewed)
                        {{ __('Revisada') }}
                    @else
                        <form method="POST" action="{{ route('admin.shop.inquiries.review', $inquiry) }}">
                            @csrf @method('PUT')
                            <x-admin.action-button type="submit" :tooltip="__('Marcar revisada')" icon="fa-solid fa-check" />
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-admin.table>
    {{ $inquiries->links() }}
</div>
@endsection

@extends('admin.layout')

@section('content')
<x-admin.form-card title="Selecciona la cuenta autorizada" :subtitle="'El proveedor '.$provider.' ha devuelto estas cuentas para tu autorización.'">
    <form method="POST" action="{{ route('admin.social-publishing.oauth.select') }}" class="space-y-4">

@csrf

<input type="hidden" name="state_id" value="{{ $stateId }}" />
        <x-admin.select name="remote_id" label="Cuenta o canal" required>

@foreach($accounts as $account)<option value="{{ $account['id'] }}">{{ $account['label'] }} · {{ $account['id'] }}</option>

@endforeach

</x-admin.select>
        <x-admin.form-actions :cancelRoute="route('admin.social-publishing.index')" submitLabel="Usar esta cuenta" />
    </form>
</x-admin.form-card>

@endsection


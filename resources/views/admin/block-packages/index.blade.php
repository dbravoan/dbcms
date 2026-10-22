@extends('admin.layout')
@section('title', 'Paquetes de bloques')
@section('content')
<div class="admin-page space-y-6">
    <x-admin.form-card title="Añadir un paquete de bloques" subtitle="Instala paquetes ZIP de proveedores de confianza. Se añaden desactivados para que puedas revisarlos.">
        <form action="{{ route('admin.block-packages.upload') }}" method="POST" enctype="multipart/form-data" class="flex flex-wrap items-end gap-4">
            @csrf
            <label class="space-y-2"><span class="block text-sm">Archivo ZIP</span><input type="file" name="package_zip" accept=".zip" required></label>
            <x-admin.button type="submit">Instalar paquete</x-admin.button>
        </form>
        @error('package_zip')<p role="alert" class="text-red-500">{{ $message }}</p>@enderror
    </x-admin.form-card>
    <x-admin.table :empty="count($packages) === 0" empty-title="No hay paquetes instalados">
        <x-slot:header><th class="p-4">Paquete</th><th class="p-4">Estado</th><th class="p-4">Acciones</th></x-slot:header>
        @foreach($packages as $identifier => $package)
            @php($enabled = $package['enabled'])
            <tr>
                <td class="p-4"><strong>{{ $package['name'] }}</strong><p class="text-xs opacity-70">{{ $package['version'] }} · {{ $package['bundled'] ? 'Incluido con el CMS' : 'Instalado desde ZIP' }}</p></td>
                <td class="p-4">{{ $enabled ? 'Activo' : 'Oculto' }}</td>
                <td class="p-4 flex flex-wrap gap-3">
                    <form action="{{ route('admin.block-packages.toggle', $identifier) }}" method="POST">@csrf<input type="hidden" name="enabled" value="{{ $enabled ? 0 : 1 }}"><x-admin.button type="submit" variant="secondary">{{ $enabled ? 'Ocultar' : 'Activar' }}</x-admin.button></form>
                    @if(!$package['bundled'] && !$enabled)
                        <form action="{{ route('admin.block-packages.destroy', $identifier) }}" method="POST">@csrf @method('DELETE')<x-admin.button type="submit" variant="danger">Desinstalar paquete</x-admin.button></form>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-admin.table>
    <p class="text-sm opacity-70">Ocultar un paquete mantiene sus bloques guardados y deja de mostrarlos en la web. Al reactivarlo vuelven a estar disponibles.</p>
</div>
@endsection

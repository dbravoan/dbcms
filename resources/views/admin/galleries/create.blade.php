@extends('admin.layout')

@section('title', __('admin.page_titles.gallery_create'))

@section('content')
<div class="admin-page space-y-6 font-mono text-xs">
    <x-admin.form-header
        title="Crear galería multimedia"
        description="Crea álbumes fotográficos con marcado Schema.org ImageGallery y soporte multi-mercado."
        :backRoute="route('admin.galleries.index')"
        backLabel="Volver al listado"
    />

    @include('admin.galleries._form', ['gallery' => null, 'markets' => $markets, 'languages' => $languages ?? []])
</div>
@endsection

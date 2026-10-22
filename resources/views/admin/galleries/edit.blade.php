@extends('admin.layout')

@php
    $liveUrl = !empty($gallery->slug)
        ? app(\App\Contracts\Services\LocalizationServiceInterface::class)->frontUrl('gallery', $gallery->slug)
        : null;
@endphp

@section('title', __('admin.page_titles.gallery_edit'))

@section('content')
<div class="admin-page space-y-6 font-mono text-xs">
    <x-admin.form-header
        :title="'Editar galería: ' . $gallery->title"
        description="Administra las fotos, reordena elementos y ajusta las traducciones del álbum."
        :backRoute="route('admin.galleries.index')"
        backLabel="Volver al listado"
        :liveUrl="$liveUrl"
        liveLabel="Ver en la web &nearr;"
    />

    @include('admin.galleries._form', ['gallery' => $gallery, 'markets' => $markets, 'languages' => $languages ?? []])
</div>
@endsection

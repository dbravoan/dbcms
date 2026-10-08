@extends('admin.layout')

@php
    $mainTrans = $post->translations->where('locale', 'es')->first() ?? $post->translations->first();
    $postTitle = $mainTrans?->title ?? 'Artículo #' . $post->id;
    $slug = $mainTrans?->slug ?? '';
    $liveUrl = $slug ? url('/blog/' . $slug) : null;
@endphp

@section('title', __('admin.page_titles.blog_post_edit'))

@section('content')
    @if($post->getAttributes()['pending_revision_id'] ?? null)
        <p class="mb-4 rounded border border-amber-500/40 p-3 text-sm text-amber-300">Estás editando una revisión pendiente. La versión publicada se conserva hasta aprobar estos cambios.</p>
    @endif
<div class="space-y-6 font-mono text-xs">
    <x-admin.form-header
        :title="'Editar artículo: ' . $postTitle"
        description="Actualiza el contenido técnico, ajustes multi-idioma, taxonomías y auditoría GEO."
        :backRoute="route('admin.blog.posts.index')"
        backLabel="Volver al listado"
        :liveUrl="$liveUrl"
        liveLabel="Ver en blog &nearr;"
    />

    @include('admin.blog.posts._form', [
        'post' => $post,
        'categories' => $categories,
        'markets' => $markets,
        'languages' => $languages ?? [],
    ])
</div>
@endsection

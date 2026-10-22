@extends('admin.layout')

@section('title', __('admin.page_titles.blog_post_create'))

@section('content')
<div class="admin-page space-y-6 font-mono text-xs">
    <x-admin.form-header
        title="Crear artículo del blog"
        description="Redacta contenido con bloques dinámicos y optimización para motores de búsqueda tradicionales y generativos (GEO)."
        :backRoute="route('admin.blog.posts.index')"
        backLabel="Volver al listado"
    />

    @include('admin.blog.posts._form', [
        'post' => null,
        'categories' => $categories,
        'markets' => $markets,
        'languages' => $languages ?? [],
    ])
</div>
@endsection

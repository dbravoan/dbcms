@extends('themes.db-editorial.layouts.app')

@section('title', $translation->meta_title ?? $translation->title ?? __('Página'))
@section('meta_description', $translation->meta_description ?? '')

@section('content')
<div class="space-y-12 pb-16" @if(!empty($page)) data-cms-edit-url="{{ route('admin.pages.edit', $page->id) }}" data-cms-edit-label="Editar Página" @endif>
    @if(isset($renderedSections) && count($renderedSections) > 0)
        @foreach($renderedSections as $sectionHtml)
            <div @if(!empty($page)) data-cms-edit-url="{{ route('admin.pages.edit', $page->id) }}" data-cms-edit-label="Editar Sección" @endif>
                {!! $sectionHtml !!}
            </div>
        @endforeach
    @else
        <div class="max-w-4xl mx-auto px-4 py-20 text-center editorial-card p-8 font-serif">
            <h1 class="text-3xl font-bold text-stone-900 mb-2">{{ $translation->title ?? __('Página') }}</h1>
            <p class="text-stone-500 text-sm">{{ __('Esta página aún no tiene secciones asignadas en el constructor.') }}</p>
        </div>
    @endif
</div>

<!-- Schema.org WebPage JSON-LD -->
@endsection

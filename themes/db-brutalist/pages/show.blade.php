@extends('themes.db-brutalist.layouts.app')

@section('title', $translation->meta_title ?? $translation->title ?? __('Página'))
@section('meta_description', $translation->meta_description ?? '')

@section('content')
<div class="space-y-16 pb-16" @if(!empty($page)) data-cms-edit-url="{{ route('admin.pages.edit', $page->id) }}" data-cms-edit-label="Editar Página" @endif>
    @if(isset($renderedSections) && count($renderedSections) > 0)
        @foreach($renderedSections as $sectionHtml)
            <div @if(!empty($page)) data-cms-edit-url="{{ route('admin.pages.edit', $page->id) }}" data-cms-edit-label="Editar Sección" @endif>
                {!! $sectionHtml !!}
            </div>
        @endforeach
    @else
        <div class="max-w-4xl mx-auto py-24 text-center brutalist-card p-12">
            <h1 class="text-3xl font-black uppercase text-black mb-3">{{ $translation->title ?? __('Página') }}</h1>
            <p class="text-black font-mono text-sm">{{ __('Esta página aún no dispone de bloques asignados.') }}</p>
        </div>
    @endif
</div>

<!-- Schema.org WebPage JSON-LD -->
@endsection

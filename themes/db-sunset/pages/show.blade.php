@extends('themes::db-sunset.layouts.app')

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
        <div class="max-w-4xl mx-auto py-24 text-center sunset-card p-12">
            <h1 class="text-3xl font-bold text-white mb-3">{{ $translation->title ?? __('Página') }}</h1>
            <p class="text-slate-400 text-sm">{{ __('Esta página aún no dispone de bloques maquetados.') }}</p>
        </div>
    @endif
</div>

<!-- Schema.org WebPage JSON-LD -->
@endsection

@extends('themes.db-enterprise.layouts.app')

@section('title', $translation->meta_title ?? $translation->title ?? __('Página'))
@section('meta_description', $translation->meta_description ?? '')

@section('content')
<div class="space-y-12 pb-20" @if(!empty($page)) data-cms-edit-url="{{ route('admin.pages.edit', $page->id) }}" data-cms-edit-label="Editar Página" @endif>
    @if(isset($renderedSections) && count($renderedSections) > 0)
        @foreach($renderedSections as $sectionHtml)
            <div @if(!empty($page)) data-cms-edit-url="{{ route('admin.pages.edit', $page->id) }}" data-cms-edit-label="Editar Sección" @endif>
                {!! $sectionHtml !!}
            </div>
        @endforeach
    @else
        <div class="max-w-4xl mx-auto px-4 py-20 text-center bg-slate-900 border border-slate-800 rounded-2xl p-8">
            <h1 class="text-2xl font-bold text-white mb-2">{{ $translation->title ?? __('Página') }}</h1>
            <p class="text-slate-400 text-sm">{{ __('Esta página aún no tiene secciones asignadas en el constructor.') }}</p>
        </div>
    @endif
</div>

<!-- Schema.org WebPage JSON-LD -->
@endsection

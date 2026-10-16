@extends('themes::ia-practica.layouts.app')

@section('title', $translation->meta_title ?? $translation->title ?? __('Página'))
@section('meta_description', $translation->meta_description ?? '')

@section('content')
<div class="ia-page {{ ($translation->slug ?? '') === 'home' ? 'ia-home' : '' }}" @if(!empty($page)) data-cms-edit-url="{{ route('admin.pages.edit', $page->id) }}" data-cms-edit-label="{{ __('Editar página') }}" @endif>
    @if(isset($renderedSections) && count($renderedSections) > 0)
        @foreach($renderedSections as $sectionHtml)
            <div class="ia-section">{!! $sectionHtml !!}</div>
        @endforeach
    @else
        <header class="ia-page-heading"><h1>{{ $translation->title ?? __('Página') }}</h1></header>
    @endif
</div>
@endsection

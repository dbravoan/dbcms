@extends('themes::ia-practica.layouts.app')
@php
    $catTrans = $category->translations->firstWhere('locale', app()->getLocale());
@endphp
@section('title', $catTrans?->name ?? __('Guías por departamento'))
@section('meta_description', $catTrans?->description ?? '')

@section('content')
<div class="ia-catalogue">
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])
    <header class="ia-page-heading">
        <a class="ia-eyebrow" href="{{ $localization->url('/blog', $currentMarket) }}">{{ __('Biblioteca') }} / {{ __('Por departamento') }}</a>
        <h1>{{ $catTrans?->name ?? __('Guías por departamento') }}</h1>
        @if($catTrans?->description)<p>{{ $catTrans->description }}</p>@endif
    </header>
    <x-cms-filter.bar :module="$filterModule ?? null" :set="$filterSet ?? null" :market="$currentMarket" />
    @include('themes::ia-practica.blog.cards')
</div>
@endsection

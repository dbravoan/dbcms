@extends('themes::ia-practica.layouts.app')

@section('title', __('Guías prácticas de IA para cada equipo'))
@section('meta_description', __('Guías paso a paso para aplicar inteligencia artificial en marketing, ventas, personas, operaciones, finanzas y tecnología, con revisión humana.'))

@section('content')
<div class="ia-catalogue">
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])
    <header class="ia-page-heading">
        <span class="ia-eyebrow">{{ __('La biblioteca de trabajo') }}</span>
        <h1>{{ __('Una tarea real.') }}<br><em>{{ __('Un siguiente paso claro.') }}</em></h1>
        <p>{{ __('Guías para entender qué delegar a una IA, cómo darle contexto y qué revisar antes de dar el trabajo por terminado.') }}</p>
    </header>
    <x-cms-filter.bar :module="$filterModule ?? null" :set="$filterSet ?? null" :market="$currentMarket" />
    @include('themes::ia-practica.blog.cards')
</div>
@endsection

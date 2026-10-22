@extends('themes::default-clean.layouts.app')

@section('title', $translation->meta_title ?? $translation->title ?? '')
@section('meta_description', $translation->meta_description ?? '')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8">
    @if(!empty($renderedSections))
        @foreach($renderedSections as $section)
            {!! $section !!}
        @endforeach
    @else
        <div class="text-center py-16 text-slate-500">
            <p>&gt; {{ __('Content in preparation') }}</p>
        </div>
    @endif
</div>
@endsection
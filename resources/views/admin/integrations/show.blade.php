@extends('admin.layout')

@section('title', $guide->title)

@section('content')
<div class="space-y-6">
    <x-admin.form-header :title="$guide->title" :description="__('integrations.metadata', ['platform' => $guide->platform, 'version' => $guide->version, 'date' => $guide->reviewedAt])" :back-route="route('admin.modules.guides.index', ['identifier' => $module])" :back-label="__('integrations.all_tutorials')" />
    <aside class="cyber-card p-5 space-y-2" aria-label="{{ __('integrations.progress') }}">
        <p class="font-bold text-white">{{ __('integrations.progress_count', ['completed' => count($completedSteps), 'total' => count($guide->steps)]) }}</p>
        <p class="text-sm text-gray-400">{{ __('integrations.progress_notice') }}</p>
        <progress class="w-full h-2" value="{{ count($completedSteps) }}" max="{{ count($guide->steps) }}" aria-label="{{ __('integrations.completed_steps') }}"></progress>
    </aside>
    @if($guide->prerequisites !== [])
        <section class="cyber-card p-6" aria-labelledby="guide-prerequisites">
            <h2 id="guide-prerequisites" class="text-base font-bold text-white mb-3">{{ __('integrations.prerequisites') }}</h2>
            <ul class="list-disc pl-5 space-y-2 text-sm text-gray-400">
                @foreach($guide->prerequisites as $prerequisite)<li>{{ $prerequisite }}</li>@endforeach
            </ul>
        </section>
    @endif
    <nav aria-label="{{ __('integrations.step_navigation') }}" class="flex flex-wrap gap-2">
        @foreach($guide->steps as $step)
            <x-admin.button :href="'#step-'.$step->id" size="sm">{{ $loop->iteration }}. {{ $step->title }}</x-admin.button>
        @endforeach
    </nav>
    <div class="space-y-5">
        @foreach($guide->steps as $step)
            <x-admin.integration-guide-step :step="$step" :module="$module" :guide-id="$guide->id" :number="$loop->iteration" :completed="in_array($step->id, $completedSteps, true)" :callback-url="$callbackUrls[$step->id] ?? null" :callback-eligible="array_key_exists($step->id, $callbackUrls)" :previous-step="$guide->steps[$loop->index - 1] ?? null" :next-step="$guide->steps[$loop->index + 1] ?? null" />
        @endforeach
    </div>
    @if($guide->troubleshooting !== [])
        <details class="cyber-card p-6">
            <summary class="font-bold text-white cursor-pointer">{{ __('integrations.troubleshooting') }}</summary>
            <ul class="list-disc pl-5 space-y-2 text-sm text-gray-400 mt-4">
                @foreach($guide->troubleshooting as $problem)<li>{{ $problem }}</li>@endforeach
            </ul>
        </details>
    @endif
    <section class="cyber-card p-6 space-y-3" aria-labelledby="guide-sources">
        <h2 id="guide-sources" class="font-bold text-white">{{ __('integrations.sources') }}</h2>
        <p class="text-sm text-gray-400">{{ __('integrations.sources_notice') }}</p>
        <div class="flex flex-wrap gap-2">
            @foreach($guide->officialSources as $label => $url)
                <x-admin.button :href="$url" target="_blank" rel="noopener noreferrer" size="sm">{{ $label }} ↗</x-admin.button>
            @endforeach
        </div>
    </section>
</div>
@endsection

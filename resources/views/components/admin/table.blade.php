@props([
    'hoverable' => true,
    'striped' => false,
    'empty' => false,
    'emptyTitle' => null,
    'emptyDescription' => null,
    'emptyActionRoute' => null,
    'emptyActionLabel' => null,
    'emptyActionEvent' => null,
])

@php
    $emptyTitle = $emptyTitle ?? __('admin.table.empty_title');
    $emptyDescription = $emptyDescription ?? __('admin.table.empty_description');
@endphp

<div {{ $attributes->merge(['class' => 'cyber-card overflow-hidden font-mono text-xs']) }}>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            @isset($header)
                <thead class="bg-[#121212] border-b border-gray-800 text-gray-400">
                    <tr>
                        {{ $header }}
                    </tr>
                </thead>
            @endisset

            <tbody x-data="{ expanded: null }" class="divide-y divide-gray-800/60 {{ $striped ? 'divide-gray-800/40' : '' }}">
                @if($empty)
                    <tr>
                        <td colspan="100" class="p-0">
                            @if(isset($emptySlot))
                                {{ $emptySlot }}
                            @else
                                <x-admin.empty-state
                                    :title="$emptyTitle"
                                    :description="$emptyDescription"
                                    :action-route="$emptyActionRoute"
                                    :action-label="$emptyActionLabel"
                                    :action-event="$emptyActionEvent"
                                />
                            @endif
                        </td>
                    </tr>
                @else
                    {{ $slot }}
                @endif
            </tbody>
        </table>
    </div>

    @isset($pagination)
        <div class="border-t border-gray-800/80 bg-[#121212]/50">
            {{ $pagination }}
        </div>
    @endisset
</div>

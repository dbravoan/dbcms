<div class="space-y-6 w-full">
    @if($title !== '')
        <div class="flex items-center justify-between pb-3 border-b cms-border mb-6">
            <h2 class="text-2xl font-bold cms-text">{{ $title }}</h2>
        </div>
    @endif
    @include('components.cms.card-grid', ['items' => $items, 'columns' => $columns, 'style' => 'cms-card'])
</div>

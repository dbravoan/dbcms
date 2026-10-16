<div class="ia-post-grid">
    @forelse($posts as $post)
        @php
            $trans = $post->translations->firstWhere('locale', app()->getLocale());
            $categoryTranslation = $post->category?->translations->firstWhere('locale', app()->getLocale());
            $postUrl = $localization->url('/blog/'.($trans?->slug ?? ''), $currentMarket);
        @endphp
        @if($trans)
            <article class="ia-post-card cms-card">
                @if($post->featured_image)
                    <img class="ia-post-image" src="{{ $post->featured_image }}" alt="" loading="lazy" decoding="async">
                @endif
                <div class="ia-post-card-body">
                    <div class="ia-post-meta">
                        @if($categoryTranslation)<span>{{ $categoryTranslation->name }}</span>@endif
                        <span>{{ $post->reading_time_minutes }} {{ __('min de lectura') }}</span>
                    </div>
                    <h2><a href="{{ $postUrl }}">{{ $trans->title }}</a></h2>
                    @if($trans->summary)<p>{{ $trans->summary }}</p>@endif
                    <a class="ia-read-link" href="{{ $postUrl }}" aria-label="{{ __('Leer guía') }}: {{ $trans->title }}">{{ __('Leer guía') }} <span aria-hidden="true">↗</span></a>
                </div>
            </article>
        @endif
    @empty
        <p class="ia-empty">{{ __('Todavía no hay guías publicadas para esta selección.') }}</p>
    @endforelse
</div>
@if(method_exists($posts, 'links'))
    <div class="ia-pagination">{{ $posts->links() }}</div>
@endif

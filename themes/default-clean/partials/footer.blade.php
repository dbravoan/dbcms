<footer class="border-t border-slate-200 bg-slate-50 py-12 text-sm text-slate-600">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-3 gap-8">
        <div>
            <div class="font-bold text-slate-900 mb-2">{{ $cmsBrandName ?? config('cms.name') }}</div>
            <p class="text-xs leading-relaxed max-w-sm">{{ $brandDescription }}</p>
        </div>
        <div>
            <div class="font-bold text-slate-900 mb-2">{{ $quickNavTitle }}</div>
            <ul class="space-y-1 text-xs">
                @forelse($footerQuick as $link)
                    <li><a href="{{ $link['url'] }}" class="hover:text-slate-900">{{ $link['title'] }}</a></li>
                @empty
                    <li><a href="{{ $localization->url('/', $currentMarket) }}" class="hover:text-slate-900">{{ $homeTitle }}</a></li>
                    <li><a href="{{ $localization->url('/blog', $currentMarket) }}" class="hover:text-slate-900">{{ $blogTitle }}</a></li>
                @endforelse
            </ul>
        </div>
        <div>
            <div class="font-bold text-slate-900 mb-2">{{ $legalTitle }}</div>
            <ul class="space-y-1 text-xs">
                @forelse($footerLegal as $legal)
                    <li><a href="{{ $legal['url'] }}" class="hover:text-slate-900">{{ $legal['title'] }}</a></li>
                @empty
                    <li><a href="{{ $localization->url('/contacto', $currentMarket) }}" class="hover:text-slate-900">{{ $contactTitle }}</a></li>
                    <li><a href="{{ $localization->url('/privacidad', $currentMarket) }}" class="hover:text-slate-900">{{ $privacyTitle }}</a></li>
                @endforelse
            </ul>
        </div>
    </div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 pt-6 border-t border-slate-200 text-xs text-slate-500">
        &copy; {{ date('Y') }} {{ $cmsBrandName ?? config('cms.name') }}. {{ $rightsReserved }}
    </div>
</footer>
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Scaffolds a complete front-office theme under themes/{id}.
 *
 * The generated theme is discovered automatically by ThemeManagerService and is
 * selectable from the backoffice Themes page.
 */
class MakeFrontThemeCommand extends Command
{
    protected $signature = 'make:front-theme {id : Theme identifier (lowercase slug, e.g. acme)} {--force : Overwrite existing files}';

    protected $description = 'Create a new front-office theme skeleton (themes/{id})';

    public function handle(Filesystem $files): int
    {
        $id = Str::slug((string) $this->argument('id'));

        if ($id === '') {
            $this->error('Invalid theme id. Use a lowercase slug, e.g. "acme".');

            return self::FAILURE;
        }

        $base = base_path("themes/{$id}");

        if ($files->isDirectory($base) && ! $this->option('force')) {
            $this->error("Front theme [{$id}] already exists. Use --force to overwrite.");

            return self::FAILURE;
        }

        $name = Str::headline($id);

        $stubs = [
            'theme.json' => $this->themeJson($id, $name),
            'theme.css' => $this->stylesheetStub($id),
            'layouts/app.blade.php' => $this->layoutStub(),
            'partials/header.blade.php' => $this->headerStub(),
            'partials/footer.blade.php' => $this->footerStub(),
            'pages/show.blade.php' => $this->pageStub(),
            'blog/index.blade.php' => $this->blogIndexStub(),
            'blog/category.blade.php' => $this->blogCategoryStub(),
            'blog/show.blade.php' => $this->blogShowStub(),
            'gallery/index.blade.php' => $this->galleryIndexStub(),
            'gallery/show.blade.php' => $this->galleryShowStub(),
            'shop/index.blade.php' => $this->shopIndexStub(),
            'shop/show.blade.php' => $this->shopShowStub(),
            'shop/cart.blade.php' => $this->shopCartStub(),
            'shop/checkout.blade.php' => $this->shopCheckoutStub(),
            'shop/order_success.blade.php' => $this->shopOrderSuccessStub(),
            'shop/blocks/products.blade.php' => $this->productsBlockStub(),
        ];

        foreach ($stubs as $relative => $contents) {
            $path = $base.'/'.$relative;
            $files->ensureDirectoryExists(dirname($path));
            $files->put($path, str_replace('__THEME__', $id, $contents));
            $this->line("  <fg=green>created</> {$path}");
        }

        $this->newLine();
        $this->info("Front theme [{$id}] created.");
        $this->line(is_file(base_path('cms-project.json'))
            ? 'Add <fg=yellow>'.$id.'</> to themes in <fg=yellow>cms-project.json</>, deploy its asset build, then activate it from Themes.'
            : 'Activate it from the backoffice <fg=yellow>Themes</> page, or set <fg=yellow>CMS_THEME='.$id.'</> in .env.');

        return self::SUCCESS;
    }

    private function themeJson(string $id, string $name): string
    {
        return json_encode([
            'name' => $name,
            'identifier' => $id,
            'version' => '1.0.0',
            'description' => "Front-office theme {$name}.",
            'author' => 'Your Team',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    private function layoutStub(): string
    {
        return <<<'BLADE'
@php
    $adminTheme = $adminTheme ?? (string) config('cms.admin_theme', 'dbcms');
@endphp
<!DOCTYPE html>
<html lang="{{ $currentLocale ?? app()->getLocale() }}" class="h-full front-__THEME__ theme-{{ $adminTheme }} bg-white text-slate-900 antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('cms.name')) | {{ config('cms.name') }}</title>
    <meta name="description" content="@yield('meta_description', config('cms.tagline'))">
    <link rel="canonical" href="{{ url()->current() }}">

    @include('themes::components.seo-head')
    @include('themes::components.analytics')

    @if(isset($hreflangs) && is_array($hreflangs))
        @foreach($hreflangs as $localeTag => $href)
            <link rel="alternate" hreflang="{{ $localeTag }}" href="{{ $href }}" />
        @endforeach
    @endif

    @vite(cms_asset_entries(true))
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="min-h-full flex flex-col justify-between">
    <!--cms-private:themes::__THEME__.partials.header:start-->
    @include('themes::__THEME__.partials.header')
    <!--cms-private:end-->

    <main class="flex-grow">
        @yield('content')
    </main>

    @include('themes::__THEME__.partials.footer')

    @include('themes::components.visitor-geo-modal')
    @include('themes::components.cookie-consent-modal')
    @include('components.frontoffice-admin-bar')
    @include('themes::components.scroll-magic')

    @stack('admin_scripts')
    @stack('scripts')
</body>
</html>
BLADE;
    }

    private function stylesheetStub(string $id): string
    {
        return <<<CSS
@import "tailwindcss/theme" layer(theme);
@import "tailwindcss/utilities" layer(utilities) source(none);
@source "./";

.front-{$id} {
    --cms-accent: #0f766e;
    --cms-accent-contrast: #ffffff;
    --cms-fg: #0f172a;
    --cms-fg-muted: #475569;
    --cms-surface: #ffffff;
    --cms-surface-2: #f8fafc;
    --cms-border: #e2e8f0;
    --cms-border-strong: #cbd5e1;
    --cms-accent-soft: rgba(15, 118, 110, 0.08);
    --cms-accent-border: rgba(15, 118, 110, 0.35);
    --cms-glow: none;
    --cms-radius: 10px;
    --cms-card-blur: none;
}
CSS;
    }

    private function headerStub(): string
    {
        return <<<'BLADE'
@php
    $nav = $frontNav ?? ['layout' => 'inline', 'toggle' => true, 'icon' => 'bars', 'toggleDesktop' => false, 'admin' => false];
    $desktopNavVisible = in_array($nav['layout'], ['inline', 'stacked'], true);
    $navDesktopClass = ($nav['layout'] ?? 'inline') === 'stacked'
        ? 'hidden md:flex flex-col items-start gap-3'
        : 'hidden md:flex items-center gap-6';
    $navFallback = $frontFallbackLinks;
@endphp
<header class="border-b border-slate-200 bg-white/90 backdrop-blur-md sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4 sm:gap-6">
        <a href="{{ $localization->url('/', $currentMarket) }}" class="font-bold text-lg tracking-tight">
            {{ $cmsBrandName ?? config('cms.name') }}
        </a>

        @if($desktopNavVisible)
            @include('themes::components.nav-items', [
                'items' => $headerMenu,
                'fallback' => $navFallback,
                'class' => $navDesktopClass.' text-sm font-semibold',
                'linkClass' => 'text-slate-600 hover:text-slate-900 transition-colors',
            ])
        @endif

        <!-- General search (module Search) -->
        @include('themes::components.search-box', ['variant' => 'light'])

        <div class="flex items-center gap-3 text-sm">
            @if($hasMarkets || $hasLangs)
                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" class="px-3 py-1.5 border border-slate-200 rounded-lg uppercase font-bold text-xs">
                        {{ $marketCode }} / {{ $currentLocale }}
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 mt-2 w-56 bg-white border border-slate-200 rounded-xl shadow-lg py-2 z-50">
                        @foreach($allMarkets as $market)
                            @foreach($market->languages as $lang)
                                @php $directLink = $alternateUrls[$market->code][$lang->code] ?? $localization->url('/', $market, $lang); @endphp
                                <a href="{{ $directLink }}" class="block px-4 py-2 text-xs hover:bg-slate-50">{{ $market->name }} · {{ $lang->name }}</a>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            @endif

            @include('themes::components.nav-admin', [
                'frontNav' => $nav,
                'class' => 'hidden sm:inline-flex px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-bold transition-colors',
                'label' => 'Admin',
            ])

            @include('themes::components.nav-toggle', [
                'frontNav' => $nav,
                'class' => 'p-2 rounded-lg text-slate-600 hover:text-slate-900 transition-colors',
            ])
        </div>
    </div>

    @include('themes::components.nav-panel', [
        'frontNav' => $nav,
        'items' => $headerMenu,
        'fallback' => $navFallback,
        'class' => 'border-t border-slate-200 bg-white px-4 py-4',
        'navClass' => 'flex flex-col',
        'linkClass' => 'block py-2 text-sm font-semibold text-slate-600 hover:text-slate-900',
        'adminClass' => 'mt-2 block text-center py-2 px-3 bg-slate-900 text-white rounded-lg text-xs font-bold',
    ])
</header>
BLADE;
    }

    private function footerStub(): string
    {
        return <<<'BLADE'
<footer class="border-t border-slate-200 bg-slate-50 py-12 text-sm text-slate-600">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-3 gap-8">
        <div>
            <div class="font-bold text-slate-900 mb-2">{{ $cmsBrandName ?? config('cms.name') }}</div>
            <p class="text-xs leading-relaxed max-w-sm">{{ $brandDescription }}</p>
        </div>
        <div>
            <div class="font-bold text-slate-900 mb-2">{{ $quickNavTitle }}</div>
            <ul class="space-y-1 text-xs">
                @foreach(($footerQuick ?: $frontFallbackLinks) as $link)
                    <li><a href="{{ $link['url'] }}" class="hover:text-slate-900">{{ $link['title'] }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <div class="font-bold text-slate-900 mb-2">{{ $legalTitle }}</div>
            <ul class="space-y-1 text-xs">
                @foreach($footerLegal as $legal)
                    <li><a href="{{ $legal['url'] }}" class="hover:text-slate-900">{{ $legal['title'] }}</a></li>
                @endforeach
                <li><button type="button" data-dispatch="open-cookie-settings" class="hover:text-slate-900">{{ $cookieSettingsTitle }}</button></li>
            </ul>
        </div>
    </div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 pt-6 border-t border-slate-200 text-xs text-slate-500">
        &copy; {{ date('Y') }} {{ $cmsBrandName ?? config('cms.name') }}. {{ $rightsReserved }}
    </div>
</footer>
BLADE;
    }

    private function pageStub(): string
    {
        return <<<'BLADE'
@extends('themes::__THEME__.layouts.app')

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
BLADE;
    }

    private function blogIndexStub(): string
    {
        return <<<'BLADE'
@extends('themes::__THEME__.layouts.app')

@section('title', __('Blog'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8">
    <h1 class="text-3xl font-bold">{{ __('Blog') }}</h1>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @forelse($posts as $post)
            @php $trans = $post->translations->where('locale', $currentLocale)->first() ?? $post->translations->first(); @endphp
            <article class="border border-slate-200 rounded-xl overflow-hidden hover:shadow-md transition-shadow">
                <div class="p-5 space-y-2">
                    <h2 class="font-bold text-lg">{{ $trans?->title }}</h2>
                    <p class="text-sm text-slate-500">{{ \Illuminate\Support\Str::limit($trans?->summary, 120) }}</p>
                    <a href="{{ $localization->url('/blog/'.$trans?->slug, $currentMarket) }}" class="text-sm font-semibold text-slate-900 hover:underline">{{ __('Read more') }} &rarr;</a>
                </div>
            </article>
        @empty
            <p class="text-slate-500 col-span-full">{{ __('No articles available.') }}</p>
        @endforelse
    </div>
    <div>{{ $posts->links() }}</div>
</div>
@endsection
BLADE;
    }

    private function blogCategoryStub(): string
    {
        return <<<'BLADE'
@extends('themes::__THEME__.layouts.app')

@section('title', $category->name ?? __('Category'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8">
    <h1 class="text-3xl font-bold">{{ $category->name ?? __('Category') }}</h1>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @forelse($posts as $post)
            @php $trans = $post->translations->where('locale', $currentLocale)->first() ?? $post->translations->first(); @endphp
            <article class="border border-slate-200 rounded-xl p-5 space-y-2">
                <h2 class="font-bold text-lg">{{ $trans?->title }}</h2>
                <a href="{{ $localization->url('/blog/'.$trans?->slug, $currentMarket) }}" class="text-sm font-semibold hover:underline">{{ __('Read more') }} &rarr;</a>
            </article>
        @empty
            <p class="text-slate-500 col-span-full">{{ __('No articles available.') }}</p>
        @endforelse
    </div>
</div>
@endsection
BLADE;
    }

    private function blogShowStub(): string
    {
        return <<<'BLADE'
@extends('themes::__THEME__.layouts.app')

@section('title', $translation?->meta_title ?? $translation?->title ?? '')
@section('meta_description', $translation?->meta_description ?? '')

@section('content')
<article class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-6">
    <h1 class="text-3xl font-bold">{{ $translation?->title }}</h1>
    @if(!empty($renderedSections))
        @foreach($renderedSections as $sectionHtml)
            {!! $sectionHtml !!}
        @endforeach
    @else
    <div class="prose max-w-none">{!! app(\App\Services\Media\MarkdownRendererService::class)->renderHtml((string) $translation?->content) !!}</div>
    @endif
</article>
@endsection
BLADE;
    }

    private function galleryIndexStub(): string
    {
        return <<<'BLADE'
@extends('themes::__THEME__.layouts.app')

@section('title', __('Gallery'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8">
    <h1 class="text-3xl font-bold">{{ __('Gallery') }}</h1>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($galleries as $gallery)
            @php
                $trans = $gallery->translations->where('locale', $currentLocale)->first() ?? $gallery->translations->first();
                $cover = $gallery->items->first();
            @endphp
            <a href="{{ $localization->url('/'.$galPrefix.'/'.$trans?->slug, $currentMarket) }}" class="group block border border-slate-200 rounded-xl overflow-hidden">
                @if($cover)
                    <img src="{{ $cover->image_url }}" alt="{{ $trans?->title }}" class="w-full h-48 object-cover group-hover:scale-105 transition-transform">
                @endif
                <div class="p-4 font-semibold">{{ $trans?->title }}</div>
            </a>
        @empty
            <p class="text-slate-500 col-span-full">{{ __('No galleries available.') }}</p>
        @endforelse
    </div>
    <div>{{ $galleries->links() }}</div>
</div>
@endsection
BLADE;
    }

    private function galleryShowStub(): string
    {
        return <<<'BLADE'
@extends('themes::__THEME__.layouts.app')

@section('title', $translation?->meta_title ?? $translation?->title ?? $gallery->title)
@section('meta_description', $translation?->meta_description ?? $translation?->description ?? '')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8">
    <h1 class="text-3xl font-bold">{{ $translation?->title ?? $gallery->title }}</h1>
    @if($translation?->description)
        <p class="text-slate-500 max-w-2xl">{{ $translation->description }}</p>
    @endif
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @forelse($items as $item)
            <img src="{{ $item->image_url }}" alt="{{ $item->title ?? '' }}" class="w-full aspect-square object-cover rounded-xl">
        @empty
            <p class="text-slate-500 col-span-full">{{ __('No images assigned') }}</p>
        @endforelse
    </div>
</div>
@endsection
BLADE;
    }

    private function shopIndexStub(): string
    {
        return <<<'BLADE'
@extends('themes::__THEME__.layouts.app')

@section('title', $isShop ?? false ? __('Online Store') : __('Catalog'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8">
    <h1 class="text-3xl font-bold">{{ $isShop ?? false ? __('Online Store') : __('Catalog') }}</h1>
    {{-- Facet navigation: theme-skinned filter panel (canonical, crawlable URLs) --}}
    <x-cms-filter.bar :module="$filterModule ?? null" :set="$filterSet ?? null" :market="$currentMarket" />

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        @forelse($products as $product)
            @php $trans = $product->translations->where('locale', $currentLocale)->first(); @endphp
            <a href="{{ $localization->url('/'.$localization->getModulePrefix('shop', $currentLocale).'/'.$trans?->slug, $currentMarket, $currentLocale) }}" class="border border-slate-200 rounded-xl overflow-hidden group">
                @if($product->featured_image)
                    <img src="{{ $product->featured_image }}" alt="{{ $trans?->name }}" class="w-full h-44 object-cover" loading="lazy" decoding="async">
                @endif
                <div class="p-4 space-y-1">
                    <div class="font-semibold">{{ $trans?->name }}</div>
                    @if(($showPrices ?? true))
                        <div class="text-sm text-slate-500">{{ $product->getFormattedPrice($currentMarket) }}</div>
                    @endif
                </div>
            </a>
        @empty
            <p class="text-slate-500 col-span-full">{{ __('No products in this selection') }}</p>
        @endforelse
    </div>
    <div>{{ $products->links() }}</div>
</div>
@endsection
BLADE;
    }

    private function shopShowStub(): string
    {
        return <<<'BLADE'
@extends('themes::__THEME__.layouts.app')

@section('title', $translation?->meta_title ?? $translation?->name ?? '')
@section('meta_description', $translation?->meta_description ?? '')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 grid grid-cols-1 md:grid-cols-2 gap-10">
    <div>
        @if($product->featured_image)
            <img src="{{ $product->featured_image }}" alt="{{ $translation?->name }}" class="w-full rounded-2xl object-cover" loading="lazy" decoding="async">
        @endif
    </div>
    <div class="space-y-4">
        @if($errors->any())
            <div role="alert" class="text-red-700">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif
        <h1 class="text-3xl font-bold">{{ $translation?->name }}</h1>
        @if(($showPrices ?? true))
            <div class="text-2xl font-black">{{ $product->getFormattedPrice($currentMarket) }}</div>
        @endif
        @if(empty($renderedSections))
            <div class="prose max-w-none">{!! app(\App\Services\Media\MarkdownRendererService::class)->renderHtml((string) $translation?->description) !!}</div>
        @endif
        @if($isShop ?? false)
            <form method="POST" action="{{ route('shop.cart.add', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="space-y-3">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="number" name="quantity" value="1" min="1" class="w-24 border border-slate-300 rounded-lg px-3 py-2">
                <button type="submit" class="btn-neon">{{ __('Add to cart') }}</button>
            </form>
        @endif
    </div>
</div>
@foreach($renderedSections ?? [] as $sectionHtml)
    {!! $sectionHtml !!}
@endforeach
@endsection
BLADE;
    }

    private function shopCartStub(): string
    {
        return <<<'BLADE'
@extends('themes::__THEME__.layouts.app')

@section('title', __('Shopping Cart'))

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-6">
    <h1 class="text-3xl font-bold">{{ __('Shopping Cart') }}</h1>
    @if(empty($items))
        <p class="text-slate-500">{{ __('Your cart is empty.') }}</p>
    @else
        <ul class="divide-y divide-slate-200">
            @foreach($items as $item)
                <li class="py-3 flex items-center justify-between text-sm">
                    <span>{{ $item['product']->translations->firstWhere('locale', $currentLocale)?->name ?? $item['product']->sku }}</span>
                    <span>{{ $item['quantity'] ?? 1 }} × {{ $item['formatted_unit_price'] }}</span>
                </li>
            @endforeach
        </ul>
        <p>{{ __('Total:') }} {{ number_format($grandTotal, 2) }} {{ $currentMarket->currency }}</p>
        <a class="btn-neon" href="{{ route('shop.checkout', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}">{{ __('Complete Order') }}</a>
    @endif
</div>
@endsection
BLADE;
    }

    private function shopCheckoutStub(): string
    {
        return <<<'BLADE'
@extends('themes::__THEME__.layouts.app')

@section('title', __('Complete Order'))

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-6">
    <h1 class="text-3xl font-bold">{{ __('Complete Order') }}</h1>
    <p class="text-slate-500 text-sm">{{ __('Enter the shipping details and confirm your preferred payment method.') }}</p>
    <form method="POST" action="{{ route('shop.checkout.process', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="space-y-4 text-sm">
        @csrf
        <input type="text" name="customer_name" required placeholder="{{ __('Full Name') }}" class="w-full border border-slate-300 rounded-lg px-3 py-2">
        <input type="email" name="customer_email" required placeholder="tu@email.com" class="w-full border border-slate-300 rounded-lg px-3 py-2">
        <input type="hidden" name="payment_method" value="bank_transfer">
        <input type="text" name="city" required placeholder="{{ __('City') }}">
        <input type="text" name="postal_code" required placeholder="{{ __('Postal Code') }}">
        <input type="text" name="country" required placeholder="{{ __('Country') }}">
        <input type="text" name="address" required placeholder="{{ __('Street and Number') }}" class="w-full border border-slate-300 rounded-lg px-3 py-2">
        <button type="submit" class="btn-neon">{{ __('Complete Order') }} &rarr;</button>
    </form>
</div>
@endsection
BLADE;
    }

    private function shopOrderSuccessStub(): string
    {
        return <<<'BLADE'
@extends('themes::__THEME__.layouts.app')

@section('title', __('Order Confirmed'))

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center space-y-4">
    <h1 class="text-3xl font-bold">{{ __('Order Confirmed Successfully!') }}</h1>
    <p>{{ __('Hemos registrado tu pedido. Contacto:') }} {{ $order->customer_email }}.</p>
    <div class="text-sm text-slate-600">{{ __('Order Number:') }} <strong>{{ $order->order_number ?? '' }}</strong></div>
</div>
@endsection
BLADE;
    }

    private function productsBlockStub(): string
    {
        return <<<'BLADE'
<div class="space-y-4">
    @if($title !== '')<h2 class="text-xl font-semibold">{{ $title }}</h2>@endif
    @if($subtitle !== '')<p>{{ $subtitle }}</p>@endif
    <div class="grid {{ $gridClass }} gap-6">
        @foreach($items as $item)
            <a href="{{ $item['url'] }}" class="border border-slate-200 rounded-xl overflow-hidden">
                @if($item['image'] !== '')
                    <img src="{{ $item['image'] }}" alt="{{ $item['title'] }}" class="w-full h-40 object-cover" loading="lazy" decoding="async">
                @endif
                <div class="p-3 text-sm font-semibold">{{ $item['title'] }}</div>
                @if($showPrices)<div class="p-3">{{ $item['price'] }}</div>@endif
            </a>
        @endforeach
    </div>
</div>
BLADE;
    }
}

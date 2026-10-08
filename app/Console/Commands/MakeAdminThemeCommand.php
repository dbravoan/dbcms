<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Scaffolds a new backoffice theme under resources/views/themes/admin/{id}.
 *
 * The generated theme is discovered automatically by AdminThemeService and is
 * selectable by setting CMS_BRAND to its identifier.
 */
class MakeAdminThemeCommand extends Command
{
    protected $signature = 'make:admin-theme {id : Theme identifier (lowercase slug, e.g. acme)} {--force : Overwrite existing files}';

    protected $description = 'Create a new backoffice theme skeleton (resources/views/themes/admin/{id})';

    public function handle(Filesystem $files): int
    {
        $id = Str::slug((string) $this->argument('id'));

        if ($id === '') {
            $this->error('Invalid theme id. Use a lowercase slug, e.g. "acme".');

            return self::FAILURE;
        }

        $base = resource_path("views/themes/admin/{$id}");

        if ($files->isDirectory($base) && ! $this->option('force')) {
            $this->error("Backoffice theme [{$id}] already exists. Use --force to overwrite.");

            return self::FAILURE;
        }

        $name = Str::headline($id);

        $stubs = [
            'theme.json' => $this->themeJson($id, $name),
            'admin/layout.blade.php' => $this->layoutStub($id, $name),
            'admin/dashboard.blade.php' => $this->dashboardStub(),
            'admin/auth/login.blade.php' => $this->loginStub($id),
            'errors/layout.blade.php' => $this->errorLayoutStub($id),
            "assets/{$id}.css" => $this->cssStub($id),
        ];

        foreach ($stubs as $relative => $contents) {
            $path = $base.'/'.$relative;
            $files->ensureDirectoryExists(dirname($path));
            $files->put($path, $contents);
            $this->line("  <fg=green>created</> {$path}");
        }

        $this->newLine();
        $this->info("Backoffice theme [{$id}] created.");
        $this->line('Activate it by setting <fg=yellow>CMS_BRAND='.$id.'</> in your .env file.');

        return self::SUCCESS;
    }

    private function themeJson(string $id, string $name): string
    {
        return json_encode([
            'name' => $name,
            'identifier' => $id,
            'version' => '1.0.0',
            'description' => "Backoffice theme {$name}.",
            'author' => 'Your Team',
            'brand' => [
                'name' => $name,
                'tagline' => '',
                'logo' => null,
                'icon' => null,
                'edition' => null,
                'accent' => '#0f766e',
                'mode' => 'light',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    private function layoutStub(string $id, string $name): string
    {
        return <<<BLADE
@php
    /** @var \\App\\Models\\User|null \$user */
    \$user = \$currentUser ?? auth()->user();
    \$navigation = \$navigation ?? [];
    \$cmsBrandName = \$cmsBrandName ?? (string) config('cms.name', 'CMS');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full theme-{$id}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('admin.layout.backoffice')) | {{ \$cmsBrandName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ theme_asset('{$id}.css') }}?v={{ config('cms.version', '1.0.0') }}">
    <style>[x-cloak]{display:none!important}</style>
</head>
<body class="h-full bg-slate-100 text-slate-900">
<div class="h-full flex" x-data="{ mobileNavOpen: false }">
    <aside class="hidden md:flex md:flex-col w-64 shrink-0 bg-white border-r border-slate-200">
        <div class="h-16 flex items-center px-5 border-b border-slate-200 font-bold text-lg">
            <a href="{{ route('admin.dashboard') }}" class="truncate">{{ \$cmsBrandName }}</a>
        </div>
        <nav class="flex-1 overflow-y-auto p-3 space-y-3 text-sm">
            @foreach(\$navigation as \$section)
                @php
                    \$sectionVisible = is_object(\$section) ? (\$section->visible ?? true) : (\$section['visible'] ?? true);
                    \$sectionTitle = is_object(\$section) ? (\$section->title ?? '') : (\$section['title'] ?? '');
                    \$sectionItems = is_object(\$section) ? (\$section->items ?? []) : (\$section['items'] ?? []);
                @endphp
                @if(\$sectionVisible)
                    @if(!empty(\$sectionTitle))
                        <div class="px-3 pt-2 text-[10px] uppercase tracking-wider text-slate-400 font-bold">{{ \$sectionTitle }}</div>
                    @endif
                    @foreach(\$sectionItems as \$item)
                        @php
                            \$itemRoute = is_object(\$item) ? (\$item->route ?? '') : (\$item['route'] ?? '');
                            \$itemUrl = \\Illuminate\\Support\\Facades\\Route::has(\$itemRoute) ? route(\$itemRoute) : url(\$itemRoute);
                            \$itemLabel = is_object(\$item) ? (\$item->label ?? '') : (\$item['label'] ?? '');
                            \$itemIcon = is_object(\$item) ? (\$item->icon ?? '') : (\$item['icon'] ?? '');
                            \$itemActive = is_object(\$item) ? (\$item->active ?? false) : (\$item['active'] ?? false);
                        @endphp
                        <a href="{{ \$itemUrl }}" class="flex items-center gap-3 px-3 py-2 rounded-lg {{ \$itemActive ? 'nav-link-active bg-slate-900 text-white font-semibold' : 'text-slate-600 hover:bg-slate-100' }}">
                            @if(!empty(\$itemIcon))<i class="{{ \$itemIcon }} w-4 text-center"></i>@endif
                            <span class="truncate">{{ \$itemLabel }}</span>
                        </a>
                    @endforeach
                @endif
            @endforeach
        </nav>
        <div class="p-4 border-t border-slate-200 text-xs flex items-center justify-between gap-2">
            <span class="truncate font-semibold">{{ \$user?->name }}</span>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="text-rose-600 hover:underline">{{ __('admin.layout.logout') }}</button>
            </form>
        </div>
    </aside>

    <div class="flex-1 flex flex-col min-w-0">
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 sm:px-6 shrink-0">
            <div class="flex items-center gap-3">
                <button type="button" @click="mobileNavOpen = !mobileNavOpen" class="md:hidden p-2 rounded-lg border border-slate-200">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <span class="text-sm font-bold">@yield('title')</span>
            </div>
            <div class="flex items-center gap-3">
                <x-admin.locale-switcher />
            </div>
        </header>
        <main class="flex-1 overflow-y-auto p-4 sm:p-6">
            @yield('content')
        </main>
    </div>
</div>
@stack('admin_scripts')
@stack('scripts')
</body>
</html>
BLADE;
    }

    private function dashboardStub(): string
    {
        return <<<'BLADE'
@extends('admin.layout')

@section('title', __('admin.nav.items.dashboard'))

@section('content')
<div class="space-y-6">
    <div class="cyber-card p-6">
        <h1 class="text-xl font-bold">{{ __('admin.dashboard.control_panel') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ $cmsBrandName ?? config('cms.name') }}</p>
    </div>
</div>
@endsection
BLADE;
    }

    private function loginStub(string $id): string
    {
        return <<<BLADE
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full theme-{$id}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('admin.auth.backoffice_access_title') }} | {{ config('cms.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ theme_asset('{$id}.css') }}">
</head>
<body class="h-full flex items-center justify-center bg-slate-100 text-slate-900">
    <div class="w-full max-w-md cyber-card p-8 space-y-4">
        <h1 class="text-lg font-bold">{{ __('admin.auth.backoffice_access_title') }}</h1>
        @if(session('error'))
            <div class="p-3 rounded-lg bg-rose-50 text-rose-700 text-sm">{{ session('error') }}</div>
        @endif
        <form method="POST" action="{{ route('admin.login.submit') }}" class="space-y-4 text-sm">
            @csrf
            <div>
                <label class="block font-semibold mb-1">{{ __('admin.auth.email') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>
            <div>
                <label class="block font-semibold mb-1">{{ __('admin.auth.password') }}</label>
                <input type="password" name="password" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>
            <button type="submit" class="btn-neon w-full">{{ __('admin.auth.submit') }}</button>
        </form>
        <div class="flex justify-center"><x-admin.locale-switcher /></div>
    </div>
</body>
</html>
BLADE;
    }

    private function errorLayoutStub(string $id): string
    {
        return <<<BLADE
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full theme-{$id}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | {{ config('cms.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ theme_asset('{$id}.css') }}">
</head>
<body class="h-full flex items-center justify-center bg-slate-100 text-slate-900">
    <div class="cyber-card p-8 max-w-lg text-center space-y-4">
        <h1 class="text-4xl font-black">@yield('code')</h1>
        <p class="text-slate-500">@yield('message')</p>
        <a href="{{ url('/') }}" class="btn-neon inline-flex">{{ __('admin.layout.backoffice') }}</a>
    </div>
</body>
</html>
BLADE;
    }

    private function cssStub(string $id): string
    {
        return <<<CSS
/* {$id} backoffice theme styles.
   Keep semantic classes (.cyber-card, .btn-neon, .nav-link-active) so the
   shared <x-admin.*> components render correctly. */

:root {
    --theme-accent: #0f766e;
    --theme-accent-contrast: #ffffff;
    --theme-surface: #ffffff;
    --theme-border: #e2e8f0;
}

.cyber-card {
    background-color: var(--theme-surface);
    border: 1px solid var(--theme-border);
    border-radius: 14px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
}

.btn-neon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 16px;
    background-color: var(--theme-accent);
    border: 1px solid var(--theme-accent);
    color: var(--theme-accent-contrast);
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease-in-out;
}

.btn-neon:hover {
    filter: brightness(1.08);
}

.nav-link-active {
    color: var(--theme-accent) !important;
}
CSS;
    }
}

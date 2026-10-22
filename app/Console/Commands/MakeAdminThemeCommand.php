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
            'theme.css' => "@import \"tailwindcss/theme\" layer(theme);\n@import \"tailwindcss/utilities\" layer(utilities) source(none);\n@source \"./\";\n@import '../../../../css/admin-utility-sources.css';\n",
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
    \$cmsBrandName = \$cmsBrandName ?? (string) config('cms.name', 'CMS');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full theme-{$id}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('admin.layout.backoffice')) | {{ \$cmsBrandName }}</title>
    @vite(cms_asset_entries())
    <link rel="stylesheet" href="{{ theme_asset('{$id}.css') }}?v={{ config('cms.version') }}">
    <style>[x-cloak]{display:none!important}</style>
</head>
<body data-admin-shell class="h-full flex overflow-hidden bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)]"
      x-data="{ mobileNavOpen: false }" x-on:keydown.escape.window="mobileNavOpen = false">
    <div x-show="mobileNavOpen" x-on:click="mobileNavOpen = false" x-transition.opacity x-cloak
         class="fixed inset-0 bg-black/60 z-40 md:hidden" style="display: none;"></div>
    <aside id="admin-navigation" :class="mobileNavOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full md:translate-x-0'"
           class="fixed md:static inset-y-0 left-0 w-64 h-full flex flex-col shrink-0 z-50 md:z-20 overflow-hidden transition-transform bg-[color:var(--admin-surface)] border-r border-[color:var(--admin-border)]">
        <div class="h-16 flex items-center justify-between gap-2 px-5 border-b border-[color:var(--admin-border)] font-bold text-lg shrink-0">
            <a href="{{ route('admin.dashboard') }}" class="truncate">{{ \$cmsBrandName }}</a>
            <button type="button" x-on:click="mobileNavOpen = false" aria-label="{{ __('admin.common.close') }}"
                    class="md:hidden p-2 min-h-10 min-w-10 shrink-0">&times;</button>
        </div>
        <x-admin.sidebar-navigation :sections="\$navSections ?? []" />
        <div class="p-4 border-t border-[color:var(--admin-border)] text-xs flex items-center justify-between gap-2 shrink-0">
            <span class="truncate font-semibold">{{ \$user?->name }}</span>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="text-[color:var(--admin-danger)] hover:underline">{{ __('admin.layout.logout') }}</button>
            </form>
        </div>
    </aside>

    <div class="flex-1 flex flex-col min-w-0">
        <header class="h-16 bg-[color:var(--admin-surface)] border-b border-[color:var(--admin-border)] flex items-center justify-between px-4 sm:px-6 shrink-0">
            <div class="flex items-center gap-3">
                <button type="button" x-on:click="mobileNavOpen = !mobileNavOpen" aria-controls="admin-navigation"
                        aria-expanded="false" :aria-expanded="mobileNavOpen.toString()" aria-label="{{ __('admin.nav.label') }}"
                        class="md:hidden p-2 min-h-10 min-w-10 rounded-lg border border-[color:var(--admin-border)]">
                    <i class="fa-solid fa-bars" aria-hidden="true"></i>
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
    @vite(cms_asset_entries())
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
    @vite(cms_asset_entries())
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
/* {$id} backoffice theme styles. Shared components consume --admin-* tokens. */

html.theme-{$id} {
    --theme-accent: #0f766e;
    --theme-accent-contrast: #ffffff;
    --theme-surface: #ffffff;
    --theme-border: #e2e8f0;
    --admin-color-scheme: light;
    --admin-accent: var(--theme-accent);
    --admin-accent-hover: #115e59;
    --admin-on-accent: var(--theme-accent-contrast);
    --admin-surface: var(--theme-surface);
    --admin-surface-raised: #f8fafc;
    --admin-text: #0f172a;
    --admin-muted: #475569;
    --admin-border: var(--theme-border);
    --admin-control-bg: #ffffff;
    --admin-control-border: #cbd5e1;
    --admin-control-text: #0f172a;
    --admin-control-placeholder: #64748b;
    --admin-control-focus: var(--theme-accent);
    --admin-control-ring: rgba(15, 118, 110, .16);
    --admin-danger: #b91c1c;
    --admin-danger-bg: #fff1f2;
    --admin-warning: #92400e;
    --admin-warning-bg: #fffbeb;
    --admin-message-bg: #f8fafc;
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

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full theme-dbcms bg-[#1A1A1A]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('admin.auth.login_title') }} | {{ config('cms.name') }}</title>
    <style nonce="{{ $cspNonce ?? '' }}">
        body {
            font-family: 'Google Sans Code', monospace;
            background-color: #1A1A1A;
            color: #FFFFFF;
        }
        .btn-neon {
            padding: 10px 20px;
            background-color: transparent;
            border: 1px solid #00FF99;
            color: #00FF99;
            font-family: 'Google Sans Code', monospace;
            font-weight: 700;
            font-size: 0.85rem;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.25s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-neon:hover {
            background-color: #00FF99;
            color: #1A1A1A;
            box-shadow: 0 0 15px rgba(0, 255, 153, 0.4);
        }
        .cyber-card {
            background-color: #141414;
            border: 1px solid rgba(0, 255, 153, 0.2);
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.8);
        }
        .cursor-blink {
            color: #00FF99;
            display: inline-block;
            animation: blink 1s step-end infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-admin-shell class="min-h-full flex flex-col justify-center py-10 px-4 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <h2 class="text-3xl font-extrabold text-white tracking-wider flex items-center justify-center gap-2">
            <span>&gt;</span><span class="cursor-blink">_</span><span>{{ brand()->name }}</span>
            <span class="text-[10px] font-mono text-[#00FF99] border border-[#00FF99]/40 bg-[#00FF99]/10 px-1.5 py-0.5 rounded font-bold ml-1">
                CORE
            </span>
        </h2>
        <p class="mt-2 text-xs text-gray-400 font-mono">
            {{ __('admin.auth.tagline') }}
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="cyber-card py-8 px-6 shadow sm:rounded-xl sm:px-10">
            @if(session('error'))
                <div role="alert" class="mb-4 p-3 bg-red-900/30 border border-red-800 text-red-400 rounded-lg text-xs font-mono">
                    {{ session('error') }}
                </div>
            @endif

            @if(session('success'))
                <div role="status" class="mb-4 p-3 bg-[#00FF99]/10 border border-[#00FF99]/30 text-[#00FF99] rounded-lg text-xs font-mono">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.submit') }}" class="space-y-4 font-mono">
                @csrf
                <div class="admin-field">
                    <label for="login-email" class="admin-field-label">{{ __('admin.auth.email') }}</label>
                    <input id="login-email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" inputmode="email"
                           @error('email') aria-invalid="true" aria-describedby="login-email-error" @enderror
                           class="admin-control">
                    @error('email')
                        <p id="login-email-error" class="admin-field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="admin-field">
                    <label for="login-password" class="admin-field-label">{{ __('admin.auth.password') }}</label>
                    <input id="login-password" type="password" name="password" required autocomplete="current-password"
                           @error('password') aria-invalid="true" aria-describedby="login-password-error" @enderror
                           class="admin-control">
                    @error('password')
                        <p id="login-password-error" class="admin-field-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center text-xs text-gray-400">
                        <input type="checkbox" name="remember" class="rounded bg-[#1A1A1A] border-gray-700 text-[#00FF99] focus:ring-[#00FF99]" checked>
                        <span class="ml-2">{{ __('admin.auth.remember') }}</span>
                    </label>
                </div>

                <div class="pt-2">
                    <button type="submit" class="admin-btn admin-btn--primary admin-btn--lg w-full">
                        <span>{{ __('admin.auth.submit') }}</span>
                        <span>&rarr;</span>
                    </button>
                </div>
            </form>

            <div class="mt-6 flex items-center justify-center">
                <x-admin.locale-switcher />
            </div>

            <!-- 1-Click Demo Persona Credentials Quick Access -->
            @if(!app()->isProduction())
            <div class="mt-8 pt-6 border-t border-gray-800 font-mono text-xs space-y-4">
                <div class="text-[11px] font-bold text-[#00FF99] uppercase tracking-wider text-center">
                    &gt; {{ __('admin.auth.quick_access_title') }}
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <a href="{{ route('admin.quick-login', 'admin') }}" class="p-2.5 bg-[#1A1A1A] hover:bg-[#00FF99]/15 border border-gray-800 hover:border-[#00FF99] text-gray-300 hover:text-[#00FF99] text-center rounded-lg transition-all">
                        <span class="font-bold block text-white">Admin</span>
                        <span class="block text-[10px] text-[#00FF99]">{{ __('admin.auth.role_scope.admin') }}</span>
                    </a>
                    <a href="{{ route('admin.quick-login', 'editor') }}" class="p-2.5 bg-[#1A1A1A] hover:bg-[#00FF99]/15 border border-gray-800 hover:border-[#00FF99] text-gray-300 hover:text-[#00FF99] text-center rounded-lg transition-all">
                        <span class="font-bold block text-white">Editor</span>
                        <span class="block text-[10px] text-gray-400">{{ __('admin.auth.role_scope.editor') }}</span>
                    </a>
                    <a href="{{ route('admin.quick-login', 'author') }}" class="p-2.5 bg-[#1A1A1A] hover:bg-[#00FF99]/15 border border-gray-800 hover:border-[#00FF99] text-gray-300 hover:text-[#00FF99] text-center rounded-lg transition-all">
                        <span class="font-bold block text-white">Author</span>
                        <span class="block text-[10px] text-gray-400">{{ __('admin.auth.role_scope.author') }}</span>
                    </a>
                    <a href="{{ route('admin.quick-login', 'marketing') }}" class="p-2.5 bg-[#1A1A1A] hover:bg-[#00FF99]/15 border border-gray-800 hover:border-[#00FF99] text-gray-300 hover:text-[#00FF99] text-center rounded-lg transition-all">
                        <span class="font-bold block text-white">Marketing</span>
                        <span class="block text-[10px] text-gray-400">{{ __('admin.auth.role_scope.marketing') }}</span>
                    </a>
                    <a href="{{ route('admin.quick-login', 'viewer') }}" class="col-span-2 sm:col-span-2 p-2.5 bg-[#1A1A1A] hover:bg-[#00FF99]/15 border border-gray-800 hover:border-[#00FF99] text-gray-300 hover:text-[#00FF99] text-center rounded-lg transition-all">
                        <span class="font-bold block text-white">Viewer (Auditor)</span>
                        <span class="block text-[10px] text-gray-400">{{ __('admin.auth.role_scope.viewer') }}</span>
                    </a>
                </div>

                <div class="mt-4 p-3.5 bg-black/60 border border-gray-800/80 rounded-lg text-[11px] space-y-2">
                    <div class="flex items-center justify-between text-gray-400 border-b border-gray-800 font-bold pb-1.5 uppercase text-[10px]">
                        <span>{{ __('admin.auth.manual_credentials') }}</span>
                        <span class="text-[#00FF99]">{{ __('admin.auth.local_environment') }}</span>
                    </div>
                    <div class="space-y-1 font-mono text-[11px]">
                        <div class="flex items-center justify-between text-gray-300">
                            <span class="text-white font-bold">admin@db-cms.io <span class="text-gray-500 font-normal">(Admin)</span></span>
                            <code class="text-[#00FF99] bg-[#1A1A1A] px-1.5 py-0.5 rounded border border-gray-700">secret123</code>
                        </div>
                        <div class="flex items-center justify-between text-gray-300">
                            <span class="text-white font-bold">editor@db-cms.io <span class="text-gray-500 font-normal">(Editor)</span></span>
                            <code class="text-[#00FF99] bg-[#1A1A1A] px-1.5 py-0.5 rounded border border-gray-700">secret123</code>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</body>
</html>

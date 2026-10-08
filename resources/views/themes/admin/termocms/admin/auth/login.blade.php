@php
    $brand = $cmsBrand ?? brand();
    $lang = str_replace('_', '-', app()->getLocale());
    $frontUrl = url('/');
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" class="h-full theme-termocms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('admin.auth.login_title') }} | {{ $brand->name }}</title>
    <link rel="icon" type="image/png" href="{{ theme_asset('termosalud-favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ theme_asset('termosalud-favicon.png') }}">
    <link rel="stylesheet" href="{{ theme_asset('termocms.css') }}">
    <style nonce="{{ $cspNonce ?? '' }}">
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #F8FAFC;
            background-image:
                radial-gradient(60rem 30rem at 15% -10%, rgba(0, 144, 168, 0.10), transparent 60%),
                radial-gradient(45rem 25rem at 110% 10%, rgba(13, 148, 136, 0.10), transparent 55%);
            background-repeat: no-repeat;
            color: #0F172A;
        }
        input:focus-visible, button:focus-visible, a:focus-visible, summary:focus-visible {
            outline: 2px solid #0090A8;
            outline-offset: 2px;
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-admin-shell class="min-h-full flex flex-col justify-center py-10 sm:px-6 lg:px-8 font-sans">

    <main class="sm:mx-auto sm:w-full sm:max-w-md w-full px-4 sm:px-0">
        {{-- Brand header --}}
        <header class="flex flex-col items-center gap-4 text-center">
            <img src="{{ theme_asset('termosalud-favicon.png') }}"
                 alt=""
                 aria-hidden="true"
                 class="w-14 h-14 rounded-2xl border border-slate-200 bg-white p-1 shadow-sm object-contain">

            <div class="flex items-center justify-center gap-2.5">
                <img src="{{ theme_asset('termosalud-logo.png') }}" alt="{{ $brand->name }}" class="h-7 w-auto object-contain">
                @if(!empty($brand->edition))
                    <span class="text-[10px] text-teal-700 border border-teal-200 bg-teal-50 px-2 py-0.5 rounded-full font-bold uppercase tracking-wider">
                        {{ mb_strtoupper($brand->edition) }}
                    </span>
                @endif
            </div>
            <p class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-teal-700">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                <span>{{ __('admin.auth.secure_authentication') }}</span>
            </p>
        </header>

        {{-- Login card --}}
        <section class="mt-8 bg-white border border-slate-200 rounded-2xl shadow-xl shadow-slate-900/5 px-6 py-8 sm:px-10"
                 aria-labelledby="login-heading">
            <h1 id="login-heading" class="text-lg font-bold text-slate-900">{{ __('admin.auth.login_title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('admin.auth.login_subtitle') }}</p>

            @if(session('error') || session('success') || (isset($errors) && $errors->any()))
                <div class="mt-6 space-y-2" role="status" aria-live="polite">
                    @if(isset($errors) && $errors->any())
                        <div class="p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs">
                            <ul class="list-disc pl-4 space-y-0.5">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-xs">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if(session('success'))
                        <div class="p-3 bg-teal-50 border border-teal-200 text-teal-800 rounded-xl text-xs">
                            {{ session('success') }}
                        </div>
                    @endif
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.submit') }}" class="mt-6 space-y-5">
                @csrf

                <div>
                    <label for="login-email" class="admin-field-label">
                        {{ __('admin.auth.email') }}
                    </label>
                    <div class="relative mt-1.5">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400" aria-hidden="true">
                            <i class="fa-solid fa-envelope text-xs"></i>
                        </span>
                        <input id="login-email"
                               type="email"
                               name="email"
                               value="{{ old('email') }}"
                               required
                               autofocus
                               autocomplete="username"
                               inputmode="email"
                               @if(isset($errors) && $errors->has('email')) aria-invalid="true" aria-describedby="login-email-error" @endif
                               class="admin-control admin-control--leading-icon">
                    </div>
                    @if(isset($errors) && $errors->has('email'))
                        <p id="login-email-error" class="admin-field-error">{{ $errors->first('email') }}</p>
                    @endif
                </div>

                <div>
                    <label for="login-password" class="admin-field-label">
                        {{ __('admin.auth.password') }}
                    </label>
                    <div class="relative mt-1.5">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400" aria-hidden="true">
                            <i class="fa-solid fa-lock text-xs"></i>
                        </span>
                        <input id="login-password"
                               type="password"
                               name="password"
                               required
                               autocomplete="current-password"
                               @if(isset($errors) && $errors->has('password')) aria-invalid="true" aria-describedby="login-password-error" @endif
                               class="admin-control admin-control--leading-icon admin-control--trailing-icon">
                        <button type="button"
                                data-password-toggle="login-password"
                                aria-pressed="false"
                                aria-controls="login-password"
                                title="{{ __('admin.auth.show_password') }}"
                                class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-xl text-slate-400 transition-colors hover:text-slate-700">
                            <i class="fa-solid fa-eye text-xs" aria-hidden="true"></i>
                            <span class="sr-only">{{ __('admin.auth.show_password') }}</span>
                        </button>
                    </div>
                    @if(isset($errors) && $errors->has('password'))
                        <p id="login-password-error" class="admin-field-error">{{ $errors->first('password') }}</p>
                    @endif
                </div>

                <div class="flex items-center justify-between">
                    <label for="login-remember" class="flex items-center gap-2 text-xs text-slate-600">
                        <input id="login-remember"
                               type="checkbox"
                               name="remember"
                               checked
                               class="rounded border-slate-300 text-[#0090A8] focus:ring-[#0090A8]">
                        <span>{{ __('admin.auth.remember') }}</span>
                    </label>

                    <a href="{{ $frontUrl }}" class="text-xs font-semibold text-[#0090A8] hover:text-[#007B90]">
                        {{ __('admin.auth.back_to_site') }}
                    </a>
                </div>

                <button type="submit"
                        class="admin-btn admin-btn--primary admin-btn--lg w-full">
                    <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i>
                    <span>{{ __('admin.auth.submit') }}</span>
                </button>
            </form>

            <div class="mt-6 pt-5 border-t border-slate-100 flex items-center justify-center">
                <x-admin.locale-switcher variant="light" />
            </div>
        </section>

        {{-- Demo personas (never in production) --}}
        @if(!app()->isProduction())
            <details class="mt-6 bg-white/70 border border-slate-200 rounded-2xl px-5 py-4 text-xs">
                <summary class="cursor-pointer font-bold text-[#0090A8] list-none flex items-center justify-between gap-2">
                    <span class="flex items-center gap-2">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        {{ __('admin.auth.quick_access_title') }}
                    </span>
                    <i class="fa-solid fa-chevron-down text-[10px]" aria-hidden="true"></i>
                </summary>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    @foreach(['admin' => 'Admin', 'editor' => 'Editor', 'author' => 'Author', 'marketing' => 'Marketing', 'viewer' => 'Viewer (Auditor)'] as $role => $label)
                        <a href="{{ route('admin.quick-login', $role) }}"
                           class="p-2.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 hover:border-[#0090A8] text-center rounded-lg transition-all shadow-sm">
                            <span class="block font-bold text-slate-900">{{ $label }}</span>
                            <span class="block text-[10px] text-slate-500">{{ __('admin.auth.role_scope.'.$role) }}</span>
                        </a>
                    @endforeach
                </div>

                <div class="mt-4 p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-1.5 text-[10px] font-bold uppercase text-slate-500">
                        <span>{{ __('admin.auth.manual_credentials') }}</span>
                        <span class="text-[#0090A8]">{{ __('admin.auth.local_environment') }}</span>
                    </div>
                    <div class="space-y-1">
                        <div class="flex items-center justify-between text-slate-700">
                            <span class="font-bold text-slate-900">admin@db-cms.io <span class="font-normal text-slate-400">(Admin)</span></span>
                            <code class="rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[#0090A8]">secret123</code>
                        </div>
                        <div class="flex items-center justify-between text-slate-700">
                            <span class="font-bold text-slate-900">editor@db-cms.io <span class="font-normal text-slate-400">(Editor)</span></span>
                            <code class="rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[#0090A8]">secret123</code>
                        </div>
                    </div>
                </div>
            </details>
        @endif
    </main>

    <script nonce="{{ $cspNonce ?? '' }}">
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var input = document.getElementById(button.getAttribute('data-password-toggle'));
                    if (! input) { return; }

                    var show = input.type === 'password';
                    input.type = show ? 'text' : 'password';
                    button.setAttribute('aria-pressed', show ? 'true' : 'false');

                    var icon = button.querySelector('i');
                    if (icon) { icon.className = show ? 'fa-solid fa-eye-slash text-xs' : 'fa-solid fa-eye text-xs'; }
                });
            });
        });
    </script>
</body>
</html>

@php
    $cookieConsentService = app(\App\Services\Cms\CookieConsentService::class);
    $currentLocale = app()->getLocale();
    $disclosedCategories = $cookieConsentService->getDisclosedCategories($currentLocale);
    $currentPreferences = $cookieConsentService->getConsentPreferences();
    $hasServerConsent = $cookieConsentService->hasGivenConsent();

    // Copy lives in `lang/{locale}/cookies.php`: adding a language never
    // requires editing this view.
    $bannerHeading = __('cookies.banner.heading');
    $bannerMsg = __('cookies.banner.message');
    $btnAcceptAll = __('cookies.banner.accept_all');
    $btnNecessary = __('cookies.banner.necessary_only');
    $btnConfigure = __('cookies.banner.configure');
    $modalTitle = __('cookies.banner.modal_title');
    $modalDesc = __('cookies.banner.modal_desc');
    $colName = __('cookies.banner.col_cookie');
    $colProvider = __('cookies.banner.col_provider');
    $colPurpose = __('cookies.banner.col_purpose');
    $colDuration = __('cookies.banner.col_duration');
    $badgeRequired = __('cookies.banner.badge_required');
    $badgeOptional = __('cookies.banner.badge_optional');
    $btnSave = __('cookies.banner.save');
    $detailsText = __('cookies.banner.details');

    $activeTheme = (string) config('cms.theme', 'db-cyber');

    $bannerWrapClass = match($activeTheme) {
        'db-editorial' => 'fixed bottom-0 left-0 right-0 z-50 p-4 md:p-6 bg-stone-100/95 backdrop-blur-xl border-t border-stone-300 shadow-xl font-serif text-stone-900',
        'db-enterprise' => 'fixed bottom-0 left-0 right-0 z-50 p-4 md:p-6 bg-white/95 backdrop-blur-xl border-t border-slate-200 shadow-2xl font-sans text-slate-900',
        'db-minimal' => 'fixed bottom-0 left-0 right-0 z-50 p-4 md:p-6 bg-white border-t-2 border-black font-mono text-black',
        'db-sunset' => 'fixed bottom-0 left-0 right-0 z-50 p-4 md:p-6 bg-stone-950/95 backdrop-blur-xl border-t border-rose-500/40 shadow-[0_-15px_35px_rgba(0,0,0,0.8)] font-sans text-white',
        'db-brutalist' => 'fixed bottom-0 left-0 right-0 z-50 p-4 md:p-6 bg-yellow-300 border-t-4 border-black font-mono text-black shadow-[0_-8px_0px_0px_#000]',
        default => 'fixed bottom-0 left-0 right-0 z-50 p-4 md:p-6 bg-[#0E0E0E]/95 backdrop-blur-xl border-t-2 border-[#00FF99]/40 shadow-[0_-15px_35px_rgba(0,0,0,0.8)] font-mono text-white',
    };

    $bannerBtnConfigClass = match($activeTheme) {
        'db-editorial' => 'px-3.5 py-2 rounded border border-stone-300 bg-white hover:bg-stone-50 text-stone-700 text-xs font-medium',
        'db-enterprise' => 'px-3.5 py-2 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold',
        'db-minimal' => 'px-3.5 py-2 border border-black bg-white hover:bg-black hover:text-white text-black text-xs uppercase tracking-wider',
        'db-sunset' => 'px-3.5 py-2 rounded-lg border border-rose-500/30 bg-stone-900 hover:border-rose-400 text-gray-200 text-xs font-semibold',
        'db-brutalist' => 'px-3.5 py-2 border-2 border-black bg-white hover:bg-black hover:text-white text-black text-xs font-black shadow-[2px_2px_0px_0px_#000]',
        default => 'px-3.5 py-2 rounded-lg border border-gray-700 bg-[#1A1A1A] hover:border-[#00FF99] hover:text-[#00FF99] text-gray-300 transition-colors text-xs font-bold',
    };

    $bannerBtnNecessaryClass = match($activeTheme) {
        'db-editorial' => 'px-3.5 py-2 rounded border border-stone-300 bg-stone-200/60 hover:bg-stone-300 text-stone-800 text-xs font-medium',
        'db-enterprise' => 'px-3.5 py-2 rounded-lg border border-slate-300 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold',
        'db-minimal' => 'px-3.5 py-2 border border-black bg-neutral-100 hover:bg-neutral-200 text-black text-xs uppercase tracking-wider',
        'db-sunset' => 'px-3.5 py-2 rounded-lg border border-gray-700 bg-stone-900/80 hover:bg-stone-800 text-gray-300 text-xs font-semibold',
        'db-brutalist' => 'px-3.5 py-2 border-2 border-black bg-white hover:bg-gray-100 text-black text-xs font-black shadow-[2px_2px_0px_0px_#000]',
        default => 'px-3.5 py-2 rounded-lg border border-gray-700 bg-[#161616] hover:bg-[#202020] text-gray-300 hover:text-white transition-colors text-xs font-bold',
    };

    $bannerBtnAcceptClass = match($activeTheme) {
        'db-editorial' => 'bg-stone-900 text-white hover:bg-stone-800 text-xs py-2 px-4 rounded font-sans',
        'db-enterprise' => 'bg-indigo-600 hover:bg-indigo-700 text-white text-xs py-2 px-4 rounded-lg font-semibold shadow-sm',
        'db-minimal' => 'bg-black text-white hover:bg-neutral-800 text-xs py-2 px-4 rounded-none uppercase tracking-widest',
        'db-sunset' => 'bg-gradient-to-r from-rose-500 to-amber-500 hover:from-rose-600 hover:to-amber-600 text-white text-xs py-2 px-4 rounded-lg font-semibold shadow-md',
        'db-brutalist' => 'bg-[#00F0FF] text-black border-2 border-black hover:bg-cyan-300 text-xs py-2 px-4 font-black shadow-[3px_3px_0px_0px_#000]',
        default => 'btn-neon text-xs py-2 px-4 shadow-[0_0_15px_rgba(0,255,153,0.3)]',
    };

    $modalCardClass = match($activeTheme) {
        'db-editorial' => 'bg-white border border-stone-300 rounded-lg max-w-3xl w-full max-h-[90vh] flex flex-col shadow-2xl font-serif text-stone-900 overflow-hidden',
        'db-enterprise' => 'bg-white border border-slate-200 rounded-xl max-w-3xl w-full max-h-[90vh] flex flex-col shadow-2xl font-sans text-slate-900 overflow-hidden',
        'db-minimal' => 'bg-white border-2 border-black rounded-none max-w-3xl w-full max-h-[90vh] flex flex-col font-mono text-black overflow-hidden',
        'db-sunset' => 'bg-stone-900 border border-rose-500/40 rounded-2xl max-w-3xl w-full max-h-[90vh] flex flex-col shadow-[0_0_50px_rgba(244,63,94,0.15)] font-sans text-white overflow-hidden',
        'db-brutalist' => 'bg-yellow-50 border-4 border-black rounded-none max-w-3xl w-full max-h-[90vh] flex flex-col shadow-[10px_10px_0px_0px_#000] font-mono text-black overflow-hidden',
        default => 'bg-[#141414] border-2 border-[#00FF99]/60 rounded-2xl max-w-3xl w-full max-h-[90vh] flex flex-col shadow-[0_0_50px_rgba(0,255,153,0.2)] font-mono text-white overflow-hidden',
    };

    $modalHeaderClass = match($activeTheme) {
        'db-editorial' => 'p-5 border-b border-stone-200 flex items-center justify-between bg-stone-50',
        'db-enterprise' => 'p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50',
        'db-minimal' => 'p-5 border-b-2 border-black flex items-center justify-between bg-white',
        'db-sunset' => 'p-5 border-b border-rose-500/20 flex items-center justify-between bg-stone-950/60',
        'db-brutalist' => 'p-5 border-b-4 border-black flex items-center justify-between bg-yellow-300',
        default => 'p-5 border-b border-gray-800 flex items-center justify-between bg-[#181818]/60',
    };

    $modalCategoryBoxClass = match($activeTheme) {
        'db-editorial' => 'p-4 rounded border border-stone-200 bg-stone-50/50 space-y-3',
        'db-enterprise' => 'p-4 rounded-xl border border-slate-200 bg-slate-50/50 space-y-3',
        'db-minimal' => 'p-4 border border-black bg-white space-y-3',
        'db-sunset' => 'p-4 rounded-xl border border-stone-800 bg-stone-950/50 space-y-3',
        'db-brutalist' => 'p-4 border-2 border-black bg-white space-y-3',
        default => 'p-4 rounded-xl bg-[#1A1A1A] border border-gray-800 space-y-3',
    };

    $modalAccentText = match($activeTheme) {
        'db-editorial' => 'text-amber-800 font-bold',
        'db-enterprise' => 'text-indigo-600 font-bold',
        'db-minimal' => 'text-black font-bold',
        'db-sunset' => 'text-rose-400 font-bold',
        'db-brutalist' => 'text-black font-black',
        default => 'text-[#00FF99]',
    };

    $modalBadgeRequired = match($activeTheme) {
        'db-editorial' => 'px-2 py-0.5 rounded bg-stone-200 text-stone-800 text-[10px] font-bold',
        'db-enterprise' => 'px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-200 text-[10px] font-semibold',
        'db-minimal' => 'px-2 py-0.5 border border-black text-black text-[10px] uppercase font-bold',
        'db-sunset' => 'px-2 py-0.5 rounded bg-rose-500/20 text-rose-300 border border-rose-500/40 text-[10px] font-bold',
        'db-brutalist' => 'px-2 py-0.5 border border-black bg-yellow-300 text-black text-[10px] font-black',
        default => 'px-2 py-0.5 rounded bg-[#00FF99]/20 text-[#00FF99] border border-[#00FF99]/40 text-[10px] font-bold',
    };

    $modalCheckboxClass = match($activeTheme) {
        'db-editorial' => 'rounded border-stone-300 text-amber-800 focus:ring-amber-800 h-4 w-4 cursor-pointer',
        'db-enterprise' => 'rounded border-slate-300 text-indigo-600 focus:ring-indigo-600 h-4 w-4 cursor-pointer',
        'db-minimal' => 'rounded-none border-black text-black focus:ring-black h-4 w-4 cursor-pointer',
        'db-sunset' => 'rounded border-rose-500/40 text-rose-500 focus:ring-rose-400 h-4 w-4 bg-stone-900 cursor-pointer',
        'db-brutalist' => 'rounded-none border-2 border-black text-black focus:ring-black h-4 w-4 cursor-pointer',
        default => 'rounded border-gray-700 text-[#00FF99] focus:ring-[#00FF99] h-4 w-4 bg-[#121212] cursor-pointer',
    };

    $modalFooterClass = match($activeTheme) {
        'db-editorial' => 'p-5 border-t border-stone-200 bg-stone-50 flex flex-wrap items-center justify-between gap-3',
        'db-enterprise' => 'p-5 border-t border-slate-200 bg-slate-50 flex flex-wrap items-center justify-between gap-3',
        'db-minimal' => 'p-5 border-t-2 border-black bg-white flex flex-wrap items-center justify-between gap-3',
        'db-sunset' => 'p-5 border-t border-rose-500/20 bg-stone-950/60 flex flex-wrap items-center justify-between gap-3',
        'db-brutalist' => 'p-5 border-t-4 border-black bg-yellow-200 flex flex-wrap items-center justify-between gap-3',
        default => 'p-5 border-t border-gray-800 bg-[#181818]/60 flex flex-wrap items-center justify-between gap-3',
    };
@endphp

<div x-data="{
        showBanner: false,
        showSettings: false,
        expandedCategory: null,
        preferences: {
            necessary: true,
            @foreach($disclosedCategories as $cat)
                @if($cat['key'] !== 'necessary')
                    '{{ $cat['key'] }}': {{ $currentPreferences[$cat['key']] ? 'true' : ($cat['default'] ? 'true' : 'false') }},
                @endif
            @endforeach
        },
        getSavedConsent() {
            var parts = ('; ' + document.cookie).split('; cms_cookie_consent=');
            if (parts.length >= 2) {
                var val = parts.pop().split(';').shift();
                if (val && val.trim().length > 0) return val.trim();
            }
            try {
                var local = localStorage.getItem('cms_cookie_consent');
                if (local && local.trim().length > 0) return local.trim();
            } catch(e) {}
            return null;
        },
        init() {
            var saved = this.getSavedConsent();
            var hasServer = {{ $hasServerConsent ? 'true' : 'false' }};
            if (!saved && !hasServer) {
                this.showBanner = true;
            } else {
                this.showBanner = false;
                if (saved) {
                    try {
                        var parsed = JSON.parse(decodeURIComponent(saved));
                        if (parsed && typeof parsed === 'object') {
                            this.preferences = Object.assign({}, this.preferences, parsed, { necessary: true });
                        }
                    } catch(e) {}
                }
            }
            window.addEventListener('open-cookie-settings', () => {
                this.showSettings = true;
            });
        },
        saveConsent(prefs) {
            prefs.necessary = true;
            var jsonStr = JSON.stringify(prefs);
            document.cookie = 'cms_cookie_consent=' + encodeURIComponent(jsonStr) + '; path=/; max-age=' + (60*60*24*365) + '; SameSite=Lax';
            try {
                localStorage.setItem('cms_cookie_consent', jsonStr);
            } catch(e) {}
            window.dispatchEvent(new CustomEvent('cookie-consent-updated', { detail: prefs }));
            this.showBanner = false;
            this.showSettings = false;
        },
        acceptAll() {
            var all = { necessary: true };
            @foreach($disclosedCategories as $cat)
                all['{{ $cat['key'] }}'] = true;
            @endforeach
            this.preferences = Object.assign({}, all);
            this.saveConsent(all);
        },
        acceptNecessary() {
            var nec = { necessary: true };
            @foreach($disclosedCategories as $cat)
                @if($cat['key'] !== 'necessary')
                    nec['{{ $cat['key'] }}'] = false;
                @endif
            @endforeach
            this.preferences = Object.assign({}, nec);
            this.saveConsent(nec);
        },
        saveCustom() {
            this.saveConsent(this.preferences);
        },
        toggleCategoryDetails(catKey) {
            this.expandedCategory = (this.expandedCategory === catKey) ? null : catKey;
        }
    }"
    class="font-mono text-xs select-none">

    @if(!$hasServerConsent)
    <!-- Floating Cookie Banner -->
    <div x-show="showBanner"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-6"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-6"
         class="{{ $bannerWrapClass }}"
         style="display: none;">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="space-y-1.5 max-w-3xl">
                <div class="flex items-center gap-2">
                    <span class="text-base {{ $modalAccentText }}"><i class="fa-solid fa-cookie-bite"></i></span>
                    <h3 class="text-sm font-bold tracking-wide">{{ $bannerHeading }}</h3>
                </div>
                <p class="opacity-80 text-xs leading-relaxed">
                    {{ $bannerMsg }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 w-full md:w-auto justify-end">
                <button type="button"
                        @click="showSettings = true"
                        class="{{ $bannerBtnConfigClass }}">
                    {{ $btnConfigure }}
                </button>

                <button type="button"
                        @click="acceptNecessary()"
                        class="{{ $bannerBtnNecessaryClass }}">
                    {{ $btnNecessary }}
                </button>

                <button type="button"
                        @click="acceptAll()"
                        class="{{ $bannerBtnAcceptClass }}">
                    {{ $btnAcceptAll }} &rarr;
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- GDPR Configuration Modal -->
    <div x-show="showSettings"
         @keydown.escape.window="showSettings = false"
         @click.self="showSettings = false"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-black/40 backdrop-blur-md flex items-center justify-center p-4 sm:p-6"
         style="display: none;">
        
        <div class="{{ $modalCardClass }}"
             @click.stop
             @click.outside="showSettings = false">

            <!-- Modal Header -->
            <div class="{{ $modalHeaderClass }}">
                <div class="flex items-center gap-2">
                    <span class="text-lg {{ $modalAccentText }}"><i class="fa-solid fa-gear"></i></span>
                    <h2 class="text-sm sm:text-base font-bold tracking-wide">{{ $modalTitle }}</h2>
                </div>
                <button type="button" @click="showSettings = false" class="opacity-60 hover:opacity-100 text-xl font-bold p-1">&times;</button>
            </div>

            <!-- Modal Body (Categories & Disclosed Cookies) -->
            <div class="p-6 overflow-y-auto space-y-6 flex-1 opacity-90">
                <p class="text-xs opacity-75 leading-relaxed">
                    {{ $modalDesc }}
                </p>

                <div class="space-y-4">
                    @foreach($disclosedCategories as $cat)
                        <div class="{{ $modalCategoryBoxClass }}">
                            <div class="flex items-start justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-bold text-xs sm:text-sm">{{ $cat['title'] }}</h4>
                                        @if($cat['required'])
                                            <span class="{{ $modalBadgeRequired }}">
                                                {{ $badgeRequired }}
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded opacity-60 bg-current/10 text-[10px]">
                                                {{ $badgeOptional }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-[11px] opacity-75 leading-relaxed">{{ $cat['description'] }}</p>
                                </div>

                                <div>
                                    @if($cat['required'])
                                        <input type="checkbox" checked disabled class="{{ $modalCheckboxClass }} cursor-not-allowed opacity-80">
                                    @else
                                        <input type="checkbox"
                                               x-model="preferences['{{ $cat['key'] }}']"
                                               class="{{ $modalCheckboxClass }}">
                                    @endif
                                </div>
                            </div>

                            <!-- Expandable specific cookies list -->
                            <div>
                                <button type="button"
                                        @click="toggleCategoryDetails('{{ $cat['key'] }}')"
                                        class="text-[11px] {{ $modalAccentText }} hover:underline flex items-center gap-1">
                                    <span x-text="expandedCategory === '{{ $cat['key'] }}' ? '▼' : '►'"></span>
                                    <span>{{ $detailsText }} ({{ count($cat['cookies']) }})</span>
                                </button>

                                <div x-show="expandedCategory === '{{ $cat['key'] }}'" x-collapse class="mt-2 pt-2 border-t border-current/10">
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-[10px] opacity-90">
                                            <thead>
                                                <tr class="opacity-60 uppercase border-b border-current/10">
                                                    <th class="py-1 px-2">{{ $colName }}</th>
                                                    <th class="py-1 px-2">{{ $colProvider }}</th>
                                                    <th class="py-1 px-2">{{ $colPurpose }}</th>
                                                    <th class="py-1 px-2">{{ $colDuration }}</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-current/10">
                                                @foreach($cat['cookies'] as $ck)
                                                    <tr>
                                                        <td class="py-1.5 px-2 font-bold {{ $modalAccentText }}">{{ $ck['name'] }}</td>
                                                        <td class="py-1.5 px-2">{{ $ck['provider'] }}</td>
                                                        <td class="py-1.5 px-2 opacity-75">{{ $ck['purpose'] }}</td>
                                                        <td class="py-1.5 px-2 opacity-75 whitespace-nowrap">{{ $ck['duration'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="{{ $modalFooterClass }}">
                <button type="button"
                        @click="acceptNecessary()"
                        class="opacity-70 hover:opacity-100 text-xs underline">
                    {{ $btnNecessary }}
                </button>

                <div class="flex items-center gap-2">
                    <button type="button"
                            @click="saveCustom()"
                            class="{{ $bannerBtnConfigClass }}">
                        {{ $btnSave }}
                    </button>

                    <button type="button"
                            @click="acceptAll()"
                            class="{{ $bannerBtnAcceptClass }}">
                        {{ $btnAcceptAll }} &rarr;
                    </button>
                </div>
            </div>

        </div>
    </div>

</div>

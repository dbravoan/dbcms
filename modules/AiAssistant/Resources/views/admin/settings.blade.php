@extends('admin.layout')

@section('title', __('admin.page_titles.ai_settings'))

@section('content')
@php
    /** @var \Modules\AiAssistant\Contracts\AiConfigBridgeInterface $bridge */
    $bridge = app(\Modules\AiAssistant\Contracts\AiConfigBridgeInterface::class);
    $catalog = $bridge->getProvidersCatalog();
    $activeProvider = $bridge->getActiveProvider();
    $modalitiesMatrix = $bridge->getModalitiesMatrix();

    $initialShowKey = [];
    $initialForms = [];
    foreach ($catalog as $id => $prov) {
        $initialShowKey[$id] = false;
        $initialForms[$id] = [
            'api_key' => '',
            'api_key_id'=>'',
            'api_key_secret'=>'',
            'base_url' => ! empty($prov['base_url']) ? $prov['base_url'] : ($prov['default_base_url'] ?? ''),
            'model' => $prov['current_model'] ?? '',
            'image_model' => $prov['image_model'] ?? '',
            'video_model' => $prov['video_model'] ?? '',
            'custom_model_text' => '',
        ];
    }
@endphp

<div class="space-y-6 max-w-6xl mx-auto pb-16 font-mono text-xs" x-data="aiProviderHub()">
    <!-- Breadcrumb Header -->
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-2 text-gray-400 text-xs">
            <a href="{{ route('admin.dashboard') }}" class="hover:text-white transition-colors">{{ __('admin.ai.panel') }}</a>
            <span>/</span>
            <a href="{{ route('admin.modules.index') }}" class="hover:text-white transition-colors">{{ __('admin.ai.modules') }}</a>
            <span>/</span>
            <a href="{{ route('admin.ai_assistant.workbench') }}" class="hover:text-white transition-colors">{{ __('admin.ai.copilot') }}</a>
            <span>/</span>
            <span class="text-[#00FF99] font-bold">{{ __('admin.ai.models_apis') }}</span>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.ai_assistant.workbench') }}" class="px-3 py-1.5 bg-[#1A1A1A] hover:bg-gray-800 border border-gray-700 hover:border-[#00FF99] text-gray-300 hover:text-[#00FF99] rounded-lg transition-all text-xs font-bold inline-flex items-center gap-2">
                <i class="fa-solid fa-wand-magic-sparkles text-purple-400"></i>
                <span>{{ __('admin.ai.go_workbench') }}</span>
            </a>
        </div>
    </div>

    <!-- Active Provider Hero Status -->
    <div class="cyber-card p-6 flex flex-col md:flex-row md:items-center justify-between gap-6 border-l-4 border-l-[#00FF99] bg-[#00FF99]/5">
        <div class="space-y-2">
            <div class="flex items-center gap-3 flex-wrap">
                <div class="w-10 h-10 rounded-xl bg-[#1A1A1A] border border-gray-700 flex items-center justify-center text-lg text-[#00FF99]">
                    <i class="fa-solid fa-plug-circle-check"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-white tracking-wide">{{ __('admin.ai.title') }}</h2>
                    <p class="text-[11px] text-gray-400">
                        Proveedor predeterminado activo:
                        <span class="text-[#00FF99] font-bold uppercase" x-text="activeProvider"></span>
                        — <span class="text-gray-300" x-text="catalog[activeProvider]?.current_model || 'Predeterminado'"></span>
                    </p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="button"
                    @click="testProvider(activeProvider)"
                    :disabled="testingProvider === activeProvider + ':text'"
                    class="btn-neon">
                <i class="fa-solid" :class="testingProvider === activeProvider + ':text' ? 'fa-spinner fa-spin' : 'fa-bolt'"></i>
                <span x-text="testingProvider === activeProvider + ':text' ? 'Comprobando catálogo...' : 'Comprobar proveedor activo'"></span>
            </button>
        </div>
    </div>

    <!-- Alert / Toast -->
    <template x-if="toastMessage">
        <div class="p-4 rounded-xl border flex items-center justify-between transition-all"
             :class="toastType === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' : 'bg-rose-500/10 border-rose-500/30 text-rose-300'">
            <div class="flex items-center gap-3">
                <i class="fa-solid" :class="toastType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'"></i>
                <span class="text-xs font-medium" x-text="toastMessage"></span>
            </div>
            <button type="button" @click="toastMessage = null" class="text-xs opacity-70 hover:opacity-100">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </template>

    <!-- Multimodal Defaults Matrix -->
    <div class="cyber-card p-5 border border-purple-500/30 bg-[#16121E]/60 space-y-4">
        <div class="flex items-center justify-between border-b border-gray-800 pb-3 flex-wrap gap-2">
            <div class="flex items-center gap-2.5">
                <i class="fa-solid fa-layer-group text-purple-400 text-sm"></i>
                <h3 class="text-xs font-bold text-white tracking-wide uppercase">Orquestación Multimodal — Motores Activos</h3>
            </div>
            <span class="text-[10px] text-gray-400">dbCMS asigna automáticamente la tarea al motor especializado según la modalidad</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Text / LLM -->
            <div class="p-3.5 rounded-xl bg-[#121212] border border-gray-800 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-blue-400 flex items-center gap-1.5">
                        <i class="fa-solid fa-align-left"></i> Texto / LLM
                    </span>
                    <span class="text-[9px] px-1.5 py-0.5 rounded bg-blue-500/10 text-blue-300 font-mono uppercase" x-text="matrix?.text?.active || activeProvider"></span>
                </div>
                <select @change="setDefault($event.target.value, 'text')"
                        class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-2.5 py-1.5 text-xs text-white focus:border-[#00FF99] focus:outline-none">
                    @foreach($modalitiesMatrix['text']['providers'] ?? [] as $p)
                        @php
                            $pId = is_array($p) ? ($p['id'] ?? '') : (string) $p;
                            $pName = is_array($p) ? ($p['name'] ?? ucfirst($pId)) : ($catalog[$pId]['name'] ?? ucfirst($pId));
                        @endphp
                        <option value="{{ $pId }}" :selected="(matrix?.text?.active || activeProvider) === '{{ $pId }}'">
                            {{ $pName }}
                        </option>
                    @endforeach
                </select>
                <p class="text-[10px] text-gray-500">Redacción editorial, humanización, SEO y análisis.</p>
            </div>

            <!-- Image Generation -->
            <div class="p-3.5 rounded-xl bg-[#121212] border border-gray-800 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-emerald-400 flex items-center gap-1.5">
                        <i class="fa-solid fa-image"></i> Generación de Imágenes
                    </span>
                    <span class="text-[9px] px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-300 font-mono uppercase" x-text="matrix?.image?.active"></span>
                </div>
                <select @change="setDefault($event.target.value, 'image')"
                        class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-2.5 py-1.5 text-xs text-white focus:border-[#00FF99] focus:outline-none">
                    @foreach($modalitiesMatrix['image']['providers'] ?? [] as $p)
                        @php
                            $pId = is_array($p) ? ($p['id'] ?? '') : (string) $p;
                            $pName = is_array($p) ? ($p['name'] ?? ucfirst($pId)) : ($catalog[$pId]['name'] ?? ucfirst($pId));
                        @endphp
                        <option value="{{ $pId }}" :selected="matrix?.image?.active === '{{ $pId }}'">
                            {{ $pName }}
                        </option>
                    @endforeach
                </select>
                <p class="text-[10px] text-gray-500">Magnific AI (Ultra-fotorrealismo), OpenAI DALL-E 3, Gemini Imagen 3.</p>
            </div>

            <!-- Video Generation -->
            <div class="p-3.5 rounded-xl bg-[#121212] border border-gray-800 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold text-purple-400 flex items-center gap-1.5">
                        <i class="fa-solid fa-video"></i> Generación de Vídeo
                    </span>
                    <span class="text-[9px] px-1.5 py-0.5 rounded bg-purple-500/10 text-purple-300 font-mono uppercase" x-text="matrix?.video?.active"></span>
                </div>
                <select @change="setDefault($event.target.value, 'video')"
                        class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-2.5 py-1.5 text-xs text-white focus:border-[#00FF99] focus:outline-none">
                    @foreach($modalitiesMatrix['video']['providers'] ?? [] as $p)
                        @php
                            $pId = is_array($p) ? ($p['id'] ?? '') : (string) $p;
                            $pName = is_array($p) ? ($p['name'] ?? ucfirst($pId)) : ($catalog[$pId]['name'] ?? ucfirst($pId));
                        @endphp
                        <option value="{{ $pId }}" :selected="matrix?.video?.active === '{{ $pId }}'">
                            {{ $pName }}
                        </option>
                    @endforeach
                </select>
                <p class="text-[10px] text-gray-500">Higgsfield AI (Cine/DoP), Magnific AI (Motion).</p>
            </div>
        </div>
    </div>

    <!-- Provider Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($catalog as $pId => $prov)
            <div class="cyber-card p-5 flex flex-col justify-between space-y-4 border transition-all hover:border-gray-600"
                 :class="activeProvider === '{{ $pId }}' ? 'border-[#00FF99]/60 shadow-[0_0_20px_rgba(0,255,153,0.1)]' : 'border-gray-800'">

                <!-- Provider Header -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2.5">
                            <i class="{{ $prov['icon'] }} text-base text-gray-300"></i>
                            <h3 class="text-xs font-bold text-white tracking-wide">{{ $prov['name'] }}</h3>
                        </div>
                        <span class="text-[9px] font-bold px-2 py-0.5 rounded border {{ $prov['badge_color'] }}">
                            {{ $prov['badge'] }}
                        </span>
                    </div>
                    <p class="text-[11px] text-gray-400 line-clamp-2">{{ $prov['description'] }}</p>
                    <p class="text-[10px] text-gray-400" x-text="connectionLabel(catalog['{{ $pId }}'].connection_state)"></p>
                    <div class="flex items-center gap-1.5 flex-wrap pt-1">
                        @foreach($prov['modalities'] ?? ['text'] as $mod)
                            @if($mod === 'text')
                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded border border-blue-500/30 bg-blue-500/10 text-blue-400">
                                    <i class="fa-solid fa-align-left mr-0.5"></i> TEXTO
                                </span>
                            @elseif($mod === 'image')
                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded border border-emerald-500/30 bg-emerald-500/10 text-emerald-400">
                                    <i class="fa-solid fa-image mr-0.5"></i> IMAGEN
                                </span>
                            @elseif($mod === 'video')
                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded border border-purple-500/30 bg-purple-500/10 text-purple-400">
                                    <i class="fa-solid fa-video mr-0.5"></i> VÍDEO
                                </span>
                            @endif
                        @endforeach
                    </div>
                </div>

                <x-admin.button :href="route('admin.modules.guides.show', ['identifier'=>'ai_assistant','guide'=>$pId])" size="sm" icon="fa-solid fa-book-open">Cómo configurar {{ $prov['name'] }}</x-admin.button>

                <!-- Provider Specific Form Fields -->
                <div class="space-y-3 pt-2 border-t border-gray-800/80">
                    <!-- API Key Field (only for providers that require one) -->
                    @if($pId === 'higgsfield')
                        <x-admin.input id="higgsfield-api-key-id" name="higgsfield_api_key_id" label="Higgsfield API Key ID" type="password" x-model="forms['higgsfield'].api_key_id" :placeholder="$prov['has_api_key_id'] ? 'ID guardado; vacío lo conserva' : 'Introduce APIKEY_ID'" />
                        <x-admin.input id="higgsfield-api-key-secret" name="higgsfield_api_key_secret" label="Higgsfield API Key Secret" type="password" x-model="forms['higgsfield'].api_key_secret" :placeholder="$prov['has_api_key_secret'] ? 'Secreto guardado; vacío lo conserva' : 'Introduce APIKEY_SECRET'" />
                        <p class="admin-field-hint">Los dos campos pertenecen al mismo par. Las credenciales anteriores siguen disponibles mientras no las sustituyas.</p>
                    @elseif($prov['requires_api_key'])
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-[10px] uppercase font-bold text-gray-400">{{ __('admin.ai.api_key') }}</label>
                            @if(!empty($prov['doc_url']))
                            <a href="{{ $prov['doc_url'] }}" target="_blank" class="text-[10px] text-[#00FF99] hover:underline flex items-center gap-1">
                                <span>{{ __('admin.ai.get_key') }}</span>
                                <i class="fa-solid fa-arrow-up-right-from-square text-[8px]"></i>
                            </a>
                            @endif
                        </div>
                        <div class="relative">
                            <input :type="showKey['{{ $pId }}'] ? 'text' : 'password'"
                                   x-model="forms['{{ $pId }}'].api_key"
                                   placeholder="{{ $prov['has_key'] ? '•••••••• (Guardada)' : 'Introduce tu API Key...' }}"
                                   class="w-full bg-[#121212] border border-gray-800 rounded-lg px-3 py-2 pr-9 text-xs text-white placeholder-gray-600 focus:border-[#00FF99] focus:outline-none">
                            <button type="button"
                                    @click="showKey['{{ $pId }}'] = !showKey['{{ $pId }}']"
                                    class="absolute right-2.5 top-2.5 text-gray-500 hover:text-gray-300">
                                <i class="fa-solid" :class="showKey['{{ $pId }}'] ? 'fa-eye-slash' : 'fa-eye'"></i>
                            </button>
                        </div>
                        @if($pId === 'custom')
                        <div class="mt-2">
                            <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1">{{ __('admin.ai.endpoint') }}</label>
                            <input type="text"
                                   x-model="forms['{{ $pId }}'].base_url"
                                   placeholder="{{ __('admin.ai.ph_endpoint') }}"
                                   class="w-full bg-[#121212] border border-gray-800 rounded-lg px-3 py-2 text-xs text-white placeholder-gray-600 focus:border-[#00FF99] focus:outline-none">
                        </div>
                        @endif
                    </div>
                    @else
                    <!-- Local / self-hosted endpoint -->
                    <div>
                        <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1">{{ __('admin.ai.endpoint') }}</label>
                        <input type="text"
                               x-model="forms['{{ $pId }}'].base_url"
                               placeholder="{{ __('admin.ai.ph_endpoint') }}"
                               class="w-full bg-[#121212] border border-gray-800 rounded-lg px-3 py-2 text-xs text-white placeholder-gray-600 focus:border-[#00FF99] focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1">{{ __('admin.ai.bearer') }}</label>
                        <input :type="showKey['{{ $pId }}'] ? 'text' : 'password'"
                               x-model="forms['{{ $pId }}'].api_key"
                               placeholder="Token si se requiere..."
                               class="w-full bg-[#121212] border border-gray-800 rounded-lg px-3 py-2 text-xs text-white placeholder-gray-600 focus:border-[#00FF99] focus:outline-none">
                    </div>
                    @endif

                    <!-- Model Dropdown (Live provider catalog) -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-[10px] uppercase font-bold text-gray-400">{{ __('admin.ai.model') }}</label>
                            <button type="button"
                                    @click="loadModels('{{ $pId }}', true)"
                                    :disabled="loadingModels['{{ $pId }}']"
                                    class="text-[10px] text-[#00FF99] hover:underline flex items-center gap-1 disabled:opacity-50 transition-opacity"
                                    title="{{ __('admin.ai.request_models') }}">
                                <i class="fa-solid" :class="loadingModels['{{ $pId }}'] ? 'fa-spinner fa-spin' : 'fa-rotate'"></i>
                                <span x-text="loadingModels['{{ $pId }}'] ? 'Actualizando...' : 'Actualizar modelos'"></span>
                            </button>
                        </div>
                        <select x-model="forms['{{ $pId }}'].model"
                                class="w-full bg-[#121212] border border-gray-800 rounded-lg px-3 py-2 text-xs text-white focus:border-[#00FF99] focus:outline-none">
                            <template x-for="m in models['{{ $pId }}']" :key="m.id">
                                <option :value="m.id" x-text="m.name + (m.experimental ? ' (experimental)' : '')"></option>
                            </template>
                            <option value="__custom__">{{ __('admin.ai.other_model') }}</option>
                        </select>
                        <p class="text-[10px] text-gray-500 mt-1" x-show="modelsMeta['{{ $pId }}']" x-text="modelsMeta['{{ $pId }}']"></p>
                    </div>

                    @foreach(['image' => 'Modelo de imagen', 'video' => 'Modelo de vídeo'] as $modality => $label)
                        @if(in_array($modality, $prov['modalities'] ?? [], true))
                        <div>
                            <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1">{{ $label }}</label>
                            <input type="text" x-model="forms['{{ $pId }}'].{{ $modality }}_model" placeholder="Identificador del modelo (opcional)"
                                   class="w-full bg-[#121212] border border-gray-800 rounded-lg px-3 py-2 text-xs text-white focus:border-[#00FF99] focus:outline-none">
                        </div>
                        @endif
                    @endforeach

                    <!-- Custom Model Free Text if chosen -->
                    <div x-show="forms['{{ $pId }}'].model === '__custom__'" style="display: none;">
                        <label class="block text-[10px] uppercase font-bold text-purple-400 mb-1">{{ __('admin.ai.model_name') }}</label>
                        <input type="text"
                               x-model="forms['{{ $pId }}'].custom_model_text"
                               placeholder="{{ __('admin.ai.ph_model') }}"
                               class="w-full bg-[#121212] border border-purple-500/50 rounded-lg px-3 py-2 text-xs text-white placeholder-gray-600 focus:border-purple-400 focus:outline-none">
                    </div>
                </div>

                <!-- Test Connection Feedback for this card -->
                @foreach(['text'=>'Texto', 'image'=>'Imagen', 'video'=>'Vídeo'] as $probeModality=>$probeLabel)
                    <template x-if="testResults['{{ $pId }}:{{ $probeModality }}']">
                        <div class="admin-field-hint" role="status">
                            <strong>{{ $probeLabel }}:</strong>
                            <span x-text="testResults['{{ $pId }}:{{ $probeModality }}'].message"></span>
                        </div>
                    </template>
                @endforeach

                <!-- Card Actions -->
                <div class="space-y-2 pt-3 border-t border-gray-800">
                    <div class="flex items-center gap-2">
                        <button type="button"
                                @click="saveProvider('{{ $pId }}')"
                                :disabled="savingProvider === '{{ $pId }}'"
                                class="flex-1 px-3 py-2 rounded-lg bg-gray-800 hover:bg-gray-700 text-white font-bold text-xs transition-colors flex items-center justify-center gap-1.5">
                            <i class="fa-solid" :class="savingProvider === '{{ $pId }}' ? 'fa-spinner fa-spin' : 'fa-floppy-disk'"></i>
                            <span x-text="savingProvider === '{{ $pId }}' ? 'Guardando...' : 'Guardar'"></span>
                        </button>

                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['text'=>'Texto', 'image'=>'Imagen', 'video'=>'Vídeo'] as $probeModality=>$probeLabel)
                            <x-admin.button size="sm" x-on:click="testProvider('{{ $pId }}', '{{ $probeModality }}')" x-bind:disabled="testingProvider === '{{ $pId }}:{{ $probeModality }}'" title="Consulta de autenticación o catálogo sin generación">Comprobar {{ $probeLabel }}</x-admin.button>
                        @endforeach
                    </div>

                    <div>
                        <button type="button"
                                @click="setDefault('{{ $pId }}')"
                                :disabled="activeProvider === '{{ $pId }}' || settingDefault === '{{ $pId }}'"
                                class="w-full py-1.5 px-3 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-2"
                                :class="activeProvider === '{{ $pId }}' ? 'bg-[#00FF99]/20 text-[#00FF99] border border-[#00FF99]/40 cursor-default' : 'bg-transparent hover:bg-gray-800/60 border border-gray-800 text-gray-400 hover:text-white'">
                            <span x-show="activeProvider === '{{ $pId }}'">{{ __('admin.ai.default') }}</span>
                            <span x-show="activeProvider !== '{{ $pId }}'">{{ __('admin.ai.set_default') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
function aiProviderHub() {
    return {
        activeProvider: '{{ $activeProvider }}',
        catalog: @json($catalog),
        matrix: @json($modalitiesMatrix),
        models: {},
        loadingModels: {},
        modelsMeta: {},
        showKey: @json($initialShowKey),
        forms: @json($initialForms),
        testingProvider: null,
        savingProvider: null,
        settingDefault: null,
        testResults: {},
        connectionEpochs: {},
        toastMessage: null,
        toastType: 'success',

        connectionLabel(state) {
            return {unconfigured: 'Sin configurar', configured: 'Configurado · conexión sin verificar', verified: 'Conexión verificada mediante catálogo', unavailable: 'Conexión indisponible'}[state] || 'Estado desconocido';
        },

        init() {
            Object.keys(this.catalog).forEach((id) => {
                const registered = this.catalog[id].registered_models || [];
                this.models[id] = registered.map((m) => ({ id: m.id, name: m.name, experimental: !!m.experimental }));
                this.loadingModels[id] = false;
                this.modelsMeta[id] = registered.length
                    ? `${registered.length} modelos · ${this.catalog[id].catalog_source === 'documented' ? 'catálogo documentado' : 'catálogo del proveedor'}`
                    : 'Sin conexión — guarda las credenciales y pulsa Actualizar modelos';

                if (this.forms[id]) {
                    const stored = this.catalog[id].current_model || '';
                    if (stored && !this.models[id].some((m) => m.id === stored)) {
                        this.models[id].unshift({ id: stored, name: stored, experimental: false });
                    }
                    if (!this.forms[id].model) {
                        this.forms[id].model = this.models[id][0]?.id ?? '__custom__';
                    }
                }
            });

            // Warm the remote catalog for providers that already hold credentials.
            Object.keys(this.catalog).forEach((id) => {
                if (this.catalog[id].has_key) {
                    this.loadModels(id, false);
                }
            });
        },

        async loadModels(provider, force = false) {
            this.loadingModels[provider] = true;

            try {
                const url = new URL('{{ route('admin.ai_assistant.providers.models') }}', window.location.origin);
                url.searchParams.set('provider', provider);
                if (force) {
                    url.searchParams.set('refresh', '1');
                }

                const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (data.connection) {
                    this.catalog[provider].connection_state = data.connection.state;
                    this.catalog[provider].connection_verified = data.connection.verified;
                }

                if (data.success && Array.isArray(data.models) && data.models.length > 0) {
                    this.models[provider] = data.models.map((m) => ({ id: m.id, name: m.name, experimental: !!m.experimental }));

                    if (!this.models[provider].some((m) => m.id === this.forms[provider].model)) {
                        this.forms[provider].model = this.models[provider][0].id;
                    }

                    this.modelsMeta[provider] = `${data.models.length} modelos · ${this.catalog[provider].catalog_source === 'documented' ? 'catálogo documentado' : 'catálogo del proveedor'}`;

                    if (force) {
                        this.showToast(`Catálogo de ${provider.toUpperCase()} actualizado (${data.models.length} modelos).`, 'success');
                    }
                } else if (data.success) {
                    this.modelsMeta[provider] = 'Sin modelos disponibles — revisa el endpoint y las credenciales.';
                }
            } catch (err) {
                this.showToast('No se pudo actualizar la lista de modelos.', 'error');
            } finally {
                this.loadingModels[provider] = false;
            }
        },

        showToast(msg, type = 'success') {
            this.toastMessage = msg;
            this.toastType = type;
            setTimeout(() => { this.toastMessage = null; }, 5000);
        },

        async saveProvider(provider) {
            this.savingProvider = provider;
            const form = this.forms[provider];
            const chosenModel = (form.model === '__custom__' && form.custom_model_text) ? form.custom_model_text : form.model;

            try {
                const res = await fetch('{{ route('admin.ai_assistant.providers.save') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        provider: provider,
                        api_key: form.api_key,
                        api_key_id: provider === 'higgsfield' ? form.api_key_id : null,
                        api_key_secret: provider === 'higgsfield' ? form.api_key_secret : null,
                        model: chosenModel,
                        image_model: form.image_model || null,
                        video_model: form.video_model || null,
                        base_url: form.base_url || null
                    })
                });

                const data = await res.json();
                if (data.success) {
                    this.connectionEpochs[provider] = (this.connectionEpochs[provider] || 0) + 1;
                    for (const modality of ['text', 'image', 'video']) delete this.testResults[provider + ':' + modality];
                    this.forms[provider].api_key = '';
                    this.forms[provider].api_key_id = '';
                    this.forms[provider].api_key_secret = '';
                    this.showToast(data.message || 'Configuración guardada correctamente.', 'success');
                    if (this.catalog[provider]) {
                        this.catalog[provider].current_model = data.model;
                        this.catalog[provider].has_key = data.has_key;
                    }
                    this.forms[provider].model = data.model || this.forms[provider].model;
                    this.loadModels(provider, true);
                } else {
                    this.showToast(data.error || 'Error al guardar.', 'error');
                }
            } catch (err) {
                this.showToast('Error de red al contactar con el servidor.', 'error');
            } finally {
                this.savingProvider = null;
            }
        },

        async testProvider(provider, modality = 'text') {
            const resultKey = provider + ':' + modality;
            const epoch = this.connectionEpochs[provider] || 0;
            this.testingProvider = resultKey;
            try {
                const res = await fetch('{{ route('admin.ai_assistant.test_connection') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ provider: provider, modality: modality })
                });

                const data = await res.json();
                if (epoch !== (this.connectionEpochs[provider] || 0)) return;
                this.testResults[resultKey] = data;
                if (data.status === 'connected') {
                    this.showToast(data.message, 'success');
                } else {
                    this.showToast(data.error || data.message, 'error');
                }
            } catch (err) {
                this.showToast('Error de red al ejecutar test.', 'error');
            } finally {
                this.testingProvider = null;
            }
        },

        async setDefault(provider, modality = null) {
            this.settingDefault = provider;
            try {
                const res = await fetch('{{ route('admin.ai_assistant.providers.set_default') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ provider: provider, modality: modality })
                });

                const data = await res.json();
                if (data.success) {
                    if (modality) {
                        if (this.matrix && this.matrix[modality]) {
                            this.matrix[modality].active = data.default_provider;
                        }
                        if (modality === 'text') {
                            this.activeProvider = data.default_provider;
                        }
                    } else {
                        this.activeProvider = data.default_provider;
                        if (this.matrix && this.matrix.text) {
                            this.matrix.text.active = data.default_provider;
                        }
                    }
                    this.showToast(data.message || 'Proveedor predeterminado actualizado.', 'success');
                } else {
                    this.showToast(data.error || 'Error al cambiar proveedor.', 'error');
                }
            } catch (err) {
                this.showToast('Error de red al establecer proveedor.', 'error');
            } finally {
                this.settingDefault = null;
            }
        }
    };
}
</script>
@endsection

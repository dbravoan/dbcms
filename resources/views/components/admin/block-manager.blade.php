@props([
    'sections' => [],
    'name' => 'sections_json',
    'languages' => [],
    'target' => null,
])

@php
    // Expresiones Alpine para el estado activo de los botones de layout: se
    // construyen como texto en PHP porque los atributos de un componente <x-…>
    // se copian literalmente (no compilan directivas).
    $layoutActiveExpr = static fn (string $layout): string => "activeLayout(block) === '".$layout."'";

    $blockRegistry = app(\App\Core\Blocks\Contracts\BlockRegistryInterface::class);
    $activeBlockTypes = $blockRegistry->getActiveBlocks();
    $dynamicPayloads = $blockRegistry->getDynamicDataPayloads();
    $availableTypesJs = collect($activeBlockTypes)->map(fn ($b) => [
        'type' => $b->getType(),
        'name' => $b->getName(),
        'icon' => $b->getIcon(),
        'category' => in_array($b->getCategory(), ['dynamic', 'widget', 'interactive']) ? 'widget' : 'content',
        'raw_category' => $b->getCategory(),
        'desc' => $b->getDescription(),
        'fullWidth' => $b->isFullWidth(),
        'defaultPayload' => $b->getDefaultPayload(),
    ])->values()->all();

    $rawSections = !empty($sections) ? (is_string($sections) ? json_decode($sections, true) : $sections) : [];
    if (!is_array($rawSections)) {
        $rawSections = [];
    }

    $allActiveLanguages = !empty($languages) && count($languages) > 0
        ? $languages
        : ($activeLanguages ?? collect());

    if ($allActiveLanguages->isEmpty()) {
        // Sin idiomas en la BD se usa el idioma por defecto real (reactivo),
        // nunca una lista fija que no coincide con el backoffice.
        $allActiveLanguages = collect([$defaultLanguage ?? (object) ['code' => 'es', 'name' => 'Español', 'flag' => '🇪🇸']]);
    }

    $languagesJs = collect($allActiveLanguages)->map(function ($lang) {
        $code = is_object($lang) ? $lang->code : ($lang['code'] ?? 'es');
        $name = is_object($lang) ? $lang->name : ($lang['name'] ?? strtoupper((string) $code));
        // Prefer the flag assigned in the backoffice (Language::flag accessor).
        $assigned = is_object($lang) ? ($lang->flag ?? null) : ($lang['flag'] ?? null);
        $flag = ! empty($assigned) ? (string) $assigned : match($code) {
            'es' => '🇪🇸',
            'en' => '🇺🇸',
            'ca' => '🏴󠁥󠁳󠁣󠁴󠁿',
            'fr' => '🇫🇷',
            'de' => '🇩🇪',
            'it' => '🇮🇹',
            'pt' => '🇵🇹',
            default => '🌐'
        };
        return [
            'code' => $code,
            'name' => $name,
            'flag' => $flag,
        ];
    })->values()->all();

    // Determine if $rawSections is keyed by locale
    $isLocaleKeyed = false;
    foreach (array_keys($rawSections) as $k) {
        if (is_string($k) && !is_numeric($k)) {
            $isLocaleKeyed = true;
            break;
        }
    }

    $sectionsByLocale = [];
    foreach ($allActiveLanguages as $lang) {
        $code = is_object($lang) ? $lang->code : ($lang['code'] ?? 'es');
        $sectionsByLocale[$code] = [];
    }

    if ($isLocaleKeyed) {
        foreach ($sectionsByLocale as $code => $val) {
            $sectionsByLocale[$code] = isset($rawSections[$code]) && is_array($rawSections[$code])
                ? $rawSections[$code]
                : [];
        }
    } else {
        // Flat array: assign to default locale 'es'
        $sectionsByLocale['es'] = $rawSections;
    }

    // Normalize each block in each locale
    foreach ($sectionsByLocale as $code => &$locBlocks) {
        foreach ($locBlocks as &$b) {
            $b['title'] = $b['title'] ?? ($b['headline'] ?? '');
            $b['content'] = $b['content'] ?? ($b['body'] ?? ($b['subtitle'] ?? ($b['subheadline'] ?? '')));
            $b['cta_text'] = $b['cta_text'] ?? ($b['cta_label'] ?? '');
            $b['cta_url'] = $b['cta_url'] ?? ($b['cta_link'] ?? '');
            $b['image_url'] = $b['image_url'] ?? '';
            $b['after_image_url'] = $b['after_image_url'] ?? '';
            $b['before_label'] = $b['before_label'] ?? 'Antes';
            $b['after_label'] = $b['after_label'] ?? 'Después';
            $b['items'] = $b['items'] ?? [];
            $b['form_slug'] = $b['form_slug'] ?? 'contacto';
            $b['category'] = $b['category'] ?? '';
            $b['limit'] = $b['limit'] ?? 3;
            $b['columns'] = $b['columns'] ?? '3';
            $b['animation'] = $b['animation'] ?? 'fadeInUp';
            $b['animation_delay'] = $b['animation_delay'] ?? '0.2s';
            $b['animation_duration'] = $b['animation_duration'] ?? '1s';
            $b['container_style'] = $b['container_style'] ?? 'cyber-card';
            $b['content_width'] = $b['content_width'] ?? 'centered';
            $b['bg_type'] = $b['bg_type'] ?? 'none';
            $b['bg_color'] = $b['bg_color'] ?? '';
            $b['bg_image_url'] = $b['bg_image_url'] ?? '';
            $b['bg_image_overlay'] = $b['bg_image_overlay'] ?? 'rgba(0,0,0,0.5)';
            $b['bg_video_url'] = $b['bg_video_url'] ?? '';
            $b['bg_video_poster'] = $b['bg_video_poster'] ?? '';
            $b['bg_video_overlay'] = $b['bg_video_overlay'] ?? 'rgba(0,0,0,0.5)';
            $b['border_top'] = $b['border_top'] ?? ['enabled' => false, 'color' => '#00FF99', 'width' => '2px', 'style' => 'solid'];
            $b['border_bottom'] = $b['border_bottom'] ?? ['enabled' => false, 'color' => '#00FF99', 'width' => '2px', 'style' => 'solid'];
            $b['divider_top'] = $b['divider_top'] ?? ['type' => 'none', 'color' => '#0E0E0E', 'height' => 60, 'flip' => false];
            $b['divider_bottom'] = $b['divider_bottom'] ?? ['type' => 'none', 'color' => '#0E0E0E', 'height' => 60, 'flip' => false];
            $b['columns_layout'] = $b['columns_layout'] ?? '1';
            // Contrato canónico: una sola clave para las columnas.
            $b['columns'] = (isset($b['columns']) && is_array($b['columns'])) ? $b['columns'] : [];
            $b['scroll_magic'] = $b['scroll_magic'] ?? ['behavior' => $b['animation'], 'delay' => $b['animation_delay'], 'duration' => $b['animation_duration'], 'scrub' => 'none'];
            $b['is_collapsed'] = false;
        }
    }
    unset($locBlocks, $b);
@endphp

<script nonce="{{ $cspNonce ?? '' }}">
(function() {
    function initCmsBlockManager() {
        if (!window.Alpine || window.Alpine._cmsBlockManagerRegistered) return;
        window.Alpine._cmsBlockManagerRegistered = true;
        window.Alpine.data('blockManager', (initialLocaleMap = {}, defaultLocale = 'es', initialAvailableTypes = [], availableLanguages = [], focusTarget = null) => ({
            activeLocale: defaultLocale,
            sectionsByLocale: initialLocaleMap,
            languages: availableLanguages,
            focusTargetId: focusTarget,
            blocks: [],
            isFullscreen: false,
            canvasWidth: 'container',
            activeCategory: 'all',
            searchQuery: '',
            showAddModal: false,
            insertIndex: null,
            columnTarget: null,
            insertionMode: 'demo',
            copySourceLocale: 'es',
            showAppearanceModal: false,
            isAppearanceMaximized: false,
            editingAppearanceIndex: null,
            appearanceTarget: null,
            appearanceScope: 'section',
            appearanceTab: 'container',

            // Un único modal para añadir bloques en cualquier contexto: una
            // sección nueva (index null), arriba/abajo de una sección (index) o
            // dentro de una columna (columnTarget).
            openAddModalAt(index = null, columnTarget = null) {
                this.insertIndex = index;
                this.columnTarget = columnTarget;
                this.showAddModal = true;
                this.searchQuery = '';
                this.activeCategory = 'all';
            },

            // Cierra el modal y limpia el contexto de inserción.
            closeAddModal() {
                this.showAddModal = false;
                this.columnTarget = null;
            },

            // El bloque elegido se inserta donde toca según el contexto. Un
            // widget de ancho completo (rejilla propia) siempre va a nivel de
            // sección: anidarlo en una columna crearía rejilla dentro de rejilla.
            insertType(type, mode) {
                const def = this.availableTypes.find(t => t.type === type);

                if (this.columnTarget && !(def && def.fullWidth)) {
                    this.addColumnBlock(this.columnTarget.blockIndex, this.columnTarget.colIndex, type, mode);
                    return;
                }

                this.addBlock(type, mode);
            },

            toggleFullscreen() {
                this.isFullscreen = !this.isFullscreen;
                if (this.isFullscreen) {
                    document.body.classList.add('overflow-hidden');
                } else {
                    document.body.classList.remove('overflow-hidden');
                }
            },

            saveForm() {
                this.syncBlocksToLocale();
                const form = this.$el.closest('form');
                if (form) {
                    if (form.requestSubmit) {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                }
            },

            availableTypes: initialAvailableTypes,

            // Resolución de iconos de widget: nunca vacío.
            // 1) clase FontAwesome (fa-*), 2) emoji/símbolo, 3) inicial del nombre.
            typeDef(type) {
                return this.availableTypes.find(t => t.type === type) || {};
            },

            // Robust FontAwesome icon class resolution: normalizes legacy
            // prefixes (fas/far/fab), bare `fa-*` names and FA6 style prefixes.
            // Returns '' for emoji/text so callers can fall back to it.
            blockIconClass(icon) {
                const value = String(icon || '').trim();
                if (value === '') {
                    return '';
                }
                if (value.startsWith('fas ')) {
                    return 'fa-solid ' + value.slice(4).trim();
                }
                if (value.startsWith('far ')) {
                    return 'fa-regular ' + value.slice(4).trim();
                }
                if (value.startsWith('fab ')) {
                    return 'fa-brands ' + value.slice(4).trim();
                }
                if (/^fa-(solid|regular|brands|light|thin|duotone)\s+fa-/.test(value)) {
                    return value;
                }
                if (/^fa-[a-z0-9-]+$/.test(value)) {
                    return 'fa-solid ' + value;
                }

                return '';
            },

            isBlockIcon(icon) {
                return this.blockIconClass(icon) !== '';
            },

            isFontAwesomeIcon(icon) {
                return typeof icon === 'string' && icon.includes('fa-');
            },

            typeGlyph(type) {
                const def = this.typeDef(type);
                if (def.icon && !this.isFontAwesomeIcon(def.icon)) {
                    return def.icon;
                }
                const name = (def.name || type || '?').toString().trim();
                return name.charAt(0).toUpperCase();
            },

            animationOptions: [
                { value: 'none', label: 'Sin animación' },
                { value: 'fadeIn', label: 'Aparición suave (fade in)' },
                { value: 'fadeInUp', label: 'Desplazamiento ascendente' },
                { value: 'fadeInDown', label: 'Desplazamiento descendente' },
                { value: 'fadeInLeft', label: 'Entrada desde la izquierda' },
                { value: 'fadeInRight', label: 'Entrada desde la derecha' },
                { value: 'zoomIn', label: 'Aumento de escala (zoom in)' },
                { value: 'bounceIn', label: 'Entrada con rebote' },
                { value: 'flipInX', label: 'Giro horizontal' }
            ],

            containerStyles: [
                { value: 'cyber-card', label: 'Tarjeta translúcida oscura' },
                { value: 'cyber-glass', label: 'Efecto cristal (glassmorphism)' },
                { value: 'neon-glow', label: 'Borde iluminado con resplandor' },
                { value: 'transparent', label: 'Transparente sin borde' },
                { value: 'full-width-dark', label: 'Ancho completo con fondo oscuro' }
            ],

            dividerTypes: [
                { value: 'none', label: 'Ninguno' },
                { value: 'waves', label: 'Olas suaves' },
                { value: 'tilt', label: 'Corte diagonal' },
                { value: 'curve', label: 'Curva suave' },
                { value: 'triangle', label: 'Triángulo central' },
                { value: 'spikes', label: 'Picos geométricos' }
            ],

            init() {
                if (!this.sectionsByLocale[this.activeLocale]) {
                    this.sectionsByLocale[this.activeLocale] = [];
                }
                this.blocks = this.sectionsByLocale[this.activeLocale];

                // Deep-link from the front office: open and reveal the target block.
                this.focusTargetBlock();

                // Listen to external language switches from parent form tabs
                window.addEventListener('cms-locale-switched', (e) => {
                    const newLocale = e.detail?.locale;
                    if (newLocale && newLocale !== this.activeLocale) {
                        this.switchLocale(newLocale);
                    }
                });

                // Listen to external copy command from parent form
                window.addEventListener('cms-copy-locale', (e) => {
                    const fromLocale = e.detail?.from;
                    const toLocale = e.detail?.to || this.activeLocale;
                    if (fromLocale && toLocale) {
                        this.copyBlocksFrom(fromLocale, toLocale);
                    }
                });

                // Generic listener for external block insertion (decoupled from any specific module)
                window.addEventListener('cms-insert-block', (e) => {
                    const block = e.detail?.block;
                    const index = e.detail?.index ?? this.insertIndex ?? null;
                    if (block) {
                        this.insertExternalBlock(block, index);
                    }
                });

                // Generic listener for external block replacement (AI form filling, imports...)
                window.addEventListener('cms-replace-blocks', (e) => {
                    const blocks = e.detail?.blocks;
                    if (Array.isArray(blocks)) {
                        this.setBlocks(blocks);
                    }
                });

                // Translation targets its own locale, preserving section and column identities.
                window.addEventListener('cms-translation-blocks', (event) => {
                    const { form, targetLocale, blocks } = event.detail || {};
                    if (form !== this.$el.closest('form') || !this.languages.some(language => language.code === targetLocale) || !Array.isArray(blocks)) return;
                    this.syncBlocksToLocale();
                    this.sectionsByLocale[targetLocale] = JSON.parse(JSON.stringify(blocks));
                    if (this.activeLocale === targetLocale) this.blocks = this.sectionsByLocale[targetLocale];
                });

                // Listen to market changes from parent entity form to filter fullscreen languages
                window.addEventListener('cms-markets-changed', (e) => {
                    const activeLocales = e.detail?.activeLocales || [];
                    if (activeLocales.length > 0) {
                        this.activeLocales = activeLocales;
                        if (!this.activeLocales.includes(this.activeLocale)) {
                            this.switchLocale(this.activeLocales[0]);
                        }
                    }
                });
            },

            activeLocales: [],

            isLanguageActive(code) {
                if (!this.activeLocales || this.activeLocales.length === 0) return true;
                return this.activeLocales.includes(code);
            },

            getCleanSerializedSections() {
                this.syncBlocksToLocale();
                const clean = JSON.parse(JSON.stringify(this.sectionsByLocale));
                for (const locale in clean) {
                    if (Array.isArray(clean[locale])) {
                        clean[locale].forEach(b => {
                            if (b && typeof b === 'object') {
                                delete b._col_buffer;
                                delete b._columns;
                            }
                        });
                    }
                }
                return JSON.stringify(clean);
            },

            insertExternalBlock(block, targetIndex = null) {
                if (!block.id) {
                    block.id = 'sec_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6);
                }
                block.is_collapsed = false;
                if (targetIndex !== null && targetIndex >= 0 && targetIndex <= this.blocks.length) {
                    this.blocks.splice(targetIndex, 0, block);
                } else {
                    this.blocks.push(block);
                }
                this.insertIndex = null;
                this.syncBlocksToLocale();
            },

            // Replace the whole block sequence (used by generic external producers).
            setBlocks(list) {
                this.blocks = [];
                list.forEach((block) => {
                    if (!block || typeof block !== 'object') {
                        return;
                    }
                    if (!block.id) {
                        block.id = 'sec_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6);
                    }
                    block.is_collapsed = false;
                    this.blocks.push(block);
                });
                this.syncBlocksToLocale();
            },

            syncBlocksToLocale() {
                this.sectionsByLocale[this.activeLocale] = this.blocks;
            },

            switchLocale(code) {
                this.syncBlocksToLocale();
                this.activeLocale = code;
                if (!this.sectionsByLocale[code]) {
                    this.sectionsByLocale[code] = [];
                }
                this.blocks = this.sectionsByLocale[code];

                const globalContext = window.cmsContext = window.cmsContext || { locale: null, marketIds: [] };
                globalContext.locale = code;
                window.dispatchEvent(new CustomEvent('cms-locale-switched', { detail: { locale: code } }));
            },

            /**
             * Focus the block requested via `?section=<id>` (front-office
             * deep-link). Expands it, scrolls it into view and flashes a ring.
             */
            focusTargetBlock() {
                if (! this.focusTargetId) {
                    return;
                }

                // The front deep-link carries the numeric section id while the
                // block manager may store ids as `sec_<id>`; compare digits only.
                const normalize = (value) => String(value ?? '').replace(/[^0-9]/g, '');
                const id = normalize(this.focusTargetId);

                if (id === '') {
                    return;
                }

                let locale = this.activeLocale;
                let index = (this.sectionsByLocale[locale] || []).findIndex((b) => normalize(b.id) === id);

                if (index === -1) {
                    for (const code of Object.keys(this.sectionsByLocale)) {
                        const found = (this.sectionsByLocale[code] || []).findIndex((b) => normalize(b.id) === id);
                        if (found !== -1) {
                            locale = code;
                            index = found;
                            break;
                        }
                    }
                }

                if (index === -1) {
                    return;
                }

                if (locale !== this.activeLocale) {
                    this.switchLocale(locale);
                }

                if (this.blocks[index]) {
                    this.blocks[index].is_collapsed = false;
                }

                this.$nextTick(() => {
                    const el = this.$el.querySelector('[data-block-index="' + index + '"]');
                    if (! el) {
                        return;
                    }

                    el.classList.add('ring-2', 'ring-[#00FF99]/60', 'rounded-2xl');
                    window.setTimeout(() => el.classList.remove('ring-2', 'ring-[#00FF99]/60'), 2400);

                    // The admin content lives in an `overflow-y-auto` <main>, so
                    // window-level scrollIntoView is a no-op: scroll the nearest
                    // scrollable ancestor explicitly. Runs twice because images and
                    // fonts keep growing the layout right after init.
                    const doScroll = () => {
                        let scroller = el.parentElement;
                        while (scroller && scroller !== document.body) {
                            const style = window.getComputedStyle(scroller);
                            if (/(auto|scroll)/.test(style.overflowY) && scroller.scrollHeight > scroller.clientHeight) {
                                break;
                            }
                            scroller = scroller.parentElement;
                        }

                        if (scroller && scroller !== document.body) {
                            const offset = el.getBoundingClientRect().top - scroller.getBoundingClientRect().top;
                            // Instant (not `behavior: smooth`): deterministic across
                            // engines and headless renderers.
                            scroller.scrollTop = Math.max(0, scroller.scrollTop + offset - Math.round(scroller.clientHeight / 3));
                        } else {
                            el.scrollIntoView({ block: 'center' });
                        }
                    };

                    doScroll();
                    window.setTimeout(doScroll, 400);
                });
            },

            copyBlocksFrom(sourceLocale, targetLocale = null) {
                const target = targetLocale || this.activeLocale;
                if (!this.sectionsByLocale[sourceLocale]) return;

                const clonedBlocks = JSON.parse(JSON.stringify(this.sectionsByLocale[sourceLocale]));
                clonedBlocks.forEach(b => {
                    b.id = 'sec_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6);
                });

                this.sectionsByLocale[target] = clonedBlocks;
                if (target === this.activeLocale) {
                    this.blocks = this.sectionsByLocale[target];
                }
            },

            addBlock(type, forceMode = null) {
                const mode = forceMode || this.insertionMode;
                const isDemo = mode === 'demo';
                const typeDef = this.availableTypes.find(t => t.type === type);
                const defaultPayload = typeDef?.defaultPayload || {};

                const newBlock = Object.assign({
                    id: 'sec_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6),
                    type: type,
                    title: '',
                    content: '',
                    animation: 'fadeInUp',
                    animation_delay: '0.2s',
                    animation_duration: '1s',
                    container_style: 'cyber-card',
                    content_width: 'centered',
                    bg_type: 'none',
                    bg_color: '',
                    bg_image_url: '',
                    bg_image_overlay: 'rgba(0,0,0,0.5)',
                    bg_video_url: '',
                    bg_video_poster: '',
                    bg_video_overlay: 'rgba(0,0,0,0.5)',
                    border_top: { enabled: false, color: '#00FF99', width: '2px', style: 'solid' },
                    border_bottom: { enabled: false, color: '#00FF99', width: '2px', style: 'solid' },
                    divider_top: { type: 'none', color: '#0E0E0E', height: 60, flip: false },
                    divider_bottom: { type: 'none', color: '#0E0E0E', height: 60, flip: false },
                    scroll_magic: { behavior: 'fadeInUp', delay: '0.2s', duration: '1s', scrub: 'none' },
                    is_collapsed: false,
                    columns_layout: '1',
                    columns: [],
                    items: [],
                    cta_text: '',
                    cta_url: '',
                    image_url: '',
                    after_image_url: '',
                    before_label: 'Antes',
                    after_label: 'Después',
                    form_slug: 'contacto',
                    category: '',
                    limit: 3
                }, JSON.parse(JSON.stringify(defaultPayload)));

                if (isDemo) {
                    this.populateDemoData(newBlock, type);
                }

                if (this.insertIndex !== null && this.insertIndex >= 0 && this.insertIndex <= this.blocks.length) {
                    this.blocks.splice(this.insertIndex, 0, newBlock);
                } else {
                    this.blocks.push(newBlock);
                }
                this.insertIndex = null;
                this.syncBlocksToLocale();
                this.showAddModal = false;
            },

            populateDemoData(block, type) {
                switch(type) {
                    case 'hero':
                        block.title = 'Infraestructura web de alto rendimiento';
                        block.content = 'Plataforma empresarial con distribución global, baja latencia y arquitectura modular.';
                        block.cta_text = 'Ver catálogo';
                        block.cta_url = '/shop';
                        block.image_url = 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1600&q=80';
                        block.bg_type = 'image';
                        block.bg_image_url = 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1600&q=80';
                        block.bg_image_overlay = 'rgba(0,0,0,0.6)';
                        break;
                    case 'text':
                        block.title = 'Arquitectura modular y escalabilidad';
                        block.format = 'markdown';
                        block.content = "### Diseñado para entornos profesionales\n\nNuestra plataforma separa la presentación de los servicios de negocio:\n\n<div class=\"p-3 bg-black/40 border border-[#00FF99]/30 rounded-lg my-3 text-xs text-[#00FF99] font-mono\">\n  <strong>{{ __('admin.block_manager.tech_note') }}</strong> {{ __('admin.block_manager.html_markdown_support') }}\n</div>\n\n- **Respuesta rápida**: Caché local en memoria y optimización de recursos.\n- **Soporte multi-mercado**: Configuración adaptada a cada país e idioma.\n- **Privacidad y RGPD**: Gestión dinámica de cookies y consentimientos auditados.";
                        break;
                    case 'features':
                        block.title = 'Características principales';
                        block.columns = '3';
                        block.items = [
                            { icon: 'fa-solid fa-bolt', title: 'Rendimiento óptimo', desc: 'Tiempos de carga reducidos y optimización para buscadores.' },
                            { icon: 'fa-solid fa-shield-halved', title: 'Seguridad y control', desc: 'Autenticación robusta y control de accesos por roles.' },
                            { icon: 'fa-solid fa-earth-americas', title: 'Gestión multi-idioma', desc: 'Contenidos, monedas y catálogos adaptados a cada mercado.' }
                        ];
                        break;
                    case 'kpi':
                    case 'widget_stats':
                        block.title = 'Métricas y disponibilidad';
                        block.columns = '4';
                        block.items = [
                            { value: '99.9%', label: 'Disponibilidad del servicio', icon: 'fa-solid fa-chart-line' },
                            { value: '< 20ms', label: 'Tiempo de respuesta medio', icon: 'fa-solid fa-bolt' },
                            { value: '12', label: 'Centros de procesamiento', icon: 'fa-solid fa-earth-americas' },
                            { value: '24/7', label: 'Supervisión técnica', icon: 'fa-solid fa-shield-halved' }
                        ];
                        break;
                    case 'pricing':
                        block.title = 'Planes y Tarifas Transparentes';
                        block.content = 'Elige el plan adaptado al crecimiento y demanda de tu infraestructura.';
                        block.items = [
                            { name: 'Starter', price: '19€', period: '/mes', badge: '', features: "Hasta 3 proyectos\n10GB Almacenamiento NVMe\nSoporte comunidad", cta_label: 'Comenzar gratis', cta_url: '/registro', is_featured: false },
                            { name: 'Professional', price: '49€', period: '/mes', badge: 'Recomendado', features: "Proyectos ilimitados\n100GB Almacenamiento NVMe\nSoporte 24/7 con SLA\nDominio personalizado", cta_label: 'Probar 14 días', cta_url: '/registro?plan=pro', is_featured: true },
                            { name: 'Enterprise', price: '199€', period: '/mes', badge: 'Máxima Potencia', features: "Infraestructura dedicada\nSLA 99.99% garantizado\nGestor de cuenta dedicado\nAuditorías de seguridad", cta_label: 'Contactar ventas', cta_url: '/contacto', is_featured: false }
                        ];
                        break;
                    case 'faq':
                        block.title = 'Preguntas frecuentes';
                        block.items = [
                            { question: '¿Cómo se gestiona el contenido en varios idiomas?', answer: 'Cada página y sección permite traducir individualmente o copiar contenidos entre idiomas con un solo clic.' },
                            { question: '¿Cómo se configuran los vídeos y animaciones?', answer: 'Puedes asignar fondos de vídeo y animaciones de scroll desde el panel de personalización de cada sección.' },
                            { question: '¿Es compatible con los temas disponibles?', answer: 'Sí, todos los componentes se adaptan automáticamente al diseño del tema activo.' }
                        ];
                        break;
                    case 'testimonials':
                        block.title = 'Lo que dicen nuestros clientes';
                        block.items = [
                            { name: 'Elena Soler', role: 'Directora de Operaciones en CloudNine', quote: 'La migración fue instantánea y la optimización en los tiempos de respuesta nos redujo el coste en servidores un 40%.', rating: 5, avatar: '' },
                            { name: 'Marc Vives', role: 'Lead Architect en FinTech Solutions', quote: 'La arquitectura multi-mercado sin duplicar base de datos es la más limpia y profesional que hemos utilizado.', rating: 5, avatar: '' }
                        ];
                        break;
                    case 'cta':
                        block.title = '¿Necesitas más información?';
                        block.content = 'Escríbenos y te asesoraremos sobre la mejor solución para tu proyecto.';
                        block.cta_text = 'Contactar';
                        block.cta_url = '/contacto';
                        break;
                    case 'before_after':
                        block.title = 'Comparativa de rendimiento antes y después';
                        block.image_url = 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?auto=format&fit=crop&w=1000&q=80';
                        block.after_image_url = 'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?auto=format&fit=crop&w=1000&q=80';
                        block.before_label = 'Antes (sistema tradicional)';
                        block.after_label = @js(__('admin.block_manager.ph_after'));
                        break;
                    case 'code_embed':
                        block.title = 'Ejemplo de integración';
                        block.content = "```php\nuse App\\Services\\Cms\\ThemeManagerService;\n\n$theme = app(ThemeManagerService::class)->getActiveTheme();\necho 'Tema actual: ' . $theme;\n```";
                        break;
                    case 'roi':
                        block.title = 'Calculadora de Rentabilidad & Retorno (ROI)';
                        block.initial_investment = 12000;
                        block.return_percentage = 35;
                        block.months = 12;
                        block.chart_mode = 'bar';
                        block.content = 'Proyección calculada con un ahorro del 35% en costes de cómputo perimetral.';
                        break;
                    case 'widget_posts':
                        block.title = 'Últimas publicaciones del blog';
                        block.category = '';
                        block.limit = 3;
                        block.columns = '3';
                        break;
                    case 'widget_products':
                        block.title = 'Productos destacados';
                        block.category = '';
                        block.limit = 3;
                        block.columns = '3';
                        break;
                    case 'widget_form':
                        block.title = 'Solicitud de información';
                        block.content = 'Envíanos tu consulta y te responderemos en el menor tiempo posible.';
                        block.form_slug = 'contacto';
                        break;
                    case 'gallery':
                        block.title = 'Álbumes y Galerías de Imágenes';
                        block.album_slug = '';
                        block.columns = '3';
                        block.limit = 6;
                        break;
                    case 'charts':
                        block.title = 'Rendimiento y Tráfico Transaccional';
                        block.chart_type = 'bar';
                        block.items = [
                            { label: 'Q1', value: 45, color: '#00FF99' },
                            { label: 'Q2', value: 68, color: '#38bdf8' },
                            { label: 'Q3', value: 85, color: '#a855f7' },
                            { label: 'Q4', value: 110, color: '#f59e0b' }
                        ];
                        break;
                }
            },

            removeBlock(index) {
                if (confirm('¿Estás seguro de que deseas eliminar este bloque de la página?')) {
                    this.blocks.splice(index, 1);
                    this.syncBlocksToLocale();
                }
            },

            cloneBlock(index) {
                const cloned = JSON.parse(JSON.stringify(this.blocks[index]));
                cloned.id = 'sec_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6);
                cloned.title = (cloned.title || '') + ' (Copia)';
                this.blocks.splice(index + 1, 0, cloned);
                this.syncBlocksToLocale();
            },

            moveUp(index) {
                if (index > 0) {
                    const temp = this.blocks[index];
                    this.blocks[index] = this.blocks[index - 1];
                    this.blocks[index - 1] = temp;
                    this.syncBlocksToLocale();
                }
            },

            moveDown(index) {
                if (index < this.blocks.length - 1) {
                    const temp = this.blocks[index];
                    this.blocks[index] = this.blocks[index + 1];
                    this.blocks[index + 1] = temp;
                    this.syncBlocksToLocale();
                }
            },

            toggleCollapseAll(collapse) {
                this.blocks.forEach(b => b.is_collapsed = collapse);
            },

            // La apariencia tiene tres ámbitos sobre el MISMO modal:
            //  - `section`: fondo/parallax, bordes, divisores, animación y layout de la fila.
            //  - `column`:  fondo/parallax y bordes de una columna concreta.
            //  - `block`:   los campos del bloque (nunca fondo, que es de la fila).
            openAppearanceModal(index, target = null) {
                this.editingAppearanceIndex = index;
                this.appearanceTarget = target; // null | { colIndex } | { colIndex, subIndex }
                this.appearanceScope = !target ? 'section' : (this.appearanceTarget.subIndex === undefined ? 'column' : 'block');
                this.appearanceTab = 'container';
                this.showAppearanceModal = true;
            },

            // Abre la apariencia de UNA columna (ámbito `column`).
            openColumnAppearanceModal(index, colIndex) {
                this.openAppearanceModal(index, { colIndex });
            },

            closeAppearanceModal() {
                this.showAppearanceModal = false;
                this.editingAppearanceIndex = null;
                this.appearanceTarget = null;
                this.syncBlocksToLocale();
            },

            // El elemento que se está editando según el ámbito: la sección, la
            // columna o el bloque (de nivel raíz o dentro de una columna).
            appearanceModel() {
                const block = this.blocks[this.editingAppearanceIndex];
                if (!block || !this.appearanceTarget) return block;

                const cols = this.blockColumns(block);
                const col = cols[this.appearanceTarget.colIndex];
                if (!col) return block;

                // Ámbito columna: se edita el estilo propio de la columna.
                if (this.appearanceTarget.subIndex === undefined) {
                    if (!col.style || typeof col.style !== 'object') {
                        col.style = { bg_type: 'none', bg_color: '', bg_behavior: 'normal', bg_image_url: '', bg_image_overlay: 'rgba(0,0,0,0.5)' };
                    }
                    if (col.style.bg_type === undefined) col.style.bg_type = 'none';
                    if (col.style.bg_behavior === undefined) col.style.bg_behavior = 'normal';

                    return col.style;
                }

                return (col.blocks || [])[this.appearanceTarget.subIndex] || block;
            },

            // Cambia el número de columnas sin perder nada: al volver a una
            // columna los bloques se apilan en orden, y al expandir se crean (o
            // recuperan) las columnas necesarias. El contenido propio de la
            // sección pasa a ser el primer bloque de la primera columna.
            setColumnsLayout(blockIndex, layout) {
                const block = this.blocks[blockIndex];
                if (!block) return;

                const count = layout === '4' ? 4 : (layout === '3' ? 3 : (layout === '1' ? 1 : 2));
                block.columns_layout = layout;

                const hasColumns = Array.isArray(block.columns);
                const sectionPayload = this.sectionAsBlock(block);
                const sectionHasContent = this.blockHasContent(sectionPayload);

                // Primera vez que se abre a columnas: el contenido de la sección
                // se convierte en el primer bloque de la primera columna.
                if (!hasColumns && count > 1 && sectionHasContent) {
                    block._columns = [{ id: this.newColumnId(0), blocks: [sectionPayload] }];
                    this.clearSectionContent(block);
                }

                let columns = Array.isArray(block._columns) ? block._columns : (hasColumns ? block.columns : []);
                columns = columns.filter((c) => c && typeof c === 'object');
                columns.forEach((c, i) => {
                    c.id = c.id || this.newColumnId(i);
                    c.blocks = Array.isArray(c.blocks) ? c.blocks : [];
                });

                if (count === 1) {
                    // Apilar: todos los bloques en una columna, en orden visual.
                    const stacked = [];
                    columns.forEach((c) => stacked.push(...c.blocks));
                    block._columns = stacked.length ? [{ id: this.newColumnId(0), blocks: stacked }] : [];
                    block.columns = block._columns;
                    this.syncBlocksToLocale();
                    return;
                }

                // Reducir: los bloques de las columnas sobrantes se conservan en
                // la última (nada se destruye).
                while (columns.length > count) {
                    const removed = columns.pop();
                    columns[columns.length - 1].blocks = (columns[columns.length - 1].blocks || []).concat(removed.blocks || []);
                }

                // Expandir: nuevas columnas (o las que quedaron al apilar).
                while (columns.length < count) {
                    const reusable = (block._columns || []).find((c) => columns.indexOf(c) === -1 && !c.blocks.length);
                    columns.push(reusable || { id: this.newColumnId(columns.length), blocks: [] });
                }

                block._columns = columns;
                block.columns = columns;
                this.syncBlocksToLocale();
            },

            // Layout efectivo de un bloque: `columns_layout` o '1' (una columna).
            activeLayout(block) {
                return (block && block.columns_layout) ? block.columns_layout : '1';
            },

            // Columnas de un bloque: siempre el contrato canónico `columns`.
            blockColumns(block) {
                if (!block) return [];

                if (!Array.isArray(block.columns)) {
                    block.columns = [];
                }

                block.columns.forEach((c, i) => {
                    if (!c || typeof c !== 'object') return;
                    c.id = c.id || this.newColumnId(i);
                    c.blocks = Array.isArray(c.blocks) ? c.blocks : [];
                });

                return block.columns;
            },

            newColumnId(index) {
                return 'col_' + index + '_' + Math.random().toString(36).substring(2, 6);
            },

            // La sección vista como bloque (sin las claves del contenedor).
            sectionAsBlock(block) {
                const payload = JSON.parse(JSON.stringify(block));
                delete payload.columns;
                delete payload._columns;
                delete payload.columns_layout;
                delete payload.id;
                delete payload.is_collapsed;
                delete payload._col_buffer;
                return payload;
            },

            blockHasContent(payload) {
                if (!payload || typeof payload !== 'object') return false;
                return ['title', 'headline', 'content', 'body', 'summary', 'subtitle', 'widget_key'].some((k) => {
                    const v = payload[k];
                    return (typeof v === 'string' && v.trim() !== '') || (Array.isArray(v) && v.length > 0);
                });
            },

            clearSectionContent(block) {
                ['title', 'headline', 'content', 'body', 'summary', 'subtitle'].forEach((k) => { block[k] = ''; });
                block.items = [];
            },

            addColumnBlock(blockIndex, colIndex, type, forceMode = null) {
                const block = this.blocks[blockIndex];
                const cols = this.blockColumns(block);
                if (!block || !cols || !cols[colIndex]) return;

                const typeDef = this.availableTypes.find(t => t.type === type);
                const defaultPayload = typeDef?.defaultPayload || {};

                const newSubBlock = Object.assign({
                    id: 'sub_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6),
                    type: type,
                    title: '',
                    content: '',
                    cta_text: '',
                    cta_url: '',
                    image_url: '',
                    after_image_url: '',
                    before_label: 'Antes',
                    after_label: 'Después',
                    items: [],
                    form_slug: 'contacto',
                    category: '',
                    limit: 3
                }, JSON.parse(JSON.stringify(defaultPayload)));

                const mode = forceMode || this.insertionMode;

                if (mode === 'demo') {
                    this.populateDemoData(newSubBlock, type);
                }

                cols[colIndex].blocks.push(newSubBlock);
                this.syncBlocksToLocale();
                this.closeAddModal();
            },

            removeColumnBlock(blockIndex, colIndex, subIndex) {
                const block = this.blocks[blockIndex];
                const cols = this.blockColumns(block);
                if (!block || !cols || !cols[colIndex]) return;
                cols[colIndex].blocks.splice(subIndex, 1);
                this.syncBlocksToLocale();
            },

            moveColumnBlockUp(blockIndex, colIndex, subIndex) {
                if (subIndex <= 0) return;
                const cols = this.blockColumns(this.blocks[blockIndex]);
                const col = cols[colIndex];
                const item = col.blocks.splice(subIndex, 1)[0];
                col.blocks.splice(subIndex - 1, 0, item);
                this.syncBlocksToLocale();
            },

            moveColumnBlockDown(blockIndex, colIndex, subIndex) {
                const cols = this.blockColumns(this.blocks[blockIndex]);
                const col = cols[colIndex];
                if (subIndex >= col.blocks.length - 1) return;
                const item = col.blocks.splice(subIndex, 1)[0];
                col.blocks.splice(subIndex + 1, 0, item);
                this.syncBlocksToLocale();
            },

            ensureArray(obj, key) {
                if (!obj[key] || !Array.isArray(obj[key])) {
                    obj[key] = [];
                }
            },

            openMediaPickerForObject(targetObj, fieldName) {
                const targetId = 'obj_media_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6);
                window.addEventListener('file-selected', (e) => {
                    if (e.detail?.target === targetId && e.detail?.file) {
                        targetObj[fieldName] = e.detail.file.url;
                        this.syncBlocksToLocale();
                    }
                }, { once: true });

                window.dispatchEvent(new CustomEvent('open-filemanager', {
                    detail: { target: targetId }
                }));
            },

            openMediaPickerForMarkdown(targetObj) {
                const targetId = 'md_media_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6);
                window.addEventListener('file-selected', (e) => {
                    if (e.detail?.target === targetId && e.detail?.file) {
                        const file = e.detail.file;
                        const snippet = file.is_image
                            ? `\n![${file.name || 'imagen'}](${file.url})\n`
                            : `\n[${file.name || 'archivo'}](${file.url})\n`;
                        targetObj.content = (targetObj.content || '') + snippet;
                        this.syncBlocksToLocale();
                    }
                }, { once: true });

                window.dispatchEvent(new CustomEvent('open-filemanager', {
                    detail: { target: targetId }
                }));
            },

            appendSnippet(targetObj, prefix, suffix = '') {
                targetObj.content = (targetObj.content || '') + prefix + suffix;
                this.syncBlocksToLocale();
            },

            renderContentPreview(raw, format = 'markdown') {
                if (!raw || !raw.trim()) {
                    return '<div class="p-4 text-center text-gray-500 font-mono text-xs italic border border-dashed border-gray-800 rounded-lg">{{ __('admin.block_manager.preview_nothing') }}</div>';
                }
                if (format === 'html') {
                    return this.renderHtmlPreview(raw);
                }
                return this.renderMarkdownPreview(raw);
            },

            renderHtmlPreview(html) {
                if (!html) return '';
                return this.safeContentPreview(html);
            },

            safeContentPreview(html) {
                const template = document.createElement('template');
                template.innerHTML = html;
                const allowed = new Set(['p','div','span','h1','h2','h3','h4','h5','h6','a','img','figure','figcaption','strong','b','em','i','u','s','del','ins','small','sub','sup','blockquote','pre','code','ul','ol','li','dl','dt','dd','hr','br','table','thead','tbody','tfoot','tr','td','th','caption','details','summary']);
                const discard = new Set(['script','style','svg','math','iframe','object','embed','template','noscript','textarea','xmp','plaintext','form','input','button','select','option','link','meta','base']);
                for (const element of [...template.content.querySelectorAll('*')]) {
                    const tag = element.localName;
                    if (discard.has(tag)) { element.remove(); continue; }
                    if (!allowed.has(tag)) { element.replaceWith(...element.childNodes); continue; }
                    for (const attribute of [...element.attributes]) {
                        const name = attribute.name.toLowerCase();
                        const keep = ['class','title','lang','dir'].includes(name)
                            || (tag === 'a' && ['href','target','rel'].includes(name))
                            || (tag === 'img' && ['src','alt','width','height','loading'].includes(name))
                            || (['td','th'].includes(tag) && ['colspan','rowspan','scope'].includes(name));
                        let safe = true;
                        if (['href','src'].includes(name)) {
                            try {
                                const url = new URL(attribute.value.replace(/[\x00-\x20\x7f]+/g, ''), window.location.origin);
                                safe = (name === 'src' ? ['http:','https:'] : ['http:','https:','mailto:','tel:']).includes(url.protocol);
                            } catch { safe = false; }
                        }
                        if (!keep || !safe) element.removeAttribute(attribute.name);
                    }
                    if (tag === 'a' && element.target === '_blank') element.rel = 'noopener noreferrer';
                }
                return template.innerHTML;
            },

            parseMarkdownTables(text) {
                return text.replace(/(?:^\|.+?\|$\n?)+/gm, (tableBlock) => {
                    const lines = tableBlock.trim().split('\n');
                    if (lines.length < 2) return tableBlock;
                    const headerCells = lines[0].split('|').slice(1, -1).map(c => c.trim());
                    const isDelimiter = lines[1].includes('---') || lines[1].includes('-|-');
                    if (!isDelimiter) return tableBlock;

                    let tableHtml = '<div class="overflow-x-auto my-3"><table class="w-full text-xs text-left border border-gray-800 rounded-lg overflow-hidden">';
                    tableHtml += '<thead class="bg-[#1A1A1A] text-[#00FF99] border-b border-gray-800 font-mono"><tr>';
                    headerCells.forEach(cell => { tableHtml += `<th class="p-2.5 font-bold">${cell}</th>`; });
                    tableHtml += '</tr></thead><tbody class="divide-y divide-gray-900/60 font-mono">';

                    for (let i = 2; i < lines.length; i++) {
                        const rowCells = lines[i].split('|').slice(1, -1).map(c => c.trim());
                        tableHtml += '<tr class="hover:bg-gray-800/20 transition-colors">';
                        rowCells.forEach(cell => { tableHtml += `<td class="p-2.5 text-gray-300">${cell}</td>`; });
                        tableHtml += '</tr>';
                    }
                    tableHtml += '</tbody></table></div>';
                    return tableHtml;
                });
            },

            renderMarkdownPreview(raw) {
                if (!raw) return '<span class="text-gray-600 font-mono">&gt; Nada que previsualizar...</span>';

                let text = raw.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi,
                    '<div class="p-2 my-2 bg-amber-950/40 border border-amber-500/40 text-amber-300 font-mono text-[10px] rounded">{{ __('admin.block_manager.script_blocked') }}</div>'
                );

                text = text.replace(/```([a-zA-Z0-9_\-]*)\n([\s\S]*?)```/g, (match, lang, code) => {
                    const escaped = code.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    const badge = lang ? `<span class="text-[10px] uppercase text-gray-500 font-mono mb-1 block">${lang}</span>` : '';
                    return `<pre class="bg-[#0D0D0D] border border-gray-800 rounded-lg p-3 my-3 text-xs text-[#00FF99] font-mono overflow-x-auto shadow-inner">${badge}<code>${escaped}</code></pre>`;
                });

                text = text.replace(/`([^`\n]+)`/g, (match, code) => {
                    const escaped = code.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    return `<code class="px-1.5 py-0.5 rounded bg-black/60 border border-[#00FF99]/30 text-[#00FF99] font-mono text-[11px]">${escaped}</code>`;
                });

                text = this.parseMarkdownTables(text);

                text = text.replace(/^#### (.*$)/gim, '<h4 class="text-sm font-bold text-gray-200 mt-3 mb-1 font-mono">$1</h4>');
                text = text.replace(/^### (.*$)/gim, '<h3 class="text-base font-bold text-[#00FF99] mt-4 mb-2 font-mono flex items-center gap-1.5"><span class="text-xs">#</span> $1</h3>');
                text = text.replace(/^## (.*$)/gim, '<h2 class="text-lg font-bold text-white mt-5 mb-2 border-b border-gray-800 pb-1.5 font-mono flex items-center gap-2"><span class="text-[#00FF99]">&gt;</span> $1</h2>');
                text = text.replace(/^# (.*$)/gim, '<h1 class="text-xl sm:text-2xl font-black text-white mt-6 mb-3 font-mono border-b-2 border-[#00FF99]/40 pb-2">$1</h1>');

                text = text.replace(/^> (.*$)/gim, '<blockquote class="border-l-4 border-[#00FF99] pl-3 py-1 my-2 text-gray-300 italic bg-[#151515] rounded-r">$1</blockquote>');

                text = text.replace(/\*\*(.*?)\*\*/g, '<strong class="text-white font-bold">$1</strong>');
                text = text.replace(/__(.*?)__/g, '<strong class="text-white font-bold">$1</strong>');
                text = text.replace(/\*(.*?)\*/g, '<em class="italic text-gray-300">$1</em>');
                text = text.replace(/_(.*?)_/g, '<em class="italic text-gray-300">$1</em>');
                text = text.replace(/~~(.*?)~~/g, '<del class="line-through text-gray-500">$1</del>');

                text = text.replace(/!\[(.*?)\]\((.*?)\)/g, '<img alt="$1" src="$2" class="rounded-xl my-3 max-h-72 object-cover border border-gray-700 shadow-lg" />');
                text = text.replace(/\[(.*?)\]\((.*?)\)/g, '<a href="$2" target="_blank" class="text-[#00FF99] underline hover:text-[#00FF99]/80 font-bold">$1 &nearr;</a>');

                text = text.replace(/^---$/gim, '<hr class="my-4 border-gray-800" />');

                text = text.replace(/^\- (.*$)/gim, '<li class="ml-4 list-disc text-gray-300 my-0.5">$1</li>');
                text = text.replace(/^\* (.*$)/gim, '<li class="ml-4 list-disc text-gray-300 my-0.5">$1</li>');
                text = text.replace(/^(\d+)\. (.*$)/gim, '<li class="ml-4 list-decimal text-gray-300 my-0.5">$2</li>');

                text = text.replace(/\n\n/g, '<div class="h-3"></div>');
                text = text.replace(/\n/g, '<br />');

                return this.safeContentPreview(text);
            },

            formatHtmlString(html) {
                if (!html) return '';
                let tab = '  ';
                let result = '';
                let indent = 0;
                html.split(/>\s*</).forEach(element => {
                    if (element.match(/^\/\w/)) {
                        indent = Math.max(0, indent - 1);
                    }
                    result += tab.repeat(indent) + '<' + element + '>\n';
                    if (element.match(/^\x3c?\w[^>]*[^\/]$/) && !element.startsWith("input") && !element.startsWith("img") && !element.startsWith("br") && !element.startsWith("hr")) {
                        indent++;
                    }
                });
                return result.substring(1, result.length - 2);
            },

            filteredTypes() {
                return this.availableTypes.filter(item => {
                    const itemCat = (item.category === 'dynamic' || item.category === 'interactive' || item.category === 'widget') ? 'widget' : 'content';
                    const matchesCategory = this.activeCategory === 'all' 
                        || this.activeCategory === itemCat 
                        || this.activeCategory === item.category;
                    const q = this.searchQuery ? this.searchQuery.toLowerCase().trim() : '';
                    const matchesSearch = !q ||
                        (item.name && item.name.toLowerCase().includes(q)) ||
                        (item.desc && item.desc.toLowerCase().includes(q)) ||
                        (item.type && item.type.toLowerCase().includes(q));

                    // Dentro de una columna no se ofrecen los widgets que ya
                    // gestionan su propia rejilla (carrusel, features…): se
                    // añaden como sección, no anidados.
                    if (this.columnTarget && item.fullWidth) {
                        return false;
                    }

                    return matchesCategory && matchesSearch;
                });
            },

            // ICON & EMOJI PICKER STATE & METHODS
            showIconPickerModal: false,
            iconPickerTarget: null,
            iconPickerField: null,
            iconPickerSearch: '',
            iconPickerCategory: 'all',
            iconPickerManual: '',

            openIconPicker(targetObj, fieldName) {
                this.iconPickerTarget = targetObj;
                this.iconPickerField = fieldName;
                this.iconPickerSearch = '';
                this.iconPickerCategory = 'all';
                this.iconPickerManual = targetObj[fieldName] || '';
                this.showIconPickerModal = true;
            },

            selectIcon(val) {
                if (this.iconPickerTarget && this.iconPickerField) {
                    this.iconPickerTarget[this.iconPickerField] = val;
                    this.syncBlocksToLocale();
                }
                this.showIconPickerModal = false;
                this.iconPickerTarget = null;
                this.iconPickerField = null;
            },

            faIconsList: [
                // Populares & UI
                { name: 'Rayo / Energía', icon: 'fa-solid fa-bolt', category: 'popular', tags: 'rayo bolt energia rapido luz power' },
                { name: 'Estrella / Favorito', icon: 'fa-solid fa-star', category: 'popular', tags: 'estrella star rating favorito' },
                { name: 'Cohete / Lanzamiento', icon: 'fa-solid fa-rocket', category: 'popular', tags: 'cohete rocket launch startup despegar' },
                { name: 'Fuego / Novedad', icon: 'fa-solid fa-fire', category: 'popular', tags: 'fuego fire trending hot popular' },
                { name: 'Corazón / Me gusta', icon: 'fa-solid fa-heart', category: 'popular', tags: 'corazon heart amor like' },
                { name: 'Check / Verificado', icon: 'fa-solid fa-circle-check', category: 'popular', tags: 'check ok valid verificado exito' },
                { name: 'Bombilla / Idea', icon: 'fa-solid fa-lightbulb', category: 'popular', tags: 'bombilla idea luz innovacion' },
                { name: 'Engranaje / Config', icon: 'fa-solid fa-gear', category: 'popular', tags: 'engranaje gear settings opciones' },
                { name: 'Deslizadores / Filtro', icon: 'fa-solid fa-sliders', category: 'popular', tags: 'sliders filtros ajustar controles' },
                { name: 'Ojo / Visibilidad', icon: 'fa-solid fa-eye', category: 'popular', tags: 'ojo eye ver preview' },

                // Negocio & Finanzas
                { name: 'Gráfica Crecimiento', icon: 'fa-solid fa-chart-line', category: 'business', tags: 'grafica chart line rentabilidad crecimiento' },
                { name: 'Gráfica Barras', icon: 'fa-solid fa-chart-column', category: 'business', tags: 'barras metricas kpi estadisticas' },
                { name: 'Gráfica Circular', icon: 'fa-solid fa-chart-pie', category: 'business', tags: 'pie tarta porcentaje distribucion' },
                { name: 'Diamante / Calidad', icon: 'fa-solid fa-gem', category: 'business', tags: 'diamante gem joya premium valor' },
                { name: 'Balanza / Comparativa', icon: 'fa-solid fa-scale-balanced', category: 'business', tags: 'balanza justicia equilibrio comparar' },
                { name: 'Maletín / Empresa', icon: 'fa-solid fa-briefcase', category: 'business', tags: 'maletin briefcase trabajo consultoria' },
                { name: 'Monedas / Finanzas', icon: 'fa-solid fa-coins', category: 'business', tags: 'monedas dinero coins cash precio' },
                { name: 'Billetera / Pago', icon: 'fa-solid fa-wallet', category: 'business', tags: 'billetera wallet pago monedero' },
                { name: 'Tarjeta de Crédito', icon: 'fa-solid fa-credit-card', category: 'business', tags: 'tarjeta credit card pos visa' },
                { name: 'Apretón / Alianza', icon: 'fa-solid fa-handshake', category: 'business', tags: 'acuerdo pacto clientes partner' },
                { name: 'Diana / Objetivo', icon: 'fa-solid fa-bullseye', category: 'business', tags: 'objetivo dardo meta conversion' },
                { name: 'Trofeo / Éxito', icon: 'fa-solid fa-trophy', category: 'business', tags: 'trofeo premio copa ganador' },

                // Tecnología & Seguridad
                { name: 'Escudo / Protección', icon: 'fa-solid fa-shield-halved', category: 'tech', tags: 'escudo shield proteccion seguridad' },
                { name: 'Candado / Cifrado', icon: 'fa-solid fa-lock', category: 'tech', tags: 'candado lock clave password seguridad' },
                { name: 'Llave / Acceso', icon: 'fa-solid fa-key', category: 'tech', tags: 'llave key credenciales auth' },
                { name: 'Servidor / Nube', icon: 'fa-solid fa-server', category: 'tech', tags: 'servidor rack host backend data' },
                { name: 'Microchip / CPU', icon: 'fa-solid fa-microchip', category: 'tech', tags: 'chip procesador cpu hardware' },
                { name: 'Base de Datos', icon: 'fa-solid fa-database', category: 'tech', tags: 'base de datos sql storage records' },
                { name: 'Código / HTML', icon: 'fa-solid fa-code', category: 'tech', tags: 'codigo programacion tag html developer' },
                { name: 'Terminal / CLI', icon: 'fa-solid fa-terminal', category: 'tech', tags: 'terminal consola bash comandos agy' },
                { name: 'Red Conectada', icon: 'fa-solid fa-network-wired', category: 'tech', tags: 'red cables lan internet conexion' },
                { name: 'Nube / Cloud', icon: 'fa-solid fa-cloud', category: 'tech', tags: 'nube cloud hosting sync' },
                { name: 'Portátil / Laptop', icon: 'fa-solid fa-laptop-code', category: 'tech', tags: 'portatil laptop desarrollo programar' },
                { name: 'Móvil / Smartphone', icon: 'fa-solid fa-mobile-screen', category: 'tech', tags: 'movil smartphone pantalla responsive' },

                // Comunicación & Personas
                { name: 'Usuario / Perfil', icon: 'fa-solid fa-user', category: 'comm', tags: 'usuario persona avatar user cuenta' },
                { name: 'Equipo / Usuarios', icon: 'fa-solid fa-users', category: 'comm', tags: 'usuarios equipo comunidad clientes' },
                { name: 'Sobre / Correo', icon: 'fa-solid fa-envelope', category: 'comm', tags: 'correo email mensaje carta contacto' },
                { name: 'Teléfono / Llamada', icon: 'fa-solid fa-phone', category: 'comm', tags: 'telefono llamada call contacto' },
                { name: 'Comentario / Chat', icon: 'fa-solid fa-comment', category: 'comm', tags: 'comentario mensaje chat texto' },
                { name: 'Conversación', icon: 'fa-solid fa-comments', category: 'comm', tags: 'dialogo conversacion soporte foros' },
                { name: 'Avión de Papel', icon: 'fa-solid fa-paper-plane', category: 'comm', tags: 'enviar telegram transfer mensaje' },
                { name: 'Megáfono / Difusión', icon: 'fa-solid fa-bullhorn', category: 'comm', tags: 'megafono marketing publicidad aviso' },
                { name: 'Auriculares / Soporte', icon: 'fa-solid fa-headset', category: 'comm', tags: 'auriculares headset soporte ayuda help' },
                { name: 'Pregunta / FAQ', icon: 'fa-solid fa-circle-question', category: 'comm', tags: 'pregunta duda faq ayuda question' },
                { name: 'Información / Guía', icon: 'fa-solid fa-circle-info', category: 'comm', tags: 'informacion detalle guia info' },

                // Comercio & Medios
                { name: 'Bolsa de Compras', icon: 'fa-solid fa-bag-shopping', category: 'commerce', tags: 'bolsa compras shop tienda store' },
                { name: 'Carrito de Compra', icon: 'fa-solid fa-cart-shopping', category: 'commerce', tags: 'carrito cesta cart ecommerce' },
                { name: 'Caja / Paquete', icon: 'fa-solid fa-box', category: 'commerce', tags: 'caja paquete producto box envio' },
                { name: 'Almacén / Stock', icon: 'fa-solid fa-boxes-stacked', category: 'commerce', tags: 'almacen stock inventario mercancia' },
                { name: 'Etiqueta / Descuento', icon: 'fa-solid fa-tag', category: 'commerce', tags: 'etiqueta oferta descuento tag precio' },
                { name: 'Envío Rápido', icon: 'fa-solid fa-truck-fast', category: 'commerce', tags: 'camion transporte envio entrega delivery' },
                { name: 'Periódico / Blog', icon: 'fa-solid fa-newspaper', category: 'commerce', tags: 'periodico noticias prensa blog articulos' },
                { name: 'Documento / Archivo', icon: 'fa-solid fa-file-lines', category: 'commerce', tags: 'documento texto archivo contrato' },
                { name: 'Galería de Fotos', icon: 'fa-solid fa-images', category: 'commerce', tags: 'galeria fotos album imagenes' },
                { name: 'Planeta / Idiomas', icon: 'fa-solid fa-earth-americas', category: 'commerce', tags: 'planeta tierra mundo global idiomas' },
                { name: 'Ubicación / Mapa', icon: 'fa-solid fa-location-dot', category: 'commerce', tags: 'ubicacion mapa direccion gps' },
                { name: 'Reloj / Minutos', icon: 'fa-regular fa-clock', category: 'commerce', tags: 'reloj tiempo duracion horario' }
            ],

            emojisList: [
                { emoji: '⚡', name: 'Rayo', tags: 'rayo electricidad energia rapido' },
                { emoji: '🚀', name: 'Cohete', tags: 'cohete startup innovacion lanzamiento' },
                { emoji: '🔥', name: 'Fuego', tags: 'fuego hot trending novedad' },
                { emoji: '💡', name: 'Bombilla', tags: 'bombilla idea creatividad luz' },
                { emoji: '💎', name: 'Diamante', tags: 'diamante lujo premium valor' },
                { emoji: '🛡️', name: 'Escudo', tags: 'escudo seguridad proteccion' },
                { emoji: '🔒', name: 'Candado', tags: 'candado bloqueo cifrado clave' },
                { emoji: '⚙️', name: 'Engranaje', tags: 'ajustes configuracion motor' },
                { emoji: '💻', name: 'Portátil', tags: 'ordenador tecnologia codigo' },
                { emoji: '📊', name: 'Gráfico', tags: 'estadisticas metricas negocio' },
                { emoji: '📈', name: 'Crecimiento', tags: 'subida finanzas exito' },
                { emoji: '🎯', name: 'Diana', tags: 'objetivo foco meta acierto' },
                { emoji: '🛠️', name: 'Herramientas', tags: 'construccion reparacion dev' },
                { emoji: '✨', name: 'Destellos', tags: 'magia nuevo calidad especial' },
                { emoji: '🛒', name: 'Carrito', tags: 'tienda compras catalogo' },
                { emoji: '📦', name: 'Paquete', tags: 'envio entrega producto box' },
                { emoji: '🏷️', name: 'Etiqueta', tags: 'precio oferta descuento tag' },
                { emoji: '💰', name: 'Bolsa dinero', tags: 'dinero ahorro beneficio cash' },
                { emoji: '💳', name: 'Tarjeta', tags: 'pago banco checkout credito' },
                { emoji: '📢', name: 'Megáfono', tags: 'anuncio marketing difusion' },
                { emoji: '💬', name: 'Burbuja', tags: 'chat mensaje conversacion texto' },
                { emoji: '✉️', name: 'Sobre', tags: 'correo contacto email' },
                { emoji: '📞', name: 'Teléfono', tags: 'llamada soporte contacto ring' },
                { emoji: '🌐', name: 'Mundo', tags: 'web global internet multi-idioma' },
                { emoji: '⭐', name: 'Estrella', tags: 'favorito destacado rating star' },
                { emoji: '🏆', name: 'Trofeo', tags: 'premio exito ganador copa' },
                { emoji: '🤝', name: 'Apretón', tags: 'acuerdo clientes confianza pacto' },
                { emoji: '✅', name: 'Check verde', tags: 'aprobado correcto si ok' },
                { emoji: '❌', name: 'Cruz roja', tags: 'error cancelado no' },
                { emoji: '⚠️', name: 'Alerta', tags: 'cuidado aviso atencion warning' },
                { emoji: 'ℹ️', name: 'Info', tags: 'informacion ayuda detalle' },
                { emoji: '❓', name: 'Interrogación', tags: 'pregunta duda faq' },
                { emoji: '👍', name: 'Pulgar arriba', tags: 'bien like ok' },
                { emoji: '🎉', name: 'Celebración', tags: 'fiesta evento confeti' },
                { emoji: '🌍', name: 'Tierra', tags: 'planeta internacional paises' },
                { emoji: '⏱️', name: 'Cronómetro', tags: 'tiempo rapidez velocidad' },
                { emoji: '🔔', name: 'Campana', tags: 'notificacion aviso alerta' },
                { emoji: '📍', name: 'Chincheta', tags: 'ubicacion mapa sede pin' }
            ],

            filteredPickerIcons() {
                const q = this.iconPickerSearch.toLowerCase().trim();
                return this.faIconsList.filter(item => {
                    const matchesCat = this.iconPickerCategory === 'all' || this.iconPickerCategory === item.category;
                    const matchesSearch = !q || item.name.toLowerCase().includes(q) || item.icon.toLowerCase().includes(q) || item.tags.toLowerCase().includes(q);
                    return matchesCat && matchesSearch;
                });
            },

            filteredPickerEmojis() {
                const q = this.iconPickerSearch.toLowerCase().trim();
                return this.emojisList.filter(item => {
                    return !q || item.name.toLowerCase().includes(q) || item.emoji.includes(q) || item.tags.toLowerCase().includes(q);
                });
            }
        }));
    }

    if (window.Alpine) {
        initCmsBlockManager();
    } else {
        document.addEventListener('alpine:init', initCmsBlockManager);
    }
})();
</script>

<div x-data="blockManager(@js($sectionsByLocale), 'es', @js($availableTypesJs), @js($languagesJs), @json($target ? (int) $target : null))"
     @keydown.escape.window="if (isFullscreen) toggleFullscreen()"
     @keydown.window="if ((event.ctrlKey || event.metaKey) && event.shiftKey && (event.key === 'F' || event.key === 'f')) { event.preventDefault(); toggleFullscreen(); }"
     :class="isFullscreen ? 'fixed inset-0 z-[100] bg-[#0A0A0A] w-screen h-screen flex flex-col overflow-hidden text-xs' : 'space-y-6 text-xs'"
     class="font-mono transition-all duration-200">

    <!-- Hidden Input containing JSON Payload -->
    <input type="hidden" name="{{ $name }}" :value="getCleanSerializedSections()">

    <!-- FULLSCREEN STICKY TOP BAR (Visible only in Fullscreen Mode) -->
    <div x-show="isFullscreen"
         class="shrink-0 bg-[#121212]/95 backdrop-blur-md border-b border-[#00FF99]/30 px-4 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-3 z-30 shadow-2xl">
        <!-- Brand & Language Switcher in Fullscreen -->
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2">
                <span class="text-sm font-bold text-white tracking-tight">{{ config('cms.name', 'CMS') }}</span>
                <span class="px-2 py-0.5 rounded bg-[#00FF99]/10 border border-[#00FF99]/40 text-[#00FF99] text-[10px] font-bold uppercase tracking-wider flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#00FF99] animate-pulse"></span>
                    <span>{{ __('admin.block_manager.fullscreen_canvas') }}</span>
                </span>
            </div>

            <!-- Language Switcher Pills -->
            <div class="flex items-center gap-1 bg-black/60 p-1 rounded-lg border border-gray-800">
                <template x-for="lang in languages" :key="lang.code">
                    <button type="button"
                            x-show="isLanguageActive(lang.code)"
                            @click="switchLocale(lang.code)"
                            :class="activeLocale === lang.code ? 'bg-[#00FF99] text-black font-bold shadow-[0_0_10px_rgba(0,255,153,0.3)]' : 'text-gray-400 hover:text-white bg-[#181818] border border-gray-800'"
                            class="px-2.5 py-1 rounded text-xs transition-all flex items-center gap-1.5">
                        <span x-text="lang.flag"></span>
                        <span x-text="lang.code.toUpperCase()"></span>
                        <span x-show="sectionsByLocale[lang.code] && sectionsByLocale[lang.code].length > 0"
                              :class="activeLocale === lang.code ? 'bg-black' : 'bg-[#00FF99]'"
                              class="w-1.5 h-1.5 rounded-full" title="{{ __('admin.block_manager.has_sections') }}"></span>
                    </button>
                </template>
            </div>
        </div>

        <!-- Center: Canvas Width Toggle & Block Stats -->
        <div class="hidden md:flex items-center gap-3">
            <div class="flex items-center gap-1 bg-black/60 p-1 rounded-lg border border-gray-800">
                <span class="text-[10px] text-gray-500 uppercase font-bold px-1.5">{{ __('admin.block_manager.canvas') }}</span>
                <button type="button" @click="canvasWidth = 'container'"
                        :class="canvasWidth === 'container' ? 'bg-[#1F1F1F] text-[#00FF99] border border-[#00FF99]/40 font-bold' : 'text-gray-400 hover:text-white'"
                        class="px-2 py-0.5 rounded text-[11px] transition-all">
                    Centrado (1280px)
                </button>
                <button type="button" @click="canvasWidth = 'full'"
                        :class="canvasWidth === 'full' ? 'bg-[#1F1F1F] text-[#00FF99] border border-[#00FF99]/40 font-bold' : 'text-gray-400 hover:text-white'"
                        class="px-2 py-0.5 rounded text-[11px] transition-all">
                    100% Panorámico
                </button>
            </div>
            <span class="text-gray-500 text-xs font-mono" x-text="blocks.length + ' secciones en ' + activeLocale.toUpperCase()"></span>
        </div>

        <!-- Right: Actions & Exit Fullscreen -->
        <div class="flex items-center gap-2">
            <x-admin.button variant="ghost" size="sm" @click="toggleCollapseAll(true)" icon="fa-solid fa-angles-up" title="{{ __('admin.block_manager.collapse_all_tooltip') }}">
                {{ __('admin.block_manager.collapse_all') }}
            </x-admin.button>
            <x-admin.button variant="ghost" size="sm" @click="toggleCollapseAll(false)" icon="fa-solid fa-angles-down" title="{{ __('admin.block_manager.expand_all_tooltip') }}">
                {{ __('admin.block_manager.expand_all') }}
            </x-admin.button>

            <!-- Extension point: external modules may inject toolbar actions here -->
            @stack('block_manager_actions')

            <!-- Add Section Button -->
            <button type="button"
                    @click="openAddModalAt(null)"
                    class="btn-neon text-xs py-1.5 px-3 flex items-center gap-1.5">
                <span>+</span>
                <span>{{ __('admin.block_manager.add_section') }}</span>
            </button>

            <!-- Direct Save Button from Fullscreen -->
            <button type="button"
                    @click="saveForm()"
                    class="px-3 py-1.5 bg-[#00FF99] hover:bg-[#00FF99]/90 text-black font-bold rounded text-xs flex items-center gap-1.5 shadow-[0_0_15px_rgba(0,255,153,0.3)] transition-all"
                    title="{{ __('admin.block_manager.save_tooltip') }}">
                <i class="fa-solid fa-floppy-disk"></i>
                <span class="hidden sm:inline">{{ __('admin.block_manager.save') }}</span>
            </button>

            <!-- Exit Fullscreen Button -->
            <button type="button"
                    @click="toggleFullscreen()"
                    class="px-3 py-1.5 rounded bg-red-950/40 hover:bg-red-900/60 border border-red-500/40 text-red-300 hover:text-white font-bold text-xs flex items-center gap-1.5 transition-colors"
                    title="{{ __('admin.block_manager.exit_fullscreen_tooltip') }}">
                <i class="fa-solid fa-xmark"></i>
                <span class="hidden sm:inline">{{ __('admin.block_manager.exit_fullscreen') }}</span>
            </button>
        </div>
    </div>

    <!-- STANDARD TOP ACTION BAR (Visible when NOT in Fullscreen) -->
    <div x-show="!isFullscreen" class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-gray-800">
        <div>
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="text-[#00FF99]">&gt;_</span>
                <span>{{ __('admin.block_manager.title') }}</span>
                <span class="px-2 py-0.5 rounded bg-black/60 border border-[#00FF99]/40 text-[#00FF99] text-[10px] uppercase font-bold" x-text="@js(__('admin.block_manager.language')) + ' ' + activeLocale.toUpperCase()"></span>
            </h3>
            <p class="text-gray-400 text-[11px] mt-0.5">
                Organiza los bloques de contenido y widgets dinámicos para este idioma. Puedes personalizar fondos, separadores y animaciones.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <!-- Extension point: external modules may inject toolbar actions here -->
            @stack('block_manager_actions')

            <!-- Fullscreen Trigger Button -->
            <button type="button"
                    @click="toggleFullscreen()"
                    class="px-2.5 py-1 bg-[#1A1A1A] hover:bg-[#00FF99]/10 text-gray-300 hover:text-[#00FF99] rounded border border-gray-700 hover:border-[#00FF99] text-[11px] font-bold flex items-center gap-1.5 transition-colors"
                    title="{{ __('admin.block_manager.fullscreen_tooltip') }}">
                <i class="fa-solid fa-expand"></i>
                <span class="hidden sm:inline">{{ __('admin.block_manager.fullscreen') }}</span>
            </button>

            <x-admin.button variant="ghost" size="sm" @click="toggleCollapseAll(true)" icon="fa-solid fa-angles-up" title="{{ __('admin.block_manager.collapse_all_tooltip') }}">
                {{ __('admin.block_manager.collapse_all') }}
            </x-admin.button>
            <x-admin.button variant="ghost" size="sm" @click="toggleCollapseAll(false)" icon="fa-solid fa-angles-down" title="{{ __('admin.block_manager.expand_all_tooltip') }}">
                {{ __('admin.block_manager.expand_all') }}
            </x-admin.button>
        </div>
    </div>

    <!-- CANVAS BODY WRAPPER (Scrollable in Fullscreen, Normal in Embedded) -->
    <div :class="isFullscreen ? 'flex-1 overflow-y-auto px-4 sm:px-8 py-6 sidebar-scroll' : ''">
        <div :class="isFullscreen ? (canvasWidth === 'container' ? 'max-w-7xl mx-auto w-full space-y-6' : 'w-full space-y-6') : 'space-y-6'">

            <!-- Empty State for Active Locale (Centered in Canvas) -->
            <div x-show="blocks.length === 0" class="my-6 p-10 border border-dashed border-gray-800 hover:border-gray-700 rounded-2xl text-center space-y-4 bg-[#121212]/50 max-w-xl mx-auto transition-all">
                <div class="w-14 h-14 rounded-2xl bg-[#1A1A1A] border border-[#00FF99]/30 flex items-center justify-center text-2xl text-[#00FF99] mx-auto shadow-[0_0_20px_rgba(0,255,153,0.15)]">
                    <i class="fa-solid fa-cubes"></i>
                </div>
                <div>
                    <h4 class="text-white font-bold text-sm">{{ __('admin.block_manager.canvas_empty_title') }} <span x-text="activeLocale.toUpperCase()" class="text-[#00FF99]"></span></h4>
                    <p class="text-xs text-gray-400 mt-1">{{ __('admin.block_manager.canvas_empty_desc') }}</p>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                    <button type="button" @click="openAddModalAt(0)" class="btn-neon text-xs py-2 px-4 flex items-center gap-2 shadow-[0_0_15px_rgba(0,255,153,0.3)]">
                        <i class="fa-solid fa-plus text-[11px]"></i>
                        <span>{{ __('admin.block_manager.add_first') }}</span>
                    </button>
                    <div x-show="Object.keys(sectionsByLocale).some(k => k !== activeLocale && sectionsByLocale[k] && sectionsByLocale[k].length > 0)" class="flex items-center gap-2 bg-[#1A1A1A] p-1.5 rounded-lg border border-gray-800">
                        <select x-model="copySourceLocale" class="bg-[#121212] border border-gray-700 text-gray-300 text-xs rounded px-2 py-1">
                            <template x-for="(locBlocks, locCode) in sectionsByLocale" :key="locCode">
                                <option :value="locCode" x-show="locCode !== activeLocale && locBlocks.length > 0" x-text="@js(__('admin.block_manager.copy_from')).replace(':locale', locCode.toUpperCase()).replace(':count', locBlocks.length)"></option>
                            </template>
                        </select>
                        <button type="button" @click="copyBlocksFrom(copySourceLocale)" class="px-3 py-1 bg-blue-600 hover:bg-blue-500 text-white rounded text-xs font-bold">
                            Copiar bloques &rarr;
                        </button>
                    </div>
                </div>
            </div>

    <!-- Blocks List -->
    <div class="space-y-4 pb-6">
        <template x-for="(block, index) in blocks" :key="block.id || index">
            <div :data-block-index="index">
                <!-- Top Insertion Zone (visible on hover before the first block) -->
                <div x-show="index === 0" class="group/insert relative z-20 py-2 -my-1 transition-all">
                    <div class="h-[2px] w-full bg-gradient-to-r from-transparent via-[#00FF99]/40 to-transparent opacity-0 group-hover/insert:opacity-100 transition-opacity"></div>
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none group-hover/insert:pointer-events-auto">
                        <button type="button"
                                @click="openAddModalAt(0)"
                                class="opacity-0 group-hover/insert:opacity-100 scale-95 group-hover/insert:scale-100 transition-all duration-200 px-3 py-0.5 rounded-full bg-[#161616] hover:bg-[#00FF99] text-[#00FF99] hover:text-black border border-[#00FF99]/50 hover:border-[#00FF99] text-[11px] font-bold flex items-center gap-1.5 shadow-[0_0_15px_rgba(0,255,153,0.3)]">
                            <i class="fa-solid fa-plus text-[10px]"></i>
                            <span>{{ __('admin.block_manager.add_section_start') }}</span>
                        </button>
                    </div>
                </div>

                <div class="bg-[#141414] border border-gray-800 hover:border-gray-700 rounded-2xl overflow-hidden transition-all shadow-xl">

                <!-- Block Header Toolbar -->
                <div class="bg-[#181818] px-4 py-3 border-b border-gray-800 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-3 cursor-pointer select-none" @click="block.is_collapsed = !block.is_collapsed">
                        <span class="text-gray-500 font-bold font-mono" x-text="'#' + (index + 1)"></span>
                        <i x-show="isFontAwesomeIcon(typeDef(block.type).icon)" :class="typeDef(block.type).icon" class="text-base text-[#00FF99]" x-cloak></i>
                        <span x-show="!isFontAwesomeIcon(typeDef(block.type).icon)" class="text-base text-[#00FF99] font-bold" x-text="typeGlyph(block.type)" x-cloak></span>
                        <div>
                            <span class="font-bold text-white uppercase text-xs" x-text="availableTypes.find(t => t.type === block.type)?.name || block.type"></span>
                            <span x-show="block.title" class="text-gray-400 text-[11px] ml-2" x-text="'- ' + block.title"></span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <!-- Column Layout Selector -->
                        <div class="flex items-center gap-1 bg-[#101010] p-1 rounded-lg border border-gray-800">
                            <span class="text-[10px] text-gray-400 font-bold px-1 uppercase font-mono">{{ __('admin.block_manager.columns') }}</span>
                            <x-admin.button variant="ghost" size="sm" @click="setColumnsLayout(index, '1')" :alpine-active="$layoutActiveExpr('1')" title="1 Columna (100%)">1</x-admin.button>
                            <x-admin.button variant="ghost" size="sm" @click="setColumnsLayout(index, '2')" :alpine-active="$layoutActiveExpr('2')" title="2 Columnas (50/50)">2</x-admin.button>
                            <x-admin.button variant="ghost" size="sm" @click="setColumnsLayout(index, '1-2')" :alpine-active="$layoutActiveExpr('1-2')" title="2 Columnas (33/66)">1:2</x-admin.button>
                            <x-admin.button variant="ghost" size="sm" @click="setColumnsLayout(index, '2-1')" :alpine-active="$layoutActiveExpr('2-1')" title="2 Columnas (66/33)">2:1</x-admin.button>
                            <x-admin.button variant="ghost" size="sm" @click="setColumnsLayout(index, '3')" :alpine-active="$layoutActiveExpr('3')" title="3 Columnas">3</x-admin.button>
                            <x-admin.button variant="ghost" size="sm" @click="setColumnsLayout(index, '4')" :alpine-active="$layoutActiveExpr('4')" title="4 Columnas">4</x-admin.button>
                        </div>

                        <!-- Botón Emergente de Apariencia -->
                        <button type="button" @click="openAppearanceModal(index)" class="px-2 py-1 rounded bg-[#101010] hover:bg-gray-800 border border-gray-700 hover:border-[#00FF99] text-gray-300 hover:text-[#00FF99] text-xs font-bold flex items-center gap-1.5 transition-colors" title="{{ __('admin.block_manager.appearance_tooltip') }}">
                            <i class="fa-solid fa-palette"></i>
                            <span class="hidden sm:inline">{{ __('admin.block_manager.appearance') }}</span>
                        </button>

                        <!-- Controles de Reordenación (↑ / ↓) -->
                        <button type="button" @click="moveUp(index)" :disabled="index === 0" class="p-1.5 rounded hover:bg-gray-800 text-gray-400 hover:text-white disabled:opacity-20 transition-colors" title="{{ __('admin.block_manager.move_up') }}">
                            <span class="text-sm font-bold">↑</span>
                        </button>
                        <button type="button" @click="moveDown(index)" :disabled="index === blocks.length - 1" class="p-1.5 rounded hover:bg-gray-800 text-gray-400 hover:text-white disabled:opacity-20 transition-colors" title="{{ __('admin.block_manager.move_down') }}">
                            <span class="text-sm font-bold">↓</span>
                        </button>
                        <button type="button" @click="cloneBlock(index)" class="p-1.5 rounded hover:bg-gray-800 text-gray-400 hover:text-[#00FF99] transition-colors" title="{{ __('admin.block_manager.duplicate') }}"><i class="fa-solid fa-copy"></i></button>
                        
                        <!-- Plegar / Desplegar -->
                        <button type="button" @click="block.is_collapsed = !block.is_collapsed" class="p-1.5 rounded hover:bg-gray-800 text-gray-400 hover:text-white transition-colors" title="{{ __('admin.block_manager.collapse_toggle') }}">
                            <svg class="w-4 h-4 transition-transform duration-200" :class="block.is_collapsed ? '-rotate-90 text-gray-500' : 'rotate-0 text-[#00FF99]'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <button type="button" @click="removeBlock(index)" class="p-1.5 rounded hover:bg-red-950/40 text-red-400 hover:text-red-300 ml-1 transition-colors" title="{{ __('admin.block_manager.delete_section') }}">&times;</button>
                    </div>
                </div>

                <!-- Block Body (Collapsible) -->
                <div x-show="!block.is_collapsed" class="p-5 space-y-6">

                    <!-- MULTI-COLUMN SECTION EDITOR -->
                    <template x-if="block.columns_layout && block.columns_layout !== '1'">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between pb-2 border-b border-gray-800">
                                <div class="text-xs text-gray-400 font-mono flex items-center gap-2">
                                    <span class="text-[#00FF99] font-bold">▦ {{ __('admin.block_manager.columns_layout') }}</span>
                                    <span class="px-2 py-0.5 rounded bg-gray-800 text-white font-bold" x-text="block.columns_layout === '2' ? '2 Columnas (50% / 50%)' : (block.columns_layout === '1-2' ? '2 Columnas (33% / 66%)' : (block.columns_layout === '2-1' ? '2 Columnas (66% / 33%)' : (block.columns_layout === '3' ? '3 Columnas (33% / 33% / 33%)' : '4 Columnas')))"></span>
                                </div>
                            </div>

                            <!-- Columns Grid -->
                            <div class="grid gap-6"
                                 :class="block.columns_layout === '2' ? 'grid-cols-1 md:grid-cols-2' : (block.columns_layout === '3' ? 'grid-cols-1 md:grid-cols-3' : (block.columns_layout === '4' ? 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4' : 'grid-cols-1 md:grid-cols-3'))">
                                <template x-for="(col, colIdx) in blockColumns(block)" :key="col.id || colIdx">
                                    <div class="bg-[#101010] border border-gray-800 rounded-xl p-4 flex flex-col justify-between"
                                         :class="block.columns_layout === '1-2' && colIdx === 1 ? 'md:col-span-2' : (block.columns_layout === '2-1' && colIdx === 0 ? 'md:col-span-2' : 'col-span-1')">
                                        
                                        <!-- Column Header -->
                                        <div class="flex items-center justify-between pb-3 mb-3 border-b border-gray-800/80">
                                            <span class="text-xs font-bold text-gray-300 font-mono" x-text="'Columna ' + (colIdx + 1) + (block.columns_layout === '1-2' ? (colIdx === 0 ? ' (33%)' : ' (66%)') : (block.columns_layout === '2-1' ? (colIdx === 0 ? ' (66%)' : ' (33%)') : ''))"></span>

                                            <div class="flex items-center gap-1.5">
                                                <button type="button"
                                                        @click="openColumnAppearanceModal(index, colIdx)"
                                                        class="p-1 text-gray-400 hover:text-[#00FF99] text-xs"
                                                        title="{{ __('admin.block_manager.column_appearance_tooltip') }}">
                                                    <i class="fa-solid fa-palette"></i>
                                                </button>

                                                <!-- Un único flujo: el mismo modal de bloques -->
                                                <button type="button"
                                                        @click="openAddModalAt(null, { blockIndex: index, colIndex: colIdx })"
                                                        class="px-2 py-1 rounded bg-[#101010] hover:bg-[#00FF99] text-[#00FF99] hover:text-black border border-[#00FF99]/40 hover:border-[#00FF99] text-[11px] font-bold flex items-center gap-1.5 transition-colors">
                                                    <i class="fa-solid fa-plus text-[10px]"></i>
                                                    <span>{{ __('admin.block_manager.add_block') }}</span>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Blocks inside this column -->
                                        <div class="space-y-4 min-h-[100px]">
                                            <template x-if="!col.blocks || col.blocks.length === 0">
                                                <button type="button"
                                                        @click="openAddModalAt(null, { blockIndex: index, colIndex: colIdx })"
                                                        class="w-full text-center py-8 border border-dashed border-gray-800 hover:border-[#00FF99]/60 hover:bg-[#00FF99]/[0.03] rounded-lg text-gray-500 hover:text-[#00FF99] text-xs font-mono transition-colors">
                                                    <i class="fa-solid fa-plus mr-1.5"></i>{{ __('admin.block_manager.add_block_empty') }}
                                                </button>
                                            </template>

                                            <template x-for="(subBlock, subIdx) in (col.blocks || [])" :key="subBlock.id || subIdx">
                                                <div class="bg-[#161616] border border-gray-800 rounded-lg p-3 space-y-3">
                                                    <!-- Sub-block header -->
                                                    <div class="flex items-center justify-between pb-2 border-b border-gray-800">
                                                        <div class="flex items-center gap-2">
                                                            <i x-show="isFontAwesomeIcon(typeDef(subBlock.type).icon)" :class="typeDef(subBlock.type).icon" class="text-xs text-[#00FF99]" x-cloak></i>
                                                            <span x-show="!isFontAwesomeIcon(typeDef(subBlock.type).icon)" class="text-xs text-[#00FF99] font-bold" x-text="typeGlyph(subBlock.type)" x-cloak></span>
                                                            <span class="text-xs font-bold text-white uppercase font-mono" x-text="availableTypes.find(t => t.type === subBlock.type)?.name || subBlock.type"></span>
                                                        </div>
                                                        <div class="flex items-center gap-1">
                                                            <button type="button"
                                                                    @click="openAppearanceModal(index, { colIndex: colIdx, subIndex: subIdx })"
                                                                    class="p-1 text-gray-400 hover:text-[#00FF99] text-xs"
                                                                    title="{{ __('admin.block_manager.appearance_tooltip') }}">
                                                                <i class="fa-solid fa-palette"></i>
                                                            </button>
                                                            <button type="button" @click="moveColumnBlockUp(index, colIdx, subIdx)" :disabled="subIdx === 0" class="p-1 text-gray-400 hover:text-white disabled:opacity-20 text-xs font-bold" title="{{ __('admin.block_manager.move_up_short') }}">↑</button>
                                                            <button type="button" @click="moveColumnBlockDown(index, colIdx, subIdx)" :disabled="subIdx === col.blocks.length - 1" class="p-1 text-gray-400 hover:text-white disabled:opacity-20 text-xs font-bold" title="{{ __('admin.block_manager.move_down_short') }}">↓</button>
                                                            <button type="button" @click="removeColumnBlock(index, colIdx, subIdx)" class="p-1 text-red-400 hover:text-red-300 text-xs font-bold" title="{{ __('admin.block_manager.delete') }}">&times;</button>
                                                        </div>
                                                    </div>

                                                    <!-- Sub-block Full Form Body (100% Parity with Root Blocks) -->
                                                    <div class="pt-1">
                                                        @include('components.admin.blocks._block_form_body', [
                                                            'blockKey' => 'subBlock',
                                                            'dynamicPayloads' => $dynamicPayloads,
                                                            'isSubBlock' => true,
                                                        ])
                                                    </div>
                                                </div>
                                            </template>
                                        </div>

                                        <!-- Mismo flujo de añadir, al final de la columna -->
                                        <button type="button"
                                                @click="openAddModalAt(null, { blockIndex: index, colIndex: colIdx })"
                                                x-show="col.blocks && col.blocks.length > 0"
                                                x-cloak
                                                class="mt-3 w-full py-2 rounded-lg border border-dashed border-gray-800 hover:border-[#00FF99]/60 hover:text-[#00FF99] text-gray-500 text-[11px] font-mono transition-colors">
                                            <i class="fa-solid fa-plus mr-1"></i>{{ __('admin.block_manager.add_block_here') }}
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- SINGLE-BLOCK SECTION EDITOR (Cuando columns_layout es 1) -->
                    <div x-show="!block.columns_layout || block.columns_layout === '1'" class="space-y-6">
                        @include('components.admin.blocks._block_form_body', [
                            'blockKey' => 'block',
                            'dynamicPayloads' => $dynamicPayloads,
                            'isSubBlock' => false,
                        ])
                    </div>

                </div>

                <!-- Intermediate / Bottom Insertion Zone (visible on hover between blocks or at the end) -->
                <div class="group/insert relative z-20 py-2.5 -my-1 transition-all">
                    <div class="h-[2px] w-full bg-gradient-to-r from-transparent via-[#00FF99]/40 to-transparent opacity-0 group-hover/insert:opacity-100 transition-opacity"></div>
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none group-hover/insert:pointer-events-auto">
                        <button type="button"
                                @click="openAddModalAt(index + 1)"
                                class="opacity-0 group-hover/insert:opacity-100 scale-95 group-hover/insert:scale-100 transition-all duration-200 px-3 py-0.5 rounded-full bg-[#161616] hover:bg-[#00FF99] text-[#00FF99] hover:text-black border border-[#00FF99]/50 hover:border-[#00FF99] text-[11px] font-bold flex items-center gap-1.5 shadow-[0_0_15px_rgba(0,255,153,0.3)]">
                            <i class="fa-solid fa-plus text-[10px]"></i>
                            <span x-text="index === blocks.length - 1 ? @js(__('admin.block_manager.add_section_end')) : @js(__('admin.block_manager.add_section_here'))"></span>
                        </button>
                    </div>
                </div>
            </div>
            </div>
        </template>
    </div>

        </div>
    </div>

    <!-- Add Section / Widget Modal with Demo vs Blank Choice -->
    <div x-show="showAddModal"
         @click.self="showAddModal = false"
         @keydown.escape.window="showAddModal = false"
         x-transition
         x-cloak
         class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/40 backdrop-blur-md"
         style="display: none;">
        <div class="bg-[#141414] border border-[#00FF99]/40 max-w-2xl w-full rounded-2xl p-6 font-mono space-y-4 shadow-[0_0_50px_rgba(0,0,0,0.9)] max-h-[90vh] flex flex-col"
             @click.outside="showAddModal = false">

            <!-- Modal Header with Insertion Mode Selector & Position Indicator -->
            <div class="flex items-center justify-between pb-3 border-b border-gray-800">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <span>&gt;</span>
                            <span x-show="!columnTarget">{{ __('admin.block_manager.add_section') }} / Widget</span>
                            <span x-show="columnTarget" x-cloak>{{ __('admin.block_manager.add_block_to_column') }}</span>
                        </h3>
                        <span x-show="columnTarget" x-cloak class="px-2 py-0.5 rounded bg-[#00FF99]/10 text-[#00FF99] border border-[#00FF99]/30 text-[10px] font-bold"
                              x-text="'Columna ' + (columnTarget ? (columnTarget.colIndex + 1) : 1)"></span>
                        <span x-show="insertIndex === 0" class="px-2 py-0.5 rounded bg-[#00FF99]/10 text-[#00FF99] border border-[#00FF99]/30 text-[10px] font-bold">
                            Al principio (#1)
                        </span>
                        <span x-show="insertIndex !== null && insertIndex > 0 && insertIndex < blocks.length" class="px-2 py-0.5 rounded bg-sky-500/10 text-sky-400 border border-sky-500/30 text-[10px] font-bold" x-text="@js(__('admin.block_manager.at_position')).replace(':n', insertIndex + 1)"></span>
                        <span x-show="insertIndex === null || insertIndex >= blocks.length" class="px-2 py-0.5 rounded bg-purple-500/10 text-purple-300 border border-purple-500/30 text-[10px] font-bold">
                            Al final
                        </span>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-0.5">{{ __('admin.block_manager.insert_block_desc') }}</p>
                </div>
                <button @click="showAddModal = false" class="text-gray-400 hover:text-white text-xl">&times;</button>
            </div>

            <!-- Insertion Mode Tabs (Demo Data vs Empty) -->
            <div class="flex items-center justify-between p-2.5 bg-[#1A1A1A] rounded-xl border border-gray-800">
                <span class="text-xs text-gray-300 font-bold flex items-center gap-1.5">
                    <span class="flex items-center gap-1.5"><i class="fa-solid fa-bolt text-[#00FF99]"></i> {{ __('admin.block_manager.insertion_mode') }}</span>
                </span>
                <div class="flex items-center gap-2">
                    <button type="button"
                            @click="insertionMode = 'demo'"
                            :class="insertionMode === 'demo' ? 'bg-[#00FF99] text-black font-bold' : 'bg-gray-800 text-gray-300'"
                            class="px-3 py-1.5 rounded-lg text-xs transition-all flex items-center gap-1">
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-wand-magic-sparkles text-amber-400"></i> {{ __('admin.block_manager.mode_demo') }}</span>
                    </button>
                    <button type="button"
                            @click="insertionMode = 'empty'"
                            :class="insertionMode === 'empty' ? 'bg-[#00FF99] text-black font-bold' : 'bg-gray-800 text-gray-300'"
                            class="px-3 py-1.5 rounded-lg text-xs transition-all flex items-center gap-1">
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-file text-gray-400"></i> {{ __('admin.block_manager.mode_empty') }}</span>
                    </button>
                </div>
            </div>

            <!-- Search and Filter Tabs -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-1 bg-[#1A1A1A] p-1 rounded-lg border border-gray-800">
                    <button type="button" @click="activeCategory = 'all'"
                            :class="activeCategory === 'all' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white'"
                            class="px-3 py-1 rounded text-xs transition-all">{{ __('admin.block_manager.cat_all') }}</button>
                    <button type="button" @click="activeCategory = 'content'"
                            :class="activeCategory === 'content' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white'"
                            class="px-3 py-1 rounded text-xs transition-all">{{ __('admin.block_manager.tab_content') }}</button>
                    <button type="button" @click="activeCategory = 'widget'"
                            :class="activeCategory === 'widget' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white'"
                            class="px-3 py-1 rounded text-xs transition-all">{{ __('admin.block_manager.tab_widgets') }}</button>
                </div>

                <input type="text" x-model="searchQuery" placeholder="{{ __('admin.block_manager.search_component') }}" class="bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-1.5 text-white text-xs w-48">
            </div>

            <!-- Component Grid with Quick Insertion Options -->
            <div class="flex-1 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 gap-3 pr-1">
                <template x-for="item in filteredTypes()" :key="item.type">
                    <div class="p-3.5 rounded-xl bg-[#1A1A1A] border border-gray-800 hover:border-[#00FF99] transition-all group flex flex-col justify-between hover:bg-gray-800/40">
                        <div class="flex items-start gap-3">
                            <span class="text-xl shrink-0 w-10 h-10 flex items-center justify-center rounded-lg bg-black/50 border border-gray-800 group-hover:border-[#00FF99]/40 text-[#00FF99]">
                                <i x-show="isFontAwesomeIcon(item.icon)" :class="item.icon" x-cloak></i>
                                <span x-show="!isFontAwesomeIcon(item.icon)" class="font-bold" x-text="item.icon || (item.name ? item.name.trim().charAt(0).toUpperCase() : '?')" x-cloak></span>
                            </span>
                            <div>
                                <div class="font-bold text-white group-hover:text-[#00FF99] text-xs" x-text="item.name"></div>
                                <div class="text-[11px] text-gray-400 mt-0.5 leading-tight" x-text="item.desc"></div>
                            </div>
                        </div>
                        <div class="mt-3 pt-2.5 border-t border-gray-800 flex items-center justify-end gap-2">
                            <button type="button"
                                     @click="insertType(item.type, 'empty')"
                                     class="px-2.5 py-1 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded text-[10px]"
                                     title="{{ __('admin.block_manager.empty_tooltip') }}">
                                <i class="fa-solid fa-file mr-1"></i> En blanco
                            </button>
                            <button type="button"
                                     @click="insertType(item.type, 'demo')"
                                     class="px-2.5 py-1 bg-[#00FF99]/20 hover:bg-[#00FF99] text-[#00FF99] hover:text-black font-bold rounded text-[10px] border border-[#00FF99]/40"
                                     title="{{ __('admin.block_manager.demo_tooltip') }}">
                                <i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Con datos de ejemplo
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Modal Footer -->
            <div class="pt-3 border-t border-gray-800 flex justify-end">
                <button type="button" @click="closeAddModal()" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-lg text-xs">
                    Cancelar
                </button>
            </div>

        </div>
    </div>

    <!-- Appearance Modal (Ventana Emergente de Personalización Visual) -->
    <div x-show="showAppearanceModal && editingAppearanceIndex !== null"
         @click.self="closeAppearanceModal()"
         @keydown.escape.window="closeAppearanceModal()"
         x-transition
         x-cloak
         class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/40 backdrop-blur-md"
         style="display: none;">
        <div class="bg-[#141414] border border-[#00FF99]/40 rounded-2xl p-6 font-mono space-y-5 shadow-[0_0_50px_rgba(0,0,0,0.9)] flex flex-col transition-all duration-200"
             :class="isAppearanceMaximized ? 'w-[98vw] h-[96vh] max-w-none' : 'max-w-3xl w-full max-h-[90vh]'"
             @click.outside="closeAppearanceModal()">

            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-3 border-b border-gray-800 shrink-0">
                <div class="flex items-center gap-2">
                    <span class="text-xl text-[#00FF99]"><i class="fa-solid fa-palette"></i></span>
                    <div>
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <span>{{ __('admin.block_manager.appearance_title') }}</span>
                            <span class="text-[#00FF99]" x-text="'#' + (editingAppearanceIndex + 1)"></span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold border"
                                  :class="appearanceScope === 'section' ? 'bg-[#00FF99]/10 text-[#00FF99] border-[#00FF99]/40' : (appearanceScope === 'column' ? 'bg-amber-500/10 text-amber-300 border-amber-500/40' : 'bg-sky-500/10 text-sky-300 border-sky-500/40')"
                                  x-text="appearanceScope === 'section' ? @js(__('admin.block_manager.scope_section')) : (appearanceScope === 'column' ? @js(__('admin.block_manager.scope_column')) : @js(__('admin.block_manager.scope_block')))"></span>
                        </h3>
                        <p class="text-[11px] text-gray-400">{{ __('admin.block_manager.appearance_subtitle') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button"
                            @click="isAppearanceMaximized = !isAppearanceMaximized"
                            class="p-1.5 text-gray-400 hover:text-[#00FF99] rounded-lg hover:bg-white/5 transition-colors"
                            :title="isAppearanceMaximized ? 'Restaurar tamaño' : 'Maximizar'">
                        <i class="fa-solid text-sm" :class="isAppearanceMaximized ? 'fa-compress' : 'fa-expand'"></i>
                    </button>
                    <button type="button" @click="closeAppearanceModal()" class="text-gray-400 hover:text-white text-xl p-1 leading-none">&times;</button>
                </div>
            </div>

            <!-- Appearance Navigation Tabs -->
            <div class="flex items-center gap-2 border-b border-gray-800 pb-2 shrink-0">
                <button type="button"
                        @click="appearanceTab = 'container'"
                        :class="appearanceTab === 'container' ? 'bg-[#00FF99]/20 text-[#00FF99] border-[#00FF99]/40' : 'text-gray-400 hover:text-white border-transparent'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-box"></i>
                    <span>{{ __('admin.block_manager.container') }}</span>
                </button>
                <button type="button"
                        @click="appearanceTab = 'background'"
                        :class="appearanceTab === 'background' ? 'bg-[#00FF99]/20 text-[#00FF99] border-[#00FF99]/40' : 'text-gray-400 hover:text-white border-transparent'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-image"></i>
                    <span>{{ __('admin.block_manager.background') }}</span>
                </button>
                <button type="button"
                        @click="appearanceTab = 'dividers'"
                        :class="appearanceTab === 'dividers' ? 'bg-[#00FF99]/20 text-[#00FF99] border-[#00FF99]/40' : 'text-gray-400 hover:text-white border-transparent'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-water"></i>
                    <span>{{ __('admin.block_manager.dividers') }}</span>
                </button>
                <button type="button"
                        @click="appearanceTab = 'borders'"
                        :class="appearanceTab === 'borders' ? 'bg-[#00FF99]/20 text-[#00FF99] border-[#00FF99]/40' : 'text-gray-400 hover:text-white border-transparent'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-bolt"></i>
                    <span>{{ __('admin.block_manager.borders') }}</span>
                </button>
                <button type="button"
                        @click="appearanceTab = 'scroll'"
                        :class="appearanceTab === 'scroll' ? 'bg-[#00FF99]/20 text-[#00FF99] border-[#00FF99]/40' : 'text-gray-400 hover:text-white border-transparent'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    <span>{{ __('admin.block_manager.animations') }}</span>
                </button>
            </div>

            <!-- Appearance Content Tabs -->
            <div class="flex-1 overflow-y-auto pr-1">
                <template x-if="editingAppearanceIndex !== null && blocks[editingAppearanceIndex]">
                    <div>
                        <!-- TAB 1: CONTAINER -->
                        <div x-show="appearanceTab === 'container'" class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.container_style') }}</label>
                                    <select x-model="appearanceModel().container_style" @change="syncBlocksToLocale()" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs">
                                        <template x-for="style in containerStyles" :key="style.value">
                                            <option :value="style.value" x-text="style.label"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.container_content') }}</label>
                                    <select x-model="appearanceModel().content_width" @change="syncBlocksToLocale()" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs">
                                        <option value="centered">{{ __('admin.block_manager.container_standard') }}</option>
                                        <option value="narrow">{{ __('admin.block_manager.container_narrow') }}</option>
                                        <option value="full">{{ __('admin.block_manager.container_full') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: BACKGROUND -->
                        <div x-show="appearanceTab === 'background'" class="space-y-4">
                            <div>
                                <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.bg_type') }}</label>
                                <select x-model="appearanceModel().bg_type" @change="syncBlocksToLocale()" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs">
                                    <option value="none">{{ __('admin.block_manager.bg_none') }}</option>
                                    <option value="color">{{ __('admin.block_manager.bg_color') }}</option>
                                    <option value="image">{{ __('admin.block_manager.bg_image') }}</option>
                                    <option value="video">{{ __('admin.block_manager.bg_video') }}</option>
                                </select>
                            </div>

                            <div x-show="appearanceModel().bg_type === 'color'">
                                <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.bg_color_label') }}</label>
                                <input type="text" x-model="appearanceModel().bg_color" @input="syncBlocksToLocale()" placeholder="#111827 o linear-gradient(...)" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs font-mono">
                            </div>

                            <div x-show="appearanceModel().bg_type === 'image'" class="space-y-3">
                                <div>
                                    <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.bg_image_url') }}</label>
                                    <div class="flex gap-2">
                                        <input type="text" x-model="appearanceModel().bg_image_url" @input="syncBlocksToLocale()" placeholder="https://... o ruta multimedia" class="flex-1 bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs font-mono">
                                        <button type="button" @click="openMediaPickerForObject(blocks[editingAppearanceIndex], 'bg_image_url')" class="px-3 py-2 bg-gray-800 text-[#00FF99] rounded-lg font-bold"><i class="fa-solid fa-image"></i></button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.overlay') }}</label>
                                    <input type="text" x-model="appearanceModel().bg_image_overlay" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_manager.overlay_placeholder') }}" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs font-mono">
                                </div>

                                <!-- Comportamiento del fondo: solo donde hay fondo de verdad
                                     (Sección y Columna); nunca en el ámbito de Bloque. -->
                                <div x-show="appearanceScope !== 'block'">
                                    <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.bg_behavior') }}</label>
                                    <select x-model="appearanceModel().bg_behavior" @change="syncBlocksToLocale()" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs">
                                        <option value="normal">{{ __('admin.block_manager.bg_behavior_normal') }}</option>
                                        <option value="parallax">{{ __('admin.block_manager.bg_behavior_parallax') }}</option>
                                        <option value="fixed">{{ __('admin.block_manager.bg_behavior_fixed') }}</option>
                                    </select>
                                    <p class="text-[10px] text-gray-500 mt-1">{{ __('admin.block_manager.bg_behavior_hint') }}</p>
                                </div>
                            </div>

                            <div x-show="appearanceModel().bg_type === 'video'" class="space-y-3">
                                <div>
                                    <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.bg_video_url') }}</label>
                                    <div class="flex gap-2">
                                        <input type="text" x-model="appearanceModel().bg_video_url" @input="syncBlocksToLocale()" placeholder="https://... o archivo multimedia" class="flex-1 bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs font-mono">
                                        <button type="button" @click="openMediaPickerForObject(blocks[editingAppearanceIndex], 'bg_video_url')" class="px-3 py-2 bg-gray-800 text-[#00FF99] rounded-lg font-bold"><i class="fa-solid fa-film"></i></button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.video_poster') }}</label>
                                    <input type="text" x-model="appearanceModel().bg_video_poster" @input="syncBlocksToLocale()" placeholder="https://..." class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs font-mono">
                                </div>
                                <div>
                                    <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.overlay') }}</label>
                                    <input type="text" x-model="appearanceModel().bg_video_overlay" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_manager.overlay_placeholder') }}" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs font-mono">
                                </div>
                            </div>
                        </div>

                        <!-- TAB 3: DIVIDERS -->
                        <div x-show="appearanceTab === 'dividers'" class="space-y-4">
                            <!-- Divisor Superior -->
                            <div class="p-3 bg-[#1A1A1A] rounded-xl border border-gray-800 space-y-3">
                                <label class="block text-[#00FF99] text-[10px] uppercase font-bold">{{ __('admin.block_manager.divider_top') }}</label>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div>
                                        <select x-model="appearanceModel().divider_top.type" @change="syncBlocksToLocale()" class="w-full bg-[#121212] border border-gray-700 rounded px-2 py-1.5 text-white text-xs">
                                            <template x-for="d in dividerTypes" :key="d.value">
                                                <option :value="d.value" x-text="d.label"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <input type="text" x-model="appearanceModel().divider_top.color" @input="syncBlocksToLocale()" placeholder="#0E0E0E" class="w-full bg-[#121212] border border-gray-700 rounded px-2 py-1.5 text-white text-xs font-mono">
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input type="number" x-model.number="appearanceModel().divider_top.height" @input="syncBlocksToLocale()" placeholder="60" class="w-20 bg-[#121212] border border-gray-700 rounded px-2 py-1.5 text-white text-xs font-mono">
                                        <label class="flex items-center gap-1 text-gray-400 text-[11px] cursor-pointer">
                                            <x-admin.switch x-model="appearanceModel().divider_top.flip" @change="syncBlocksToLocale()" size="sm" />
                                            <span>{{ __('admin.block_manager.invert') }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Divisor Inferior -->
                            <div class="p-3 bg-[#1A1A1A] rounded-xl border border-gray-800 space-y-3">
                                <label class="block text-[#00FF99] text-[10px] uppercase font-bold">{{ __('admin.block_manager.divider_bottom') }}</label>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div>
                                        <select x-model="appearanceModel().divider_bottom.type" @change="syncBlocksToLocale()" class="w-full bg-[#121212] border border-gray-700 rounded px-2 py-1.5 text-white text-xs">
                                            <template x-for="d in dividerTypes" :key="d.value">
                                                <option :value="d.value" x-text="d.label"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <input type="text" x-model="appearanceModel().divider_bottom.color" @input="syncBlocksToLocale()" placeholder="#0E0E0E" class="w-full bg-[#121212] border border-gray-700 rounded px-2 py-1.5 text-white text-xs font-mono">
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input type="number" x-model.number="appearanceModel().divider_bottom.height" @input="syncBlocksToLocale()" placeholder="60" class="w-20 bg-[#121212] border border-gray-700 rounded px-2 py-1.5 text-white text-xs font-mono">
                                        <label class="flex items-center gap-1 text-gray-400 text-[11px] cursor-pointer">
                                            <x-admin.switch x-model="appearanceModel().divider_bottom.flip" @change="syncBlocksToLocale()" size="sm" />
                                            <span>{{ __('admin.block_manager.invert') }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 4: BORDERS -->
                        <div x-show="appearanceTab === 'borders'" class="space-y-4">
                            <div class="p-3 bg-[#1A1A1A] rounded-xl border border-gray-800 space-y-3">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <x-admin.switch x-model="appearanceModel().border_top.enabled" @change="syncBlocksToLocale()" size="sm" />
                                    <span class="text-[#00FF99] text-[10px] uppercase font-bold">{{ __('admin.block_manager.border_top') }}</span>
                                </label>
                                <div x-show="appearanceModel().border_top.enabled" class="grid grid-cols-2 gap-3">
                                    <input type="text" x-model="appearanceModel().border_top.color" @input="syncBlocksToLocale()" placeholder="#00FF99" class="bg-[#121212] border border-gray-700 rounded px-2 py-1.5 text-white text-xs font-mono">
                                    <input type="text" x-model="appearanceModel().border_top.width" @input="syncBlocksToLocale()" placeholder="2px" class="bg-[#121212] border border-gray-700 rounded px-2 py-1.5 text-white text-xs font-mono">
                                </div>
                            </div>

                            <div class="p-3 bg-[#1A1A1A] rounded-xl border border-gray-800 space-y-3">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <x-admin.switch x-model="appearanceModel().border_bottom.enabled" @change="syncBlocksToLocale()" size="sm" />
                                    <span class="text-[#00FF99] text-[10px] uppercase font-bold">{{ __('admin.block_manager.border_bottom') }}</span>
                                </label>
                                <div x-show="appearanceModel().border_bottom.enabled" class="grid grid-cols-2 gap-3">
                                    <input type="text" x-model="appearanceModel().border_bottom.color" @input="syncBlocksToLocale()" placeholder="#00FF99" class="bg-[#121212] border border-gray-700 rounded px-2 py-1.5 text-white text-xs font-mono">
                                    <input type="text" x-model="appearanceModel().border_bottom.width" @input="syncBlocksToLocale()" placeholder="2px" class="bg-[#121212] border border-gray-700 rounded px-2 py-1.5 text-white text-xs font-mono">
                                </div>
                            </div>
                        </div>

                        <!-- TAB 5: SCROLL ANIMATIONS -->
                        <div x-show="appearanceTab === 'scroll'" class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.effect') }}</label>
                                    <select x-model="appearanceModel().animation" @change="appearanceModel().scroll_magic.behavior = appearanceModel().animation; syncBlocksToLocale();" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs">
                                        <template x-for="anim in animationOptions" :key="anim.value">
                                            <option :value="anim.value" x-text="anim.label"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.effect_scrub') }}</label>
                                    <select x-model="appearanceModel().scroll_magic.scrub" @change="syncBlocksToLocale()" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs">
                                        <option value="none">{{ __('admin.block_manager.effect_standard') }}</option>
                                        <option value="rotate">{{ __('admin.block_manager.effect_rotate') }}</option>
                                        <option value="playback">{{ __('admin.block_manager.effect_play_visibility') }}</option>
                                        <option value="parallax">{{ __('admin.block_manager.effect_parallax') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.delay') }}</label>
                                    <input type="text" x-model="appearanceModel().animation_delay" @input="syncBlocksToLocale()" placeholder="0.2s" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs font-mono">
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Modal Footer -->
            <div class="pt-3 border-t border-gray-800 flex items-center justify-between shrink-0">
                <span class="text-[11px] text-gray-500">{{ __('admin.block_manager.changes_immediate') }}</span>
                <button type="button" @click="closeAppearanceModal()" class="btn-neon py-1.5 px-4 text-xs font-bold">
                    <i class="fa-solid fa-check mr-1.5"></i> Aplicar y cerrar
                </button>
            </div>
        </div>
    </div>

    <!-- Icon & Emoji Picker Modal -->
    <div x-show="showIconPickerModal"
         @click.self="showIconPickerModal = false"
         @keydown.escape.window="showIconPickerModal = false"
         x-transition
         x-cloak
         class="fixed inset-0 z-[120] flex items-center justify-center p-4 bg-black/40 backdrop-blur-md"
         style="display: none;">
        <div class="bg-[#141414] border border-[#00FF99]/40 max-w-2xl w-full rounded-2xl p-6 font-mono space-y-4 shadow-[0_0_50px_rgba(0,0,0,0.9)] max-h-[85vh] flex flex-col"
             @click.outside="showIconPickerModal = false">

            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-3 border-b border-gray-800 shrink-0">
                <div class="flex items-center gap-2">
                    <span class="text-xl text-[#00FF99]"><i class="fa-solid fa-icons"></i></span>
                    <div>
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <span>{{ __('admin.block_manager.icon_selector_title') }}</span>
                        </h3>
                        <p class="text-[11px] text-gray-400">{{ __('admin.block_manager.icon_selector_desc') }}</p>
                    </div>
                </div>
                <button type="button" @click="showIconPickerModal = false" class="text-gray-400 hover:text-white text-xl">&times;</button>
            </div>

            <!-- Search bar -->
            <div class="shrink-0">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 pointer-events-none">
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                    </span>
                    <input type="text"
                           x-model="iconPickerSearch"
                           placeholder="{{ __('admin.block_manager.icon_search_placeholder') }}"
                           class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-xl pl-9 pr-4 py-2 text-white text-xs placeholder-gray-500 focus:outline-none transition-colors">
                </div>
            </div>

            <!-- Category Filter Tabs -->
            <div class="flex flex-wrap items-center gap-1.5 shrink-0 border-b border-gray-800/80 pb-3">
                <button type="button" @click="iconPickerCategory = 'all'"
                        :class="iconPickerCategory === 'all' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white bg-[#1A1A1A] border border-gray-800'"
                        class="px-2.5 py-1 rounded-lg text-[11px] transition-all">{{ __('admin.block_manager.cat_all') }}</button>
                <button type="button" @click="iconPickerCategory = 'popular'"
                        :class="iconPickerCategory === 'popular' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white bg-[#1A1A1A] border border-gray-800'"
                        class="px-2.5 py-1 rounded-lg text-[11px] transition-all">{{ __('admin.block_manager.cat_popular') }}</button>
                <button type="button" @click="iconPickerCategory = 'business'"
                        :class="iconPickerCategory === 'business' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white bg-[#1A1A1A] border border-gray-800'"
                        class="px-2.5 py-1 rounded-lg text-[11px] transition-all">{{ __('admin.block_manager.cat_business') }}</button>
                <button type="button" @click="iconPickerCategory = 'tech'"
                        :class="iconPickerCategory === 'tech' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white bg-[#1A1A1A] border border-gray-800'"
                        class="px-2.5 py-1 rounded-lg text-[11px] transition-all">{{ __('admin.block_manager.cat_tech') }}</button>
                <button type="button" @click="iconPickerCategory = 'comm'"
                        :class="iconPickerCategory === 'comm' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white bg-[#1A1A1A] border border-gray-800'"
                        class="px-2.5 py-1 rounded-lg text-[11px] transition-all">{{ __('admin.block_manager.cat_comm') }}</button>
                <button type="button" @click="iconPickerCategory = 'commerce'"
                        :class="iconPickerCategory === 'commerce' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white bg-[#1A1A1A] border border-gray-800'"
                        class="px-2.5 py-1 rounded-lg text-[11px] transition-all">{{ __('admin.block_manager.cat_commerce') }}</button>
                <button type="button" @click="iconPickerCategory = 'emojis'"
                        :class="iconPickerCategory === 'emojis' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white bg-[#1A1A1A] border border-gray-800'"
                        class="px-2.5 py-1 rounded-lg text-[11px] transition-all">{{ __('admin.block_manager.cat_emojis') }}</button>
            </div>

            <!-- Scrollable Grid Area -->
            <div class="flex-1 overflow-y-auto pr-1 space-y-4">
                <!-- FontAwesome Icons Section -->
                <div x-show="iconPickerCategory !== 'emojis'">
                    <div class="text-[10px] text-gray-500 uppercase font-bold mb-2 flex items-center gap-1.5">
                        <i class="fa-solid fa-font-awesome text-[#00FF99]"></i>
                        <span>{{ __('admin.block_manager.icon_fa') }} (<span x-text="filteredPickerIcons().length"></span>)</span>
                    </div>
                    <div class="grid grid-cols-4 sm:grid-cols-6 gap-2">
                        <template x-for="item in filteredPickerIcons()" :key="item.icon">
                            <button type="button"
                                    @click="selectIcon(item.icon)"
                                    class="p-2.5 rounded-xl bg-[#1A1A1A] hover:bg-[#00FF99]/15 border border-gray-800 hover:border-[#00FF99] text-gray-300 hover:text-[#00FF99] flex flex-col items-center justify-center gap-1.5 transition-all group"
                                    :title="item.name + ' (' + item.icon + ')'">
                                <i :class="item.icon" class="text-xl group-hover:scale-110 transition-transform"></i>
                                <span class="text-[9px] text-gray-400 group-hover:text-white truncate w-full text-center" x-text="item.name"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Emojis Section -->
                <div x-show="iconPickerCategory === 'all' || iconPickerCategory === 'emojis'">
                    <div class="text-[10px] text-gray-500 uppercase font-bold mb-2 flex items-center gap-1.5">
                        <i class="fa-regular fa-face-smile text-amber-400"></i>
                        <span>{{ __('admin.block_manager.icon_emojis') }} (<span x-text="filteredPickerEmojis().length"></span>)</span>
                    </div>
                    <div class="grid grid-cols-5 sm:grid-cols-8 gap-2">
                        <template x-for="item in filteredPickerEmojis()" :key="item.emoji">
                            <button type="button"
                                    @click="selectIcon(item.emoji)"
                                    class="p-2 rounded-xl bg-[#1A1A1A] hover:bg-amber-400/15 border border-gray-800 hover:border-amber-400 text-gray-200 flex flex-col items-center justify-center gap-1 transition-all group"
                                    :title="item.name">
                                <span class="text-2xl group-hover:scale-110 transition-transform" x-text="item.emoji"></span>
                                <span class="text-[8px] text-gray-400 group-hover:text-white truncate w-full text-center" x-text="item.name"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Manual Value Bar & Footer -->
            <div class="pt-3 border-t border-gray-800 flex flex-wrap items-center justify-between gap-3 shrink-0">
                <div class="flex items-center gap-2 flex-1 max-w-sm">
                    <input type="text"
                           x-model="iconPickerManual"
                           placeholder="{{ __('admin.block_manager.icon_manual_placeholder') }}"
                           class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-1.5 text-white text-xs font-mono">
                    <button type="button"
                            @click="if (iconPickerManual.trim()) selectIcon(iconPickerManual.trim())"
                            class="px-3 py-1.5 bg-gray-800 hover:bg-[#00FF99] text-gray-300 hover:text-black rounded-lg text-xs font-bold transition-colors whitespace-nowrap">
                        Aplicar
                    </button>
                </div>
                <button type="button" @click="showIconPickerModal = false" class="px-4 py-1.5 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-lg text-xs">
                    Cerrar
                </button>
            </div>

        </div>
    </div>

</div>

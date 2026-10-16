const schemaDefaults = (schema = {}) => Object.fromEntries(Object.entries(schema).map(([key, field]) => {
    if (Object.hasOwn(field, 'default')) return [key, JSON.parse(JSON.stringify(field.default))];
    if (['list', 'array', 'repeater'].includes(field.type)) return [key, []];
    if (field.type === 'json') return [key, {}];
    if (field.type === 'boolean') return [key, false];
    if (field.type === 'select') {
        const options = field.options || {};
        return [key, (Array.isArray(options) ? options[0] : Object.keys(options)[0]) ?? ''];
    }
    return [key, ''];
}));

export const createBlockManager = (initialLocaleMap = {}, defaultLocale = 'es', initialAvailableTypes = [], availableLanguages = [], focusTarget = null) => ({
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
            isBlockAvailable(block) {
                return !!block && this.availableTypes.some(def => def.type === (block.type || 'text'));
            },

            schemaFields(block) {
                return Object.entries(this.typeDef(block.type).schema || {}).map(([key, field]) => ({ key, ...field }));
            },

            addSchemaItem(block, field) {
                if (!this.isBlockAvailable(block)) return;
                if (!Array.isArray(block[field.key])) block[field.key] = [];
                block[field.key].push(schemaDefaults(field.fields));
                this.syncBlocksToLocale();
            },

            updateJsonField(block, key, input) {
                try {
                    const value = JSON.parse(input.value);
                    if (!Array.isArray(value) && (typeof value !== 'object' || value === null)) throw new Error();
                    block[key] = value;
                    input.setCustomValidity('');
                    this.syncBlocksToLocale();
                } catch {
                    input.setCustomValidity('Introduce una lista u objeto JSON válido.');
                    input.reportValidity();
                }
            },

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
                                delete b.is_collapsed;
                            }
                        });
                    }
                }
                return JSON.stringify(clean);
            },

            insertExternalBlock(block, targetIndex = null) {
                if (!this.isBlockAvailable(block)) return;
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

                const normalize = (value) => String(value ?? '');
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
                if (!this.availableTypes.some(def => def.type === type)) return;
                const mode = forceMode || this.insertionMode;

                const newBlock = Object.assign({
                    id: 'sec_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6),
                    type: type,
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
                }, this.initialBlockPayload(type, mode));

                if (this.insertIndex !== null && this.insertIndex >= 0 && this.insertIndex <= this.blocks.length) {
                    this.blocks.splice(this.insertIndex, 0, newBlock);
                } else {
                    this.blocks.push(newBlock);
                }
                this.insertIndex = null;
                this.syncBlocksToLocale();
                this.showAddModal = false;
            },

            initialBlockPayload(type, mode) {
                const definition = this.typeDef(type);
                const payload = schemaDefaults(definition.schema);
                return mode === 'demo' ? Object.assign(payload, JSON.parse(JSON.stringify(definition.defaultPayload || {}))) : payload;
            },

            removeBlock(index) {
                if (!this.isBlockAvailable(this.blocks[index])) return;
                if (confirm('¿Estás seguro de que deseas eliminar este bloque de la página?')) {
                    this.blocks.splice(index, 1);
                    this.syncBlocksToLocale();
                }
            },

            cloneBlock(index) {
                if (!this.isBlockAvailable(this.blocks[index])) return;
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
                return ['title', 'headline', 'content', 'body', 'summary', 'subtitle'].some((k) => {
                    const v = payload[k];
                    return (typeof v === 'string' && v.trim() !== '') || (Array.isArray(v) && v.length > 0);
                });
            },

            clearSectionContent(block) {
                ['title', 'headline', 'content', 'body', 'summary', 'subtitle'].forEach((k) => { block[k] = ''; });
                block.items = [];
            },

            addColumnBlock(blockIndex, colIndex, type, forceMode = null) {
                if (!this.availableTypes.some(def => def.type === type)) return;
                const block = this.blocks[blockIndex];
                const cols = this.blockColumns(block);
                if (!block || !cols || !cols[colIndex]) return;

                const newSubBlock = Object.assign({
                    id: 'sub_' + Date.now() + '_' + Math.random().toString(36).substring(2, 6),
                    type: type,
                }, this.initialBlockPayload(type, forceMode || this.insertionMode));

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
                    return '<div class="p-4 text-center text-gray-500 font-mono text-xs italic border border-dashed border-gray-800 rounded-lg">Sin contenido para previsualizar</div>';
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
                    '<div class="p-2 my-2 bg-amber-950/40 border border-amber-500/40 text-amber-300 font-mono text-[10px] rounded">Script omitido en la vista previa</div>'
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
        });

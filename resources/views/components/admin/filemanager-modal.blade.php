<div x-data="{
    open: false,
    files: [],
    loading: false,
    activeTab: 'all',
    targetField: null,
    uploading: false,
    uploadError: '',
    selectedFile: null,
    savingMetadata: false,
    metadataSaved: false,
    searchQuery: '',
    isMaximized: false,

    init() {
        window.addEventListener('open-filemanager', (e) => {
            this.targetField = e.detail?.target || null;
            this.open = true;
            this.fetchFiles();
        });
    },

    fetchFiles() {
        this.loading = true;
        fetch('{{ route('admin.media.index') }}')
            .then(res => res.json())
            .then(data => {
                this.files = data.files || [];
                this.loading = false;
                if (this.selectedFile) {
                    const match = this.files.find(f => f.id === this.selectedFile.id || f.filename === this.selectedFile.filename);
                    if (match) this.selectedFile = match;
                }
            })
            .catch(() => { this.loading = false; });
    },

    uploadFile(event) {
        const file = event.target.files[0];
        if (!file) return;

        this.uploading = true;
        this.uploadError = '';
        const formData = new FormData();
        formData.append('file', file);
        formData.append('_token', '{{ csrf_token() }}');

        fetch('{{ route('admin.media.upload') }}', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            this.uploading = false;
            if (data.success) {
                this.fetchFiles();
                if (data.file) {
                    this.selectedFile = data.file;
                }
            } else {
                this.uploadError = data.message || 'Error al subir archivo';
            }
        })
        .catch(() => {
            this.uploading = false;
            this.uploadError = 'Fallo en la comunicación con el servidor.';
        });
    },

    clickFile(file) {
        this.selectedFile = Object.assign({}, file);
        this.metadataSaved = false;
    },

    selectFile(file) {
        const chosen = file || this.selectedFile;
        if (!chosen) return;

        if (this.targetField) {
            window.dispatchEvent(new CustomEvent('file-selected', {
                detail: {
                    target: this.targetField,
                    file: chosen
                }
            }));
        }
        this.open = false;
    },

    saveMetadata() {
        if (!this.selectedFile) return;

        this.savingMetadata = true;
        this.metadataSaved = false;

        fetch(@js(route('admin.media.update', ['id' => '__ID__'])).replace('__ID__', encodeURIComponent(this.selectedFile.id)), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                title: this.selectedFile.title,
                alt_text: this.selectedFile.alt_text,
                caption: this.selectedFile.caption
            })
        })
        .then(res => res.json())
        .then(data => {
            this.savingMetadata = false;
            if (data.success) {
                this.metadataSaved = true;
                const idx = this.files.findIndex(f => f.id === this.selectedFile.id || f.filename === this.selectedFile.filename);
                if (idx !== -1) {
                    this.files[idx].title = this.selectedFile.title;
                    this.files[idx].alt_text = this.selectedFile.alt_text;
                    this.files[idx].caption = this.selectedFile.caption;
                }
                setTimeout(() => { this.metadataSaved = false; }, 3000);
            }
        })
        .catch(() => {
            this.savingMetadata = false;
        });
    },

    deleteFile(file) {
        const target = file || this.selectedFile;
        if (!target) return;

        if (!confirm('¿Eliminar permanentemente el archivo \'' + (target.title || target.filename) + '\'? Esta acción no se puede deshacer.')) {
            return;
        }

        fetch(@js(route('admin.media.destroy', ['filename' => '__FILE__'])).replace('__FILE__', encodeURIComponent(target.filename)), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                this.files = this.files.filter(f => f.filename !== target.filename && f.id !== target.id);
                if (this.selectedFile && (this.selectedFile.id === target.id || this.selectedFile.filename === target.filename)) {
                    this.selectedFile = null;
                }
            }
        });
    },

    filteredFiles() {
        let list = this.files;
        if (this.activeTab === 'images') list = list.filter(f => f.is_image);
        if (this.activeTab === 'videos') list = list.filter(f => f.is_video);
        if (this.activeTab === 'docs') list = list.filter(f => f.is_doc);

        if (this.searchQuery && this.searchQuery.trim() !== '') {
            const q = this.searchQuery.toLowerCase();
            list = list.filter(f =>
                (f.title && f.title.toLowerCase().includes(q)) ||
                (f.alt_text && f.alt_text.toLowerCase().includes(q)) ||
                (f.filename && f.filename.toLowerCase().includes(q)) ||
                (f.original_name && f.original_name.toLowerCase().includes(q))
            );
        }
        return list;
    }
}"
     x-show="open"
     x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-[200] flex items-center justify-center p-3 sm:p-6 bg-black/40 backdrop-blur-md"
     style="display: none;"
     @click.self="open = false"
     @keydown.escape.window="open = false">

    <div class="bg-[#141414] border border-[#00FF99]/30 rounded-2xl shadow-[0_0_50px_rgba(0,0,0,0.9)] flex flex-col overflow-hidden transition-all duration-200"
         :class="isMaximized ? 'w-[98vw] h-[96vh] max-w-none' : 'w-full max-w-6xl h-[90vh]'"
         @click.outside="open = false">

        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-gray-800 bg-[#1A1A1A] flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <span class="text-xl font-bold text-[#00FF99]">&gt;_</span>
                <div>
                    <h3 class="text-sm font-bold text-white font-mono uppercase tracking-wider">{{ __('admin.filemanager.title') }}</h3>
                    <p class="text-[10px] text-gray-400 font-mono">{{ __('admin.filemanager.subtitle') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="isMaximized = !isMaximized" class="p-1.5 text-gray-400 hover:text-[#00FF99] hover:bg-white/5 rounded-lg transition-colors" :title="isMaximized ? 'Restaurar tamaño' : 'Maximizar'">
                    <i class="fa-solid text-sm" :class="isMaximized ? 'fa-compress' : 'fa-expand'"></i>
                </button>
                <button type="button" @click="open = false" class="text-gray-400 hover:text-white text-2xl font-mono leading-none">&times;</button>
            </div>
        </div>

        <!-- Toolbar & Tabs -->
        <div class="px-6 py-3 border-b border-gray-800 bg-[#171717] font-mono text-xs flex flex-wrap items-center justify-between gap-3 shrink-0">
            <div class="flex items-center gap-1.5">
                <button @click="activeTab = 'all'" :class="activeTab === 'all' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white'" class="px-3 py-1.5 rounded-lg transition-all">{{ __('admin.filemanager.tab_all') }}</button>
                <button @click="activeTab = 'images'" :class="activeTab === 'images' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white'" class="px-3 py-1.5 rounded-lg transition-all">{{ __('admin.filemanager.tab_images') }}</button>
                <button @click="activeTab = 'videos'" :class="activeTab === 'videos' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white'" class="px-3 py-1.5 rounded-lg transition-all">{{ __('admin.filemanager.tab_videos') }}</button>
                <button @click="activeTab = 'docs'" :class="activeTab === 'docs' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white'" class="px-3 py-1.5 rounded-lg transition-all">{{ __('admin.filemanager.tab_docs') }}</button>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" x-data="{ aiAvailable: typeof window.CmsAssistant !== 'undefined' }"
                        @cms-assistant-ready.window="aiAvailable = true" x-show="aiAvailable && targetField"
                        @click="open = false; window.CmsAssistant.open({ mode: 'image', mediaTarget: targetField })"
                        class="px-3 py-1.5 rounded-lg border border-purple-500/40 text-purple-300 hover:text-white text-xs font-bold">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('admin.ai.media_generate') }}
                </button>
                <!-- Search input -->
                <input type="text"
                       x-model="searchQuery"
                       placeholder="{{ __('admin.filemanager.search_placeholder') }}"
                       class="bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded px-2.5 py-1 text-xs text-white placeholder-gray-500 w-44">

                <!-- Upload button -->
                <label class="cursor-pointer px-3.5 py-1.5 border border-[#00FF99] text-[#00FF99] hover:bg-[#00FF99] hover:text-black font-bold rounded transition-all flex items-center gap-1.5 text-xs">
                    <i x-show="uploading" class="fa-solid fa-spinner fa-spin"></i>
                    <span x-text="uploading ? 'Subiendo...' : '+ Subir Archivo'"></span>
                    <input type="file" @change="uploadFile($event)" class="hidden">
                </label>
                <button @click="fetchFiles()" class="text-gray-400 hover:text-[#00FF99] px-2 py-1" title="{{ __('admin.filemanager.reload') }}"><i class="fa-solid fa-rotate-right"></i></button>
            </div>
        </div>

        <!-- Upload error banner -->
        <div x-show="uploadError" x-cloak class="p-2.5 bg-red-950/60 border-b border-red-800 text-red-300 font-mono text-xs flex items-center justify-between">
            <span class="flex items-center gap-1.5"><i class="fa-solid fa-triangle-exclamation"></i> <span x-text="uploadError"></span></span>
            <button @click="uploadError = ''" class="text-gray-400 hover:text-white">&times;</button>
        </div>

        <!-- Main Body: Split Grid & Inspector -->
        <div class="flex-1 flex overflow-hidden admin-fm-body bg-[#121212]">

            <!-- Left: Files Grid -->
            <div class="flex-1 p-5 overflow-y-auto">
                <div x-show="loading" class="text-center py-16 text-[#00FF99] font-mono text-xs">
                    <span class="animate-pulse">&gt; Cargando biblioteca multimedia...</span>
                </div>

                <div x-show="!loading && filteredFiles().length === 0" class="text-center py-20 text-gray-500 font-mono text-xs space-y-2">
                    <div class="text-3xl text-gray-600"><i class="fa-solid fa-folder-open"></i></div>
                    <p>{{ __('admin.filemanager.empty') }}</p>
                </div>

                <div x-show="!loading && filteredFiles().length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3.5">
                    <template x-for="file in filteredFiles()" :key="file.id">
                        <div @click="clickFile(file)"
                             @dblclick="selectFile(file)"
                             :class="selectedFile && (selectedFile.id === file.id || selectedFile.filename === file.filename)
                                ? 'border-[#00FF99] shadow-[0_0_15px_rgba(0,255,153,0.25)] bg-[#1F1F1F]'
                                : 'border-gray-800 hover:border-gray-600 bg-[#171717]'"
                             class="group relative border rounded-xl p-2 cursor-pointer transition-all flex flex-col justify-between">

                            <!-- Thumbnail Container with Error Fallback -->
                            <div class="h-28 w-full bg-[#0E0E0E] rounded-lg overflow-hidden flex items-center justify-center mb-2 relative">
                                <template x-if="file.is_image">
                                    <div class="w-full h-full flex items-center justify-center">
                                        <img :src="file.url"
                                             :alt="file.alt_text || file.title"
                                             x-on:error="$el.style.display='none'; $el.nextElementSibling.style.display='flex'"
                                             class="h-full w-full object-cover">
                                        <div style="display:none" class="h-full w-full items-center justify-center bg-[#181818] text-gray-500 text-2xl">
                                            <i class="fa-solid fa-image"></i>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="file.is_video">
                                    <span class="text-3xl text-purple-500"><i class="fa-solid fa-video"></i></span>
                                </template>
                                <template x-if="file.is_doc">
                                    <span class="text-3xl text-blue-500"><i class="fa-solid fa-file-lines"></i></span>
                                </template>

                                <!-- Dimensions Badge -->
                                <template x-if="file.width && file.height">
                                    <span class="absolute bottom-1 right-1 px-1.5 py-0.5 rounded bg-black/80 text-gray-300 text-[9px]" x-text="file.width + '×' + file.height"></span>
                                </template>
                            </div>

                            <!-- File Title / Name -->
                            <div class="font-mono text-white text-[11px] truncate font-bold" x-text="file.title || file.original_name"></div>

                            <!-- Alt text indicator -->
                            <div class="font-mono text-gray-400 text-[10px] flex items-center justify-between mt-1">
                                <span class="truncate text-[10px] text-gray-500" x-text="file.size_formatted"></span>
                                <span x-show="file.alt_text" class="text-[9px] text-[#00FF99] font-bold">ALT <i class="fa-solid fa-check"></i></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Right: Metadata & SEO Inspector Drawer -->
            <div x-show="selectedFile"
                 x-cloak
                 class="w-80 border-l border-gray-800 bg-[#161616] font-mono flex flex-col justify-between shrink-0 p-5 overflow-y-auto text-xs">

                <div class="space-y-4">
                    <!-- Drawer Header -->
                    <div class="flex items-center justify-between border-b border-gray-800 pb-3">
                        <span class="text-[#00FF99] font-bold text-xs uppercase tracking-wider">{{ __('admin.filemanager.detail_seo') }}</span>
                        <button @click="selectedFile = null" class="text-gray-400 hover:text-white">&times;</button>
                    </div>

                    <!-- Image Preview Large -->
                    <div class="h-36 w-full bg-black border border-gray-800 rounded-lg overflow-hidden flex items-center justify-center">
                        <template x-if="selectedFile?.is_image">
                            <img :src="selectedFile?.url" :alt="selectedFile?.alt_text" class="h-full w-full object-contain">
                        </template>
                        <template x-if="selectedFile?.is_video">
                            <span class="text-4xl text-purple-500"><i class="fa-solid fa-video"></i></span>
                        </template>
                        <template x-if="selectedFile?.is_doc">
                            <span class="text-4xl text-blue-500"><i class="fa-solid fa-file-lines"></i></span>
                        </template>
                    </div>

                    <!-- Technical Specs -->
                    <div class="text-[10px] text-gray-400 bg-[#1A1A1A] border border-gray-800 space-y-1 p-2.5 rounded-lg">
                        <div class="truncate text-white font-bold" x-text="selectedFile?.original_name"></div>
                        <div class="flex justify-between">
                            <span>{{ __('admin.filemanager.size') }}</span>
                            <span class="text-gray-300" x-text="selectedFile?.size_formatted"></span>
                        </div>
                        <template x-if="selectedFile?.width && selectedFile?.height">
                            <div class="flex justify-between">
                                <span>{{ __('admin.filemanager.dimensions') }}</span>
                                <span class="text-[#00FF99]" x-text="selectedFile?.width + ' × ' + selectedFile?.height + ' px'"></span>
                            </div>
                        </template>
                        <div class="flex justify-between">
                            <span>{{ __('admin.filemanager.type') }}</span>
                            <span class="text-gray-300 uppercase" x-text="selectedFile?.mime_type"></span>
                        </div>
                    </div>

                    <!-- SEO Fields Form -->
                    <template x-if="selectedFile">
                    <div class="space-y-3 pt-2">
                        <div>
                            <label class="block text-[10px] text-gray-400 uppercase font-bold mb-1">
                                {{ __('admin.filemanager.title_label') }}
                            </label>
                            <input type="text"
                                   x-model="selectedFile.title"
                                   placeholder="{{ __('admin.filemanager.title_placeholder') }}"
                                   class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] text-white rounded px-2.5 py-1.5 text-xs">
                        </div>

                        <div>
                            <label class="block text-[10px] text-gray-400 uppercase font-bold mb-1">
                                {{ __('admin.filemanager.alt_label') }} *
                            </label>
                            <input type="text"
                                   x-model="selectedFile.alt_text"
                                   placeholder="{{ __('admin.filemanager.alt_placeholder') }}"
                                   class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] text-[#00FF99] rounded px-2.5 py-1.5 text-xs">
                            <p class="text-[9px] text-gray-500 mt-0.5">{{ __('admin.filemanager.alt_hint') }}</p>
                        </div>

                        <div>
                            <label class="block text-[10px] text-gray-400 uppercase font-bold mb-1">
                                {{ __('admin.filemanager.caption_label') }}
                            </label>
                            <textarea x-model="selectedFile.caption"
                                      rows="2"
                                      placeholder="{{ __('admin.filemanager.caption_placeholder') }}"
                                      class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] text-gray-300 rounded px-2.5 py-1.5 text-xs"></textarea>
                        </div>

                        <!-- Save Metadata Button -->
                        <div class="pt-1">
                            <button type="button"
                                    @click="saveMetadata()"
                                    :disabled="savingMetadata"
                                    class="w-full py-1.5 bg-gray-800 hover:bg-gray-700 border border-gray-600 text-white rounded font-bold text-xs flex items-center justify-center gap-1.5 transition-colors">
                                <i x-show="savingMetadata" class="fa-solid fa-spinner fa-spin"></i>
                                <span class="flex items-center gap-1.5">
                                    <template x-if="!savingMetadata && !metadataSaved">
                                        <i class="fa-solid fa-floppy-disk"></i>
                                    </template>
                                    <template x-if="metadataSaved">
                                        <i class="fa-solid fa-circle-check text-[#00FF99]"></i>
                                    </template>
                                    <span x-text="savingMetadata ? 'Guardando...' : (metadataSaved ? '¡Metadatos Guardados!' : 'Guardar Metadatos SEO')"></span>
                                </span>
                            </button>
                        </div>
                    </div>
                    </template>
                </div>

                <!-- Bottom Action Controls -->
                <div class="pt-4 border-t border-gray-800 space-y-2">
                    <button type="button"
                            @click="selectFile(selectedFile)"
                            class="btn-neon font-bold w-full py-2.5 text-xs flex items-center justify-center gap-2">
                        <i class="fa-solid fa-check"></i>
                        <span>{{ __('admin.filemanager.select_for_block') }} &crarr;</span>
                    </button>

                    <button type="button"
                            @click="deleteFile(selectedFile)"
                            class="w-full py-1.5 bg-red-950/30 hover:bg-red-900/60 border border-red-800/60 text-red-300 rounded text-[10px] transition-colors">
                        Eliminar archivo permanentemente
                    </button>
                </div>
            </div>

            <!-- Empty Inspector placeholder when nothing selected -->
            <div x-show="!selectedFile"
                 class="hidden lg:flex w-72 border-l border-gray-800 bg-[#141414] text-gray-500 font-mono p-6 flex-col items-center justify-center text-center text-xs">
                <span class="text-4xl mb-3 opacity-40 text-gray-600"><i class="fa-solid fa-image"></i></span>
                <p class="font-bold text-gray-400">{{ __('admin.filemanager.no_file') }}</p>
                <p class="text-[10px] mt-1 text-gray-600">{{ __('admin.filemanager.no_file_hint') }}</p>
            </div>
        </div>
    </div>
</div>

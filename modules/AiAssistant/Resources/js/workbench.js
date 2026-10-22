export function createAiWorkbench(config = {}) {
    return {
        currentTab: 'page',
        generating: false,
        creating: false,
        testing: false,
        progressNote: '',
        toastMessage: null,
        toastType: 'success',

        init() {
            this.resumePending();
        },

        // Resume a queued generation started before navigating away.
        resumePending() {
            const params = new URLSearchParams(window.location.search);
            const taskId = params.get('task') || localStorage.getItem('ai.workbench.task');

            if (taskId) {
                this.resumeTask(taskId);
            }
        },

        async resumeTask(taskId) {
            this.generating = true;
            this.progressNote = config.resuming;

            try {
                await this.pollGeneration(taskId, (state) => this.applyTaskResult(state.action, state.data));
            } catch (e) {
                this.toastType = 'error';
                this.toastMessage = e.message;
            } finally {
                this.generating = false;
                this.progressNote = '';
                localStorage.removeItem('ai.workbench.task');
            }
        },

        applyTaskResult(action, data) {
            if (action === 'orchestrate_multimodal') {
                this.currentTab = 'orchestrator';
                this.orchestratorResult = data;
            } else if (['image', 'video'].includes(action)) {
                this.currentTab = 'media';
                this.mediaForm.mode = action;
                this.mediaResult = data;
            } else if (action === 'page_element') {
                this.currentTab = 'page';
                this.pageResult = data;
            } else {
                this.currentTab = 'refine';
                this.refineForm.action = action;
                this.refineResult = data;
            }
        },

        pageForm: {
            type: config.initialType || 'page',
            prompt: ''
        },
        pageResult: null,
        pageExamples: config.examples || {},
        pagePlaceholders: config.placeholders || {},

        briefPlaceholder() {
            return this.pagePlaceholders[this.pageForm.type]
                || (this.pageExamples[this.pageForm.type] || [])[0]
                || config.briefPlaceholder;
        },

        briefExamples() {
            return this.pageExamples[this.pageForm.type] || [];
        },

        refineForm: {
            action: 'humanize',
            content: '',
            prompt: ''
        },
        refineResult: null,

        orchestratorForm: {
            entity_type: 'post',
            topic: '',
            generate_image: true,
            generate_video: false
        },
        orchestratorResult: null,

        mediaForm: {
            mode: 'image',
            title: '',
            prompt: '',
            aspect_ratio: '16:9',
            duration: 5
        },
        mediaResult: null,

        async testActiveConnection() {
            this.testing = true;
            try {
                const res = await fetch(config.connectionUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken
                    },
                    body: JSON.stringify({ provider: config.activeProvider })
                });
                const data = await res.json();
                this.toastType = data.success ? 'success' : 'error';
                this.toastMessage = `[${data.provider.toUpperCase()}] ${data.message} (${data.latency_ms}ms)`;
            } catch (e) {
                this.toastType = 'error';
                this.toastMessage = 'Error al probar conexión: ' + e.message;
            } finally {
                this.testing = false;
            }
        },

        // Queue the action and poll until the background worker finishes.
        async requestGeneration(payload) {
            const start = await fetch(config.generateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    ...payload,
                    return_url: window.location.href
                })
            });
            const started = await start.json();
            if (!started.success) {
                throw new Error(started.question || started.message || started.error || 'No se pudo iniciar la generación.');
            }

            // Persist the task so the result can be resumed after navigating away.
            localStorage.setItem('ai.workbench.task', started.task_id);

            try {
                return await this.pollGeneration(started.task_id);
            } finally {
                localStorage.removeItem('ai.workbench.task');
            }
        },

        async pollGeneration(taskId, completed = (state) => state.data) {
            const statusUrl = config.statusUrl.replace('__TASK__', encodeURIComponent(taskId));
            const deadline = Date.now() + 30 * 60 * 1000;
            let elapsed = 0;

            while (Date.now() < deadline) {
                await new Promise((resolve) => setTimeout(resolve, 2000));
                elapsed += 2;

                const res = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                const state = await res.json();

                if (state.status === 'needs_input') {
                    throw new Error(state.question || state.message || state.data?.question || config.clarification);
                }
                if (!state.success) {
                    throw new Error(state.error || 'Tarea de generación no encontrada.');
                }
                if (state.status === 'completed') {
                    return completed(state);
                }
                if (['uncertain', 'approval_required'].includes(state.status)) {
                    const message = state.error || (state.status === 'uncertain'
                        ? 'El resultado es incierto. Revisa la operación antes de volver a ejecutarla.'
                        : 'Se requiere aprobación humana del cambio concreto.');
                    throw new Error(message);
                }
                if (state.status === 'failed') {
                    throw new Error(state.error || 'La generación falló.');
                }

                this.progressNote = `Procesando en segundo plano… ${elapsed}s`;
            }

            throw new Error('La generación superó el tiempo máximo de espera.');
        },

        async generatePageElement() {
            this.generating = true;
            this.progressNote = 'Enviando a la cola…';
            this.pageResult = null;
            try {
                this.pageResult = await this.requestGeneration({
                    action: 'page_element',
                    element_type: this.pageForm.type,
                    prompt: this.pageForm.prompt,
                    context_source: 'prompt'
                });
            } catch (e) {
                this.toastType = 'error';
                this.toastMessage = e.message;
            } finally {
                this.generating = false;
                this.progressNote = '';
            }
        },

        async createDraft() {
            if (!this.pageResult) return;
            if (!this.pageResult.resolved_context) {
                this.toastType = 'error';
                this.toastMessage = config.clarification;
                return;
            }
            this.creating = true;
            try {
                const res = await fetch(config.createUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken
                    },
                    body: JSON.stringify({
                        type: this.pageResult.type,
                        fields: this.pageResult.fields || {},
                        sections: this.pageResult.sections || [],
                        locale: this.pageResult.resolved_context.locale,
                        market: this.pageResult.resolved_context.market
                    })
                });
                const response = await res.json();
                if (response.success) {
                    this.toastType = 'success';
                    this.toastMessage = response.message + ' ' + response.edit_url;
                    window.location.href = response.edit_url;
                } else {
                    this.toastType = 'error';
                    this.toastMessage = response.error || 'No se pudo crear el borrador.';
                }
            } catch (e) {
                this.toastType = 'error';
                this.toastMessage = e.message;
            } finally {
                this.creating = false;
            }
        },

        async executeRefine() {
            this.generating = true;
            this.progressNote = 'Enviando a la cola…';
            this.refineResult = null;
            try {
                this.refineResult = await this.requestGeneration({
                    action: this.refineForm.action,
                    content: this.refineForm.content,
                    prompt: this.refineForm.prompt,
                    context_source: 'prompt'
                });
            } catch (e) {
                this.toastType = 'error';
                this.toastMessage = e.message;
            } finally {
                this.generating = false;
                this.progressNote = '';
            }
        },

        async generateMedia() {
            this.generating = true;
            this.progressNote = this.mediaForm.mode === 'image' ? 'Generando imagen fotorrealista…' : 'Sintetizando vídeo cinemático…';
            this.mediaResult = null;
            try {
                this.mediaResult = await this.requestGeneration({
                    action: this.mediaForm.mode === 'image' ? 'image' : 'video',
                    prompt: this.mediaForm.prompt,
                    aspect_ratio: this.mediaForm.aspect_ratio,
                    duration: parseInt(this.mediaForm.duration, 10) || 5,
                    title: this.mediaForm.title || (this.mediaForm.mode === 'image' ? 'Imagen IA' : 'Vídeo IA')
                });
                this.toastType = 'success';
                this.toastMessage = 'Medio generado y guardado en la mediateca.';
            } catch (e) {
                this.toastType = 'error';
                this.toastMessage = e.message;
            } finally {
                this.generating = false;
                this.progressNote = '';
            }
        },

        async orchestrateEntity() {
            this.generating = true;
            this.progressNote = 'Investigando fuentes, redactando contenido anti-IA y orquestando bloques…';
            this.orchestratorResult = null;
            try {
                this.orchestratorResult = await this.requestGeneration({
                    action: 'orchestrate_multimodal',
                    entity_type: this.orchestratorForm.entity_type,
                    topic: this.orchestratorForm.topic,
                    context_source: 'prompt',
                    generate_image: this.orchestratorForm.generate_image,
                    generate_video: this.orchestratorForm.generate_video
                });
                this.toastType = 'success';
                this.toastMessage = 'Contenido orquestado exitosamente.';
            } catch (e) {
                this.toastType = 'error';
                this.toastMessage = e.message;
            } finally {
                this.generating = false;
                this.progressNote = '';
            }
        },

        async saveOrchestratedEntity() {
            if (!this.orchestratorResult) return;
            if (!this.orchestratorResult.resolved_context) {
                this.toastType = 'error';
                this.toastMessage = config.clarification;
                return;
            }
            this.creating = true;
            try {
                const res = await fetch(config.createUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken
                    },
                    body: JSON.stringify({
                        type: this.orchestratorResult.entity_type || this.orchestratorForm.entity_type,
                        fields: {
                            title: this.orchestratorResult.title,
                            summary: this.orchestratorResult.summary,
                            content: this.orchestratorResult.content,
                            featured_image: this.orchestratorResult.featured_image_url,
                            slug: this.orchestratorResult.slug,
                            meta_title: this.orchestratorResult.meta_title,
                            meta_description: this.orchestratorResult.meta_description
                        },
                        sections: this.orchestratorResult.sections || [],
                        locale: this.orchestratorResult.resolved_context.locale,
                        market: this.orchestratorResult.resolved_context.market
                    })
                });
                const response = await res.json();
                if (response.success) {
                    this.toastType = 'success';
                    this.toastMessage = response.message;
                    window.location.href = response.edit_url;
                } else {
                    this.toastType = 'error';
                    this.toastMessage = response.error || 'No se pudo crear la entidad.';
                }
            } catch (e) {
                this.toastType = 'error';
                this.toastMessage = e.message;
            } finally {
                this.creating = false;
            }
        }
    };
}

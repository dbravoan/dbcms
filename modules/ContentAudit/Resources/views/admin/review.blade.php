@extends('admin.layout')

@section('title', __('admin.page_titles.content_audit_review') . ' ' . ($entityTitle !== '' ? $entityTitle : __('admin.page_titles.content_audit')))

@section('content')
<div class="admin-page space-y-6 font-mono">
    <!-- Header Breadcrumb & Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-[color:var(--admin-surface)] p-5 rounded-xl border border-[color:var(--admin-border)]">
        <div class="space-y-1">
            <div class="flex items-center gap-2 text-xs text-[color:var(--admin-muted)]">
                <a href="{{ route('admin.audit.index') }}" class="hover:text-[color:var(--admin-accent)]">&larr; {{ __('admin.content_audit.back_to_queue') }}</a>
                <span>/</span>
                <span class="text-[color:var(--admin-muted)] uppercase tracking-wider">{{ $type }}</span>
                <span>/</span>
                <span class="text-[color:var(--admin-text)] font-bold">#{{ $id }}</span>
            </div>
            <h1 class="admin-page-title">
                <span>{{ $entityTitle !== '' ? $entityTitle : 'Contenido sin título' }}</span>
                @php
                    $status = $audit?->status ?? ($entityIsPublished ? 'published' : 'draft');
                    $statusEnum = \Modules\ContentAudit\Enums\AuditStatus::tryFrom($status) ?? \Modules\ContentAudit\Enums\AuditStatus::DRAFT;
                @endphp
                <span class="px-2.5 py-0.5 rounded text-xs font-bold inline-flex items-center gap-1.5 {{ $statusEnum->badgeClass() }}">
                    <i class="fa-solid {{ $statusEnum->icon() }} text-[10px]"></i>
                    {{ $statusEnum->label() }}
                </span>
            </h1>
        </div>

        <div class="flex items-center gap-2">
            @if($editorUrl !== null)
                <x-admin.button :href="$editorUrl" variant="neutral" size="sm" icon="fa-solid fa-arrow-up-right-from-square" target="_blank" rel="noopener">{{ __('admin.content_audit.open_editor') }}</x-admin.button>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs rounded-xl flex items-center gap-2">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 bg-red-500/10 border border-red-500/30 text-red-400 text-xs rounded-xl space-y-1">
            <div class="font-bold flex items-center gap-1.5">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span>{{ __('admin.content_audit.validation_errors') }}</span>
            </div>
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Column: Diff Viewer & Content Preview -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Diff Visualizer -->
            <div class="bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl p-5 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[color:var(--admin-border)]">
                    <h2 class="text-sm font-bold text-[color:var(--admin-text)] uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-code-compare text-[color:var(--admin-accent)]"></i>
                        <span>{{ __('admin.content_audit.diff_title') }}</span>
                    </h2>
                    <span class="text-xs text-[color:var(--admin-muted)]">{{ __('admin.content_audit.current_vs_confirmed') }}</span>
                </div>

                @include('content_audit::admin.partials.diff-viewer', ['diffResult' => $diffResult])
            </div>

            <div class="bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl p-5 space-y-3">
                <h2 class="text-sm font-bold text-[color:var(--admin-text)]">Datos de la revisión que vas a aprobar</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    @foreach($reviewSummary as $row)
                        <div><dt class="text-[color:var(--admin-muted)]">{{ $row['label'] }}</dt><dd class="text-[color:var(--admin-text)] break-words">{{ $row['value'] }}</dd></div>
                    @endforeach
                </dl>
            </div>

            <!-- Content Details Card -->
            <div class="bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl p-5 space-y-4">
                <h2 class="text-sm font-bold text-[color:var(--admin-text)] uppercase tracking-wider pb-3 border-b border-[color:var(--admin-border)] flex items-center gap-2">
                    <i class="fa-solid fa-layer-group text-[color:var(--admin-accent)]"></i>
                    <span>{{ __('admin.content_audit.translations_title') }}</span>
                </h2>

                <div class="space-y-4">
                    @if($entityTranslations->isNotEmpty())
                        @foreach($entityTranslations as $t)
                            <div class="bg-[color:var(--admin-surface)] p-4 rounded-lg border border-[color:var(--admin-border)] space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-[color:var(--admin-accent)]/15 text-[color:var(--admin-accent)] border border-[color:var(--admin-accent)]/30 uppercase">
                                        Idioma [{{ $t['locale'] }}]
                                    </span>
                                    <span class="text-xs text-[color:var(--admin-muted)] font-mono">slug: /{{ $t['slug'] }}</span>
                                </div>
                                <h3 class="text-base font-bold text-[color:var(--admin-text)]">{{ $t['title'] !== '' ? $t['title'] : '(Sin título)' }}</h3>
                                @if($t['summary'] !== '')
                                    <p class="text-xs text-[color:var(--admin-muted)] italic">{{ $t['summary'] }}</p>
                                @endif
                                @if($t['content'] !== '')
                                    <div class="mt-3 p-3 bg-[color:var(--admin-surface)]/50 border border-[color:var(--admin-border)]/80 rounded text-xs text-[color:var(--admin-text)] max-h-48 overflow-y-auto leading-relaxed whitespace-pre-wrap">
                                        {{ strip_tags($t['content']) }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    @else
                        <div class="p-4 bg-[color:var(--admin-surface-raised)]/40 rounded text-[color:var(--admin-muted)] text-xs text-center">
                            No se registran traducciones adicionales.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar Column: Action Decision & Revisions Timeline -->
        <div class="space-y-6">
            <!-- Editorial Quality Decision Box -->
            <div class="bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl p-5 space-y-4 shadow-xl">
                <div class="pb-3 border-b border-[color:var(--admin-border)]">
                    <h2 class="text-sm font-bold text-[color:var(--admin-text)] uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-gavel text-[color:var(--admin-accent)]"></i>
                        <span>{{ __('admin.content_audit.decision_title') }}</span>
                    </h2>
                    <p class="text-[11px] text-[color:var(--admin-muted)] mt-1">{{ __('admin.content_audit.decision_desc') }}</p>
                </div>

                <!-- Approve Form -->
                <form method="POST" action="{{ route('admin.audit.approve', ['type' => $type, 'id' => $id]) }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="review_token" value="{{ $reviewToken }}">
                    <x-admin.textarea name="notes" rows="2" :label="__('admin.content_audit.approve_notes')" :placeholder="__('admin.content_audit.approve_notes_placeholder')" maxlength="2000" />
                    <x-admin.button type="submit" variant="success" class="w-full" icon="fa-solid fa-circle-check">Aprobar estos cambios</x-admin.button>
                </form>

                <div class="relative flex py-2 items-center">
                    <div class="flex-grow border-t border-[color:var(--admin-border)]"></div>
                    <span class="flex-shrink mx-3 text-[color:var(--admin-muted)] text-[10px] uppercase font-bold">{{ __('admin.content_audit.or_reject') }}</span>
                    <div class="flex-grow border-t border-[color:var(--admin-border)]"></div>
                </div>

                <!-- Reject Form -->
                <form method="POST" action="{{ route('admin.audit.reject', ['type' => $type, 'id' => $id]) }}" class="space-y-3">
                    @csrf
                    <input type="hidden" name="review_token" value="{{ $reviewToken }}">
                    <x-admin.textarea name="rejection_reason" rows="3" :label="__('admin.content_audit.reject_reason')" :required="$requireRejectionReason" :placeholder="__('admin.content_audit.reject_reason_placeholder')" minlength="5" maxlength="2000" />
                    <x-admin.button type="submit" variant="danger" class="w-full" icon="fa-solid fa-circle-xmark">{{ __('admin.content_audit.reject_return') }}</x-admin.button>
                </form>
            </div>

            <!-- Revisions Timeline Card -->
            <div class="bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl p-5 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[color:var(--admin-border)]">
                    <h3 class="text-xs font-bold text-[color:var(--admin-text)] uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-clock-rotate-left text-[color:var(--admin-accent)]"></i>
                        <span>{{ __('admin.content_audit.revisions_title') }}</span>
                    </h3>
                    <span class="text-[10px] text-[color:var(--admin-muted)]">{{ __('admin.content_audit.versions', ['count' => $revisions->count()]) }}</span>
                </div>

                <div class="space-y-3">
                    @forelse($revisions as $rev)
                        @php
                            $actionEnum = $rev->getActionEnum();
                        @endphp
                        <div class="p-3 bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)]/80 rounded-lg space-y-1.5 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-[color:var(--admin-surface)]/60 text-[color:var(--admin-text)] border border-[color:var(--admin-border)]">
                                    v{{ $rev->version }} &bull; {{ $actionEnum->label() }}
                                </span>
                                <span class="text-[10px] text-[color:var(--admin-muted)]">
                                    {{ $rev->created_at->format('d/m H:i') }}
                                </span>
                            </div>

                            <p class="text-[11px] text-[color:var(--admin-muted)]">
                                Por: <span class="text-[color:var(--admin-text)] font-semibold">{{ $rev->user?->name ?? __('admin.content_audit.system') }}</span>
                            </p>

                            @if(!empty($rev->notes))
                                <p class="text-[10px] text-[color:var(--admin-muted)] italic bg-[color:var(--admin-surface)]/30 p-1.5 rounded">
                                    "{{ $rev->notes }}"
                                </p>
                            @endif

                            @if($rev->version < $revisions->first()->version)
                                <form method="POST" action="{{ route('admin.audit.rollback', $rev->id) }}" onsubmit="return confirm('¿Revertir este contenido a la versión histórica v{{ $rev->version }}?')" class="pt-1">
                                    @csrf
                                    <input type="hidden" name="review_token" value="{{ $rollbackTokens[$rev->getKey()] }}">
                                    <button type="submit" class="text-[10px] text-amber-400 hover:underline flex items-center gap-1">
                                        <i class="fa-solid fa-rotate-left text-[9px]"></i>
                                        <span>{{ __('admin.content_audit.restore_version') }}</span>
                                    </button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <div class="p-4 text-center text-[color:var(--admin-muted)] text-xs">
                            {{ __('admin.content_audit.no_revisions') }}
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@extends('admin.layout')
@section('title', $campaign->translations->first()?->title ?? 'CreativeStudio')
@section('content')
@php
    $translation = $campaign->translations->first();
    $localeOptions = $campaign->translations->pluck('locale', 'locale')->all();
    $marketOptions = $campaign->markets->pluck('name', 'code')->all();
    $assetOptions = $assets->mapWithKeys(fn ($asset) => [$asset->id => basename($asset->artifact['path']) . ' · ' . $asset->artifact['mimeType']])->all();
    $providerOptions = function ($modality) use ($providers) { return collect($providers)->filter(fn ($provider) => in_array($modality, $provider['modalities'], true))->mapWithKeys(fn ($provider) => [$provider['id'] => $provider['name'] . ($provider['is_configured'] ? '' : ' · sin configurar')])->all(); };
    $statusLabels = ['pending'=>'Pendiente','processing'=>'Procesando','pending_remote'=>'Procesando en proveedor','pending_render'=>'Pendiente de composición','completed'=>'Completado','uncertain'=>'Pendiente de comprobación','paused'=>'Pausado','cancelled'=>'Cancelado','failed'=>'Fallido'];
@endphp
<div class="space-y-8">
    <x-admin.form-header :title="$translation?->title ?? 'Campaña'" description="Revisiones privadas y producción con identidad de marca." :backRoute="route('admin.creative-studio.index')" />
    <div class="flex flex-wrap gap-3"><x-admin.button :href="route('admin.modules.guides.index', 'creative-studio')" icon="fa-solid fa-book">Guías de proveedores y medios</x-admin.button><x-admin.button :href="route('admin.modules.settings', 'creative-studio')" icon="fa-solid fa-coins">Presupuesto de instalación</x-admin.button></div>
    <x-admin.form-card title="Guías de proveedores" subtitle="Consulta la preparación de cada proveedor, aunque todavía no esté configurado o AiAssistant esté desactivado.">
        <div class="flex flex-wrap gap-2">@foreach($providerGuides as $guide)
            <x-admin.button size="sm" :href="route('admin.modules.guides.show', ['identifier'=>'creative-studio', 'guide'=>$guide->id])" icon="fa-solid fa-book-open">Cómo configurar {{ $guide->platform }}</x-admin.button>
        @endforeach</div>
    </x-admin.form-card>
    <p class="admin-field-hint">{{ $campaign->objective }} · {{ $campaign->audience }} · Límite: {{ number_format($campaign->budget_limit / 100, 2) }} {{ $campaign->currency }}. Las estimaciones y las reservas no equivalen a importes facturados.</p>
    <div class="grid xl:grid-cols-2 gap-6 items-start">
        <x-admin.form-card title="Propuesta con IA" subtitle="Genera materiales de base y compón una nueva revisión. El texto, los archivos y la marca quedan sellados." icon="fa-solid fa-wand-magic-sparkles">
            <form method="POST" action="{{ route('admin.creative-studio.generate', $campaign->id) }}" class="space-y-4">
@csrf
                <input type="hidden" name="idempotency_key" value="{{ Str::uuid() }}">
                <div class="grid md:grid-cols-2 gap-4"><x-admin.select id="generate-locale" name="locale" label="Idioma" :options="$localeOptions" required /><x-admin.select id="generate-market" name="market" label="Mercado" :options="$marketOptions" required /></div>
                <div class="grid md:grid-cols-2 gap-4"><x-admin.select name="format" label="Pieza" :options="['text'=>'Texto', 'image'=>'Imagen', 'carousel'=>'Carrusel', 'video'=>'Vídeo corto']" required /><x-admin.select name="template" label="Plantilla" :options="['square'=>'Cuadrada 1080 × 1080', 'portrait'=>'Vertical 1080 × 1350', 'video'=>'Vídeo 1080 × 1920']" required /></div>
                <x-admin.select name="image_encoding" label="Archivo final de imagen" :options="['jpg'=>'JPEG · compatible con Instagram', 'png'=>'PNG']" />
                <x-admin.textarea name="instruction" label="Instrucciones para esta revisión" rows="3" />
                <div class="grid md:grid-cols-2 gap-4"><x-admin.input name="slides" label="Imágenes del carrusel" type="number" min="2" max="10" value="3" /><x-admin.input name="duration" label="Duración final de vídeo (segundos)" type="number" min="10" max="60" value="30" /></div>
                <x-admin.select name="provider" label="Proveedor de texto" :options="$providerOptions('text')" required /><x-admin.input name="model" label="Modelo de texto (opcional)" hint="Vacío utiliza el seleccionado en AiAssistant." />
                <x-admin.select name="image_provider" label="Proveedor de imágenes" :options="$providerOptions('image')" /><x-admin.input name="image_model" label="Modelo de imagen (opcional)" />
                <x-admin.select name="video_provider" label="Proveedor de vídeo" :options="$providerOptions('video')" /><x-admin.input name="video_model" label="Modelo de vídeo (opcional)" hint="Usa texto a vídeo. Los clips de 10 segundos se componen hasta la duración elegida." />
                <x-admin.button type="submit" variant="primary" icon="fa-solid fa-wand-magic-sparkles">Generar una propuesta</x-admin.button>
                <p class="admin-field-hint">Cada llamada reserva presupuesto. Las referencias para editar imágenes todavía no están disponibles en estos adaptadores.</p>
            </form>
        </x-admin.form-card>
        <div class="space-y-6">
            <x-admin.form-card title="Guardar texto manual" subtitle="Cada guardado crea una revisión; la anterior permanece intacta.">
                <form method="POST" action="{{ route('admin.creative-studio.revisions.create', $campaign->id) }}" class="space-y-4">
@csrf
<input type="hidden" name="format" value="text">
                    <div class="grid grid-cols-2 gap-4"><x-admin.select id="revision-locale" name="locale" label="Idioma" :options="$localeOptions" required /><x-admin.select id="revision-market" name="market" label="Mercado" :options="$marketOptions" required /></div>
                    <x-admin.textarea name="text" label="Mensaje" rows="5" required /><x-admin.input name="destination_url" label="Destino (opcional en creatividad libre)" type="url" /><x-admin.button type="submit" variant="primary">Crear revisión de texto</x-admin.button>
                </form>
            </x-admin.form-card>
            <x-admin.form-card title="Materiales privados" subtitle="Sube imágenes, logos o clips. Después podrás elegirlos para componer.">
                <form method="POST" action="{{ route('admin.creative-studio.assets.create', $campaign->id) }}" enctype="multipart/form-data" class="space-y-4">
@csrf
<x-admin.input name="file" label="Archivo" type="file" accept="image/png,image/jpeg,image/webp,video/mp4,video/quicktime" required /><x-admin.button type="submit">Añadir material</x-admin.button></form>
                <div class="mt-4 grid grid-cols-2 gap-3">
@foreach($assets as $asset)<div class="space-y-1">
@if(str_starts_with($asset->artifact['mimeType'], 'image/'))<img loading="lazy" src="{{ route('admin.creative-studio.assets.show', [$campaign->id, $asset->id]) }}" alt="Material de la campaña {{ $loop->iteration }}" class="w-full max-h-36 object-contain rounded">
@endif
<p class="admin-field-hint">{{ Str::limit($asset->id, 14) }} · {{ $asset->artifact['mimeType'] }}</p></div>
@endforeach
</div>
            </x-admin.form-card>
        </div>
    </div>
    <div class="grid xl:grid-cols-2 gap-6 items-start">
        <x-admin.form-card title="Componer con plantillas" subtitle="Aplica la marca a tus materiales. El vídeo compuesto es silencioso y descarta el audio de origen." icon="fa-solid fa-layer-group">
            <form method="POST" action="{{ route('admin.creative-studio.compose', $campaign->id) }}" class="space-y-4">
@csrf
                <div class="grid grid-cols-2 gap-4"><x-admin.select id="compose-locale" name="locale" label="Idioma" :options="$localeOptions" required /><x-admin.select id="compose-market" name="market" label="Mercado" :options="$marketOptions" required /></div>
                <x-admin.select id="compose-encoding" name="image_encoding" label="Archivo final de imagen" :options="['jpg'=>'JPEG · compatible con Instagram', 'png'=>'PNG']" />
                <x-admin.textarea id="compose-text" name="text" label="Mensaje y subtítulos" rows="3" required /><div class="grid grid-cols-2 gap-4"><x-admin.select id="compose-format" name="format" label="Formato" :options="['image'=>'Imagen', 'carousel'=>'Carrusel', 'video'=>'Vídeo']" /><x-admin.select id="compose-template" name="template" label="Plantilla" :options="['square'=>'Cuadrada', 'portrait'=>'Vertical', 'video'=>'Vídeo']" /></div>
                <x-admin.select id="compose-assets" name="asset_ids[]" label="Materiales en orden de selección" :options="$assetOptions" multiple size="5" required hint="Selecciona dos o más imágenes para un carrusel, o clips para vídeo." /><x-admin.input id="compose-duration" name="duration" label="Duración final de vídeo" type="number" min="10" max="60" value="30" /><x-admin.button type="submit" variant="primary">Componer revisión</x-admin.button>
            </form>
        </x-admin.form-card>
        <x-admin.form-card title="Identidad visual" subtitle="Los cambios se aplican a las nuevas revisiones, conservando las anteriores." icon="fa-solid fa-swatchbook">
            <form method="POST" action="{{ route('admin.creative-studio.brand', $campaign->id) }}" class="space-y-4">
@csrf
 
@method('PUT')
                <x-admin.input id="brand-name" name="name" label="Nombre de marca" :value="$campaign->brand_snapshot['name']" required /><div class="grid grid-cols-3 gap-4"><x-admin.input name="background" label="Fondo" type="color" :value="$campaign->brand_snapshot['background']" /><x-admin.input name="foreground" label="Texto" type="color" :value="$campaign->brand_snapshot['foreground']" /><x-admin.input name="accent" label="Acento" type="color" :value="$campaign->brand_snapshot['accent']" /></div>
                <x-admin.select name="logo_asset_id" label="Logo de la campaña" :options="$assetOptions" placeholder="Sin logo" /><x-admin.button type="submit">Guardar marca</x-admin.button>
            </form>
        </x-admin.form-card>
    </div>
    <x-admin.form-card title="Fechas de campaña" subtitle="Las fechas se conservan en cada nueva revisión. Los cambios no modifican piezas anteriores.">
        <form method="POST" action="{{ route('admin.creative-studio.dates', $campaign->id) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div class="grid md:grid-cols-2 gap-4">
                <x-admin.input id="campaign-start" name="starts_at" label="Inicio (opcional)" type="datetime-local" :value="$campaign->starts_at?->copy()->setTimezone(config('app.timezone', 'UTC'))->format('Y-m-d\TH:i')" :hint="'Zona horaria: ' . config('app.timezone', 'UTC')" />
                <x-admin.input id="campaign-end" name="ends_at" label="Fin (opcional)" type="datetime-local" :value="$campaign->ends_at?->copy()->setTimezone(config('app.timezone', 'UTC'))->format('Y-m-d\TH:i')" hint="El fin debe ser igual o posterior al inicio." />
            </div>
            <x-admin.button type="submit">Guardar fechas</x-admin.button>
        </form>
    </x-admin.form-card>
    <x-admin.form-card title="Presupuesto de campaña" subtitle="Las reservas existentes se conservan y las nuevas llamadas necesitan una estimación configurada.">
        <form method="POST" action="{{ route('admin.creative-studio.budget', $campaign->id) }}" class="space-y-4">
@csrf
 
@method('PUT')<div class="grid md:grid-cols-2 gap-4"><x-admin.input id="campaign-budget" name="budget_limit" label="Límite en céntimos" type="number" min="0" step="1" :value="$campaign->budget_limit" required /><x-admin.select id="campaign-currency" name="currency" label="Moneda" :value="$campaign->currency" :options="['USD'=>'USD', 'EUR'=>'EUR']" /></div><x-admin.button type="submit">Guardar presupuesto</x-admin.button></form>
    </x-admin.form-card>
    <x-admin.form-card title="Idioma y brief" subtitle="Añade otra traducción o ajusta el brief para las próximas propuestas.">
        <form method="POST" action="{{ route('admin.creative-studio.brief', $campaign->id) }}" class="space-y-4">
@csrf
 
@method('PUT')<div class="grid md:grid-cols-2 gap-4"><x-admin.input id="brief-locale" name="locale" label="Código de idioma activo" :value="$translation?->locale" required /><x-admin.input id="brief-title" name="title" label="Nombre de campaña" :value="$translation?->title" required /></div><x-admin.textarea id="brief-text" name="brief" label="Brief" :value="$translation?->brief" rows="3" required /><x-admin.button type="submit">Guardar brief</x-admin.button></form>
    </x-admin.form-card>
    <x-admin.form-card title="Producción" subtitle="Actualiza esta campaña para comprobar el avance de las tareas.">
        <x-admin.table :empty="$tasks->isEmpty()" emptyTitle="Sin tareas pendientes" emptyDescription="Puedes guardar texto, generar una propuesta o componer materiales."><x-slot:header><th class="p-3">Tarea</th><th class="p-3">Estado</th><th class="p-3">Reserva estimada</th><th class="p-3">Acción</th></x-slot:header>
@foreach($tasks as $task)<tr><td class="p-3">{{ Str::limit($task->id, 16) }}</td><td class="p-3"><x-admin.status-badge :status="$statusLabels[$task->status] ?? $task->status" /><p class="admin-field-hint">{{ $task->error }}</p></td><td class="p-3">{{ number_format($task->reserved_cost / 100, 2) }} {{ $campaign->currency }}<p class="admin-field-hint">Confirmado: {{ $task->confirmed_cost !== null ? number_format($task->confirmed_cost / 100, 2) : 'No disponible' }}</p></td><td class="p-3 space-y-2">
@if($task->status==='paused')<form method="POST" action="{{ route('admin.creative-studio.tasks.resume', $task->id) }}">
@csrf
<x-admin.button type="submit" size="sm">Reanudar</x-admin.button></form>
@elseif($task->status==='uncertain')<form method="POST" action="{{ route('admin.creative-studio.tasks.reconcile', $task->id) }}">
@csrf
<x-admin.button type="submit" size="sm">Consultar resultado remoto</x-admin.button></form>
@elseif($task->status==='pending')<form method="POST" action="{{ route('admin.creative-studio.tasks.cancel', $task->id) }}">
@csrf
<x-admin.button type="submit" size="sm">Cancelar antes de empezar</x-admin.button></form>
@endif
</td></tr>
@endforeach
</x-admin.table>
    </x-admin.form-card>
    <x-admin.form-card title="Revisiones" subtitle="Elige la pieza exacta que revisarás y programarás en SocialPublishing.">
        
@forelse($campaign->revisions as $revision)
            <article class="py-5 space-y-4 border-b border-current/10"><div class="flex flex-wrap justify-between gap-4"><h3 class="font-semibold">Revisión {{ $revision->number }} · {{ $revision->locale }} / {{ $revision->market }} · {{ $revision->format }}</h3><x-admin.button :href="route('admin.creative-studio.export', $revision->id)" icon="fa-solid fa-download">Descargar ZIP</x-admin.button></div><p class="whitespace-pre-wrap">{{ $revision->text }}</p>
@if($revision->destination_url)<p class="admin-field-hint">Destino: {{ $revision->destination_url }}</p>
@endif
<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
@foreach($revision->artifacts as $artifact)
@if(str_starts_with($artifact['mimeType'], 'video/'))<video controls preload="metadata" src="{{ route('admin.creative-studio.artifacts.show', [$revision->id, $loop->index]) }}" class="w-full max-h-96"></video>
@else
<img loading="lazy" src="{{ route('admin.creative-studio.artifacts.show', [$revision->id, $loop->index]) }}" alt="Revisión {{ $revision->number }}, pieza {{ $loop->iteration }}" class="w-full max-h-96 object-contain">
@endif
@endforeach
</div><p class="admin-field-hint">ID para importar en SocialPublishing: {{ $revision->id }}</p></article>
        
@empty
<x-admin.empty-state title="Aún no hay revisiones" description="Crea la primera pieza de esta campaña." />
@endforelse
    </x-admin.form-card>
</div>
@endsection

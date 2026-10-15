@extends('admin.layout')
@section('title', 'CreativeStudio')
@section('content')
<div class="space-y-8">
    <x-admin.datatable-header title="CreativeStudio" description="De una idea a una revisión lista para compartir. Campañas y archivos privados de este minisite.">
        <x-slot:actions>
            <x-admin.button :href="route('admin.modules.guides.index', 'creative-studio')" icon="fa-solid fa-book-open">Guías de configuración</x-admin.button>
            <x-admin.button :href="route('admin.modules.settings', 'creative-studio')" icon="fa-solid fa-coins">Presupuesto</x-admin.button>
        </x-slot:actions>
    </x-admin.datatable-header>
    <x-admin.table :empty="$campaigns->isEmpty()" emptyTitle="Tu próxima campaña empieza aquí" emptyDescription="Escribe un brief libre o selecciona un contenido publicado. Después podrás generar, componer y exportar revisiones.">
        <x-slot:header><th class="p-4">Campaña</th><th class="p-4">Objetivo</th><th class="p-4">Contexto</th><th class="p-4">Presupuesto</th><th class="p-4">Abrir</th></x-slot:header>
        
@foreach($campaigns as $campaign)
            <tr><td class="p-4 font-semibold">{{ $campaign->translations->first()?->title }}</td><td class="p-4">{{ Str::limit($campaign->objective, 70) }}</td><td class="p-4"><x-admin.market-chips :markets="$campaign->markets" /><x-admin.locale-chips :locales="$campaign->translations->pluck('locale')" /></td><td class="p-4">{{ number_format($campaign->budget_limit / 100, 2) }} {{ $campaign->currency }}</td><td class="p-4"><x-admin.button :href="route('admin.creative-studio.show', $campaign->id)" size="sm">Abrir campaña</x-admin.button></td></tr>
        
@endforeach
        <x-slot:pagination><x-admin.datatable-pagination :paginator="$campaigns" /></x-slot:pagination>
    </x-admin.table>
    <x-admin.form-card title="Crear una campaña" subtitle="El contenido que selecciones será la única referencia editorial de la generación." icon="fa-solid fa-palette">
        <form method="POST" action="{{ route('admin.creative-studio.create') }}" class="space-y-5" x-data="{ locale: @js($languages->first()?->code), marketId: @js($markets->first()?->id), sources: {}, source: '', async loadSources() { this.source = ''; const response = await fetch(@js(route('admin.creative-studio.sources')) + '?locale=' + encodeURIComponent(this.locale) + '&amp;market_id=' + encodeURIComponent(this.marketId), { headers: { Accept: 'application/json' } }); this.sources = response.ok ? (await response.json()).data : {}; } }" x-init="loadSources()">
@csrf
            <div class="grid md:grid-cols-2 gap-5"><x-admin.input name="title" label="Nombre de campaña" required /><x-admin.select name="mode" label="Modo" :options="['free'=>'Creatividad libre', 'traffic'=>'Atraer tráfico a un contenido publicado']" required /></div>
            <x-admin.textarea name="brief" label="Brief" rows="4" hint="Describe la idea, el mensaje y las referencias. Evita datos personales o material privado." required />
            <div class="grid md:grid-cols-2 gap-5"><x-admin.input name="objective" label="Objetivo" required /><x-admin.input name="audience" label="Audiencia" required /></div>
            <div class="grid md:grid-cols-2 gap-5"><x-admin.select name="locale" x-model="locale" x-on:change="loadSources()" label="Idioma del brief" :options="$languages->pluck('name', 'code')->all()" required /><x-admin.select name="market_ids[]" x-model="marketId" x-on:change="loadSources()" id="new-campaign-markets" label="Mercado" :options="$markets->pluck('name', 'id')->all()" required /></div>
            <div class="grid md:grid-cols-2 gap-5">
                <x-admin.input name="starts_at" label="Inicio de campaña (opcional)" type="datetime-local" :hint="'Zona horaria: ' . config('app.timezone', 'UTC')" />
                <x-admin.input name="ends_at" label="Fin de campaña (opcional)" type="datetime-local" hint="El fin debe ser igual o posterior al inicio." />
            </div>
            <x-admin.select name="source_ref" label="Contenido publicado para atraer tráfico" x-model="source" placeholder="Selecciona un contenido o utiliza creatividad libre" hint="El listado incluye solo el contenido publicado y autorizado en el idioma y mercado elegidos."><template x-for="(label, value) in sources" :key="value"><option :value="value" x-text="label"></option></template></x-admin.select>
            <div class="grid md:grid-cols-2 gap-5"><x-admin.input name="budget_limit" label="Límite de la campaña en céntimos" type="number" value="0" min="0" step="1" required hint="0 permite trabajo manual y exportación; activa un presupuesto para generar con IA." /><x-admin.select name="currency" label="Moneda" :options="['USD'=>'USD', 'EUR'=>'EUR']" :value="config('creative_studio.currency', 'USD')" required /></div>
            <x-admin.form-actions :cancelRoute="route('admin.modules.index')" submitLabel="Crear campaña" />
        </form>
    </x-admin.form-card>
</div>
@endsection

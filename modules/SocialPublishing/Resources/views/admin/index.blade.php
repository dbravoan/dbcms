@extends('admin.layout')

@section('content')
<div class="admin-page space-y-8">
    <x-admin.datatable-header title="Publicaciones sociales" description="Cada cuenta tiene su revisión, programación y resultado. El calendario muestra la hora de esta instalación." />
    

@if($errors->any())
        <x-admin.form-card title="Hay un paso pendiente"><ul role="alert">

@foreach($errors->all() as $error)<li>{{ $error }}</li>

@endforeach

</ul><a class="admin-link" href="{{ route('admin.modules.guides.index',['identifier'=>'social-publishing']) }}">Abrir los tutoriales de configuración</a></x-admin.form-card>
    

@endif

    <x-admin.form-card title="01 · Conexiones" subtitle="Consulta el tutorial, configura el cliente y autoriza la cuenta. Comprobar una conexión no publica ningún material.">
        <div class="grid gap-4 md:grid-cols-2">
            

@foreach($providers as $provider=>$label)
                <div class="admin-section space-y-3">
                    <h3 class="admin-field-label">{{ $label }}</h3>
                    <div class="flex flex-wrap gap-3">
                        <a class="admin-link" href="{{ route('admin.modules.guides.show',['identifier'=>'social-publishing','guide'=>$provider]) }}">Ver tutorial · Cómo configurar {{ $label }}</a>
                        <a class="admin-link" href="{{ route('admin.modules.settings',['identifier'=>'social-publishing']) }}">Configuración</a>
                        

@if($provider!=='tiktok')<form method="POST" action="{{ route('admin.social-publishing.oauth.begin',$provider) }}">

@csrf

<button class="admin-btn admin-btn--primary" type="submit">Autorizar</button></form>

@endif

                    </div>
                </div>
            

@endforeach

        </div>
        <div class="mt-6">
            <x-admin.table :empty="$accounts->isEmpty()" emptyTitle="Todavía no hay cuentas" emptyDescription="Puedes autorizar una cuenta o añadir un destino para descargar y publicar manualmente.">
                <x-slot:header><th scope="col">Cuenta</th><th scope="col">Red</th><th scope="col">Conexión</th><th scope="col">Acciones</th></x-slot:header>
                

@foreach($accounts as $a)<tr><td>{{ $a->label }}</td><td>{{ $providers[$a->provider] ?? $a->provider }}</td><td>{{ $a->status }}<br>Última comprobación: {{ $a->metadata['last_check']['status'] ?? 'Sin comprobación' }}<br>{{ $a->metadata['last_check']['checked_at'] ?? '' }}</td><td><div class="flex gap-3"><form action="{{ route('admin.social-publishing.check',$a->id) }}" method="POST">

@csrf

<button type="submit" class="admin-link">Comprobar</button></form><form action="{{ route('admin.social-publishing.revoke',$a->id) }}" method="POST">

@csrf

<button type="submit" class="admin-link">Revocar conexión</button></form></div></td></tr>

@endforeach

            </x-admin.table>
        </div>
        <form class="grid gap-4 mt-6 md:grid-cols-3" method="POST" action="{{ route('admin.social-publishing.manual-account') }}">

@csrf

            <x-admin.select name="provider" label="Red para exportación" :options="$providers" required />
            <x-admin.input name="label" label="Nombre del destino manual" required />
            <x-admin.button type="submit" class="self-end">Añadir destino manual</x-admin.button>
        </form>
    </x-admin.form-card>
    <x-admin.form-card title="02 · Preparar una publicación" subtitle="Escribe el mensaje y carga los archivos finales. Para reutilizar CreativeStudio, introduce la revisión exportable en el formulario de importación.">
        <form action="{{ route('admin.social-publishing.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">

@csrf

            <x-admin.select name="account_id" id="social-prepare-account" label="Cuenta de destino" required placeholder="Selecciona una cuenta">

@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->label }} · {{ $a->provider }}</option>

@endforeach

</x-admin.select>
            <div class="grid gap-4 md:grid-cols-2"><x-admin.input name="locale" label="Idioma" value="es" required /><x-admin.input name="market" label="Mercado" value="es" required /></div>
            <x-admin.textarea name="text" label="Texto final" rows="5" required />
            <x-admin.input name="destination_url" label="Destino publicado del minisite" type="url" hint="Opcional. Se congelarán las UTM antes de la aprobación." />
            <x-admin.input name="campaign_id" label="Identificador de campaña" />
            <x-admin.input name="files[]" label="Archivos finales, en orden" type="file" multiple accept="image/jpeg,image/png,video/mp4" />
            <details><summary>Metadatos para YouTube y LinkedIn</summary><div class="space-y-3 mt-3"><x-admin.input name="metadata[title]" id="social-prepare-title" label="Título" /><x-admin.select name="metadata[made_for_kids]" id="social-prepare-made-for-kids" label="Creado para niños" placeholder="Selecciona para YouTube" :options="['0'=>'No','1'=>'Sí']" /><x-admin.select name="metadata[synthetic_media]" label="Contenido sintético que necesita declaración" :options="['0'=>'No','1'=>'Sí']" /></div></details>
            <x-admin.form-actions :cancelRoute="route('admin.social-publishing.index')" submitLabel="Guardar y revisar" />
        </form>
        <form action="{{ route('admin.social-publishing.store') }}" method="POST" class="grid gap-4 mt-6 md:grid-cols-3">

@csrf

            <x-admin.input name="creative_revision_id" label="Revisión de CreativeStudio" required />
            <x-admin.select name="account_id" id="social-import-account" label="Cuenta para la importación" required>

@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->label }} · {{ $a->provider }}</option>

@endforeach

</x-admin.select>
            <x-admin.input name="metadata[title]" id="social-import-title" label="Título para YouTube / LinkedIn" />
            <x-admin.select name="metadata[made_for_kids]" id="social-import-made-for-kids" label="Creado para niños (YouTube)" placeholder="Seleccionar para YouTube" :options="['0'=>'No','1'=>'Sí']" />
            <x-admin.button type="submit" class="self-end">Importar revisión autorizada</x-admin.button>
        </form>
    </x-admin.form-card>
    <x-admin.form-card title="03 · Revisiones" subtitle="Aprobar fija texto, destino, metadatos, cuenta y orden de archivos.">
        <x-admin.table :empty="$publications->isEmpty()" emptyTitle="Prepara tu primera publicación" emptyDescription="Los borradores y las importaciones aparecen aquí antes de aprobarse.">
            <x-slot:header><th scope="col">Revisión</th><th scope="col">Estado editorial</th><th scope="col">Acción</th></x-slot:header>
            

@foreach($publications as $p)<tr><td>{{ substr($p->revision_id ?? '',0,8) }}</td><td>{{ $p->status }}</td><td><a href="{{ route('admin.social-publishing.preview',$p->id) }}" class="admin-link">Ver la revisión exacta</a></td></tr>

@endforeach

            <x-slot:pagination>{{ $publications->links() }}</x-slot:pagination>
        </x-admin.table>
    </x-admin.form-card>
    <x-admin.form-card title="04 · Calendario y resultados" :subtitle="'Zona horaria: '.$timezone.'. Cada envío conserva su resultado; cancelar no retira publicaciones remotas.'">
        <x-admin.table :empty="$deliveries->isEmpty()" emptyTitle="No hay envíos programados" emptyDescription="Programa una revisión aprobada para verla en este calendario.">
            <x-slot:header><th scope="col">Programado</th><th scope="col">Estado</th><th scope="col">Resultado remoto</th><th scope="col">Acción</th></x-slot:header>
            

@foreach($deliveries as $d)<tr><td>{{ ($d->scheduled_at ?? $d->due_at)->timezone($timezone)->format('d/m/Y H:i P') }}</td><td>{{ $d->status }} 

@if($d->cancel_requested)· Cancelación solicitada

@endif

</td><td>{{ $d->remote_id ?? 'Sin confirmación' }}<br>{{ $d->error_code }}</td><td><a class="admin-link" href="{{ route('admin.social-publishing.preview',$d->publication_id) }}">Ver resultado</a></td></tr>

@endforeach

        </x-admin.table>
    </x-admin.form-card>
</div>

@endsection


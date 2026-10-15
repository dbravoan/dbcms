@extends('admin.layout')

@section('content')
<div class="space-y-6">
    <x-admin.datatable-header title="Revisión exacta" :subtitle="'Destino: '.$account->label.' · '.$account->provider" />
    

@if($errors->any())<x-admin.form-card title="Revisa este paso"><ul role="alert">

@foreach($errors->all() as $error)<li>{{ $error }}</li>

@endforeach

</ul><a href="{{ route('admin.modules.guides.show',['identifier'=>'social-publishing','guide'=>$account->provider]) }}#step-check" class="admin-link">Ayuda para este destino</a></x-admin.form-card>

@endif

    <div class="grid gap-6 lg:grid-cols-2">
        <x-admin.form-card title="Contenido que se enviará" subtitle="Esta vista corresponde a una revisión guardada. Cada archivo se comprueba antes del envío.">
            <div class="whitespace-pre-wrap">{{ $revision->package['text'] }}</div>
            

@if($revision->package['destinationUrl'])<p class="mt-4"><a class="admin-link break-all" href="{{ $revision->package['destinationUrl'] }}" target="_blank" rel="noopener noreferrer">{{ $revision->package['destinationUrl'] }}</a></p>

@endif

            <div class="grid gap-3 mt-4">
                

@foreach($revision->package['artifacts'] as $i=>$file)
                    

@if(str_starts_with($file['mimeType'],'image/'))<img src="{{ route('admin.social-publishing.preview-asset',[$publication->id,$i]) }}" alt="Archivo final {{ $i+1 }} en orden de publicación" class="max-h-96 object-contain" />
                    

@else<video src="{{ route('admin.social-publishing.preview-asset',[$publication->id,$i]) }}" controls preload="metadata" class="max-h-96"></video>

@endif

                    <p class="text-xs break-all">{{ $i+1 }} · {{ $file['mimeType'] }} · SHA-256 {{ $file['sha256'] }}</p>
                

@endforeach

            </div>
            <dl class="mt-5 space-y-2"><dt>Revisión</dt><dd>{{ $revision->id }}</dd><dt>Idioma / mercado</dt><dd>{{ $revision->package['locale'] }} / {{ $revision->package['market'] }}</dd><dt>Identidad autorizada</dt><dd>{{ $revision->account_identity }} · {{ $revision->account_generation }}</dd><dt>Metadatos</dt><dd class="whitespace-pre-wrap">{{ json_encode($revision->metadata,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</dd><dt>Huella de aprobación</dt><dd class="break-all text-xs">{{ $revision->payload_hash }}</dd></dl>
        </x-admin.form-card>
        <div class="space-y-6">
            <x-admin.form-card title="Revisar, aprobar y programar" subtitle="Solo una sesión humana con permiso de revisión puede aprobar. Una edición exige otra aprobación.">
                

@if($publication->approved_revision_id!==$revision->id)
                    <form method="POST" action="{{ route('admin.social-publishing.approve',$publication->id) }}" class="space-y-4">

@csrf

<input type="hidden" name="payload_hash" value="{{ $revision->payload_hash }}" />
                        <x-admin.select name="brand_checked" label="Texto, marca y destino exactos" :options="['0'=>'Pendiente de revisión','1'=>'He revisado esta versión exacta']" required />
                        <x-admin.select name="rights_checked" label="Archivos finales y derechos de publicación" :options="['0'=>'Pendiente de revisión','1'=>'He revisado los archivos y sus derechos']" required />
                        

@if($account->provider!=='tiktok')<x-admin.input name="publish_at" label="Programar al aprobar (opcional)" placeholder="2026-10-20T10:30:00+02:00" :hint="'Fecha ISO con desplazamiento explícito. Zona: '.$timezone" />

@endif

                        <button class="admin-btn-primary" type="submit">Aprobar esta revisión</button>
                    </form>
                

@else<p>Esta revisión está aprobada. Una hora nueva mantiene la aprobación mientras el contenido y la cuenta sigan iguales.</p>

@endif

                

@if($account->provider!=='tiktok' && $publication->approved_revision_id===$revision->id)
                    <form action="{{ route('admin.social-publishing.schedule',$publication->id) }}" method="POST" class="mt-5 space-y-4">

@csrf

                        <x-admin.input name="publish_at" label="Fecha y hora ISO con zona explícita" placeholder="2026-10-20T10:30:00+02:00" :hint="'Zona de la instalación: '.$timezone.'. Usa el desplazamiento explícito para evitar ambigüedades del cambio de hora.'" required />
                        <button type="submit" class="admin-btn-primary">Programar o reprogramar</button>
                    </form>
                

@endif

                <a href="{{ route('admin.social-publishing.export',$publication->id) }}" class="admin-link inline-block mt-5">Descargar texto, archivos finales y revisión</a>
            </x-admin.form-card>
            <x-admin.form-card title="Editar creando otra revisión" subtitle="Guardar una edición cancela los pasos pendientes e invalida la aprobación anterior.">
                <form method="POST" action="{{ route('admin.social-publishing.store') }}" enctype="multipart/form-data" class="space-y-4">

@csrf

                    <input type="hidden" name="publication_id" value="{{ $publication->id }}" /><input type="hidden" name="expected_revision" value="{{ $revision->id }}" />
                    <input type="hidden" name="locale" value="{{ $revision->package['locale'] }}" /><input type="hidden" name="market" value="{{ $revision->package['market'] }}" />
                    <x-admin.select name="account_id" label="Cuenta" :value="$account->id" required>

@foreach($accounts as $target)<option value="{{ $target->id }}" @selected($target->id===$account->id)>{{ $target->label }} · {{ $target->provider }}</option>

@endforeach

</x-admin.select>
                    <x-admin.textarea name="text" label="Texto" :value="$revision->package['text']" rows="5" required />
                    <x-admin.input name="destination_url" label="Destino publicado" type="url" :value="$revision->package['destinationUrl']" />
                    <x-admin.input name="metadata[title]" label="Título de YouTube / LinkedIn" :value="$revision->metadata['title']??''" />
                    <x-admin.select name="metadata[made_for_kids]" label="Creado para niños" placeholder="Seleccionar para YouTube" :value="isset($revision->metadata['made_for_kids'])?(int)$revision->metadata['made_for_kids']:null" :options="['0'=>'No','1'=>'Sí']" />
                    <x-admin.input name="files[]" label="Reemplazar archivos, en orden" type="file" multiple accept="image/jpeg,image/png,video/mp4" hint="Sin archivos nuevos se conservan los finales de la revisión anterior." />
                    <x-admin.select name="remove_assets" label="Archivos de la nueva revisión" :options="['0'=>'Conservar archivos existentes','1'=>'Quitar archivos y preparar solo texto']" />
                    <button type="submit" class="admin-btn-secondary">Guardar una nueva revisión</button>
                </form>
            </x-admin.form-card>
            <x-admin.form-card title="Resultado por envío">
                

@forelse($deliveries as $d)<div class="space-y-3 mb-5"><p>{{ $d->status }} · {{ $d->remote_id ?? 'Sin identificador confirmado' }}</p><p>{{ $d->error_code }}</p><div class="flex gap-4"><form action="{{ route('admin.social-publishing.cancel',$d->id) }}" method="POST">

@csrf

<button type="submit" class="admin-link">Cancelar los pasos pendientes</button></form>

@if($d->status==='pending_confirmation')<form action="{{ route('admin.social-publishing.reconcile',$d->id) }}" method="POST">

@csrf

<button type="submit" class="admin-link">Comprobar evidencia remota</button></form>

@endif

</div></div>

@empty<p>No se ha programado un envío para esta revisión.</p>

@endforelse

            </x-admin.form-card>
            <x-admin.form-card title="Métricas disponibles" subtitle="Cada observación mantiene fuente y fecha. Un valor no disponible no equivale a cero.">
                

@forelse($metrics as $metric)
                <div class="space-y-2 mb-5"><p>{{ $metric->source }} · {{ $metric->observed_at->format('d/m/Y H:i') }} · {{ $metric->availability }}</p>
                
@if(in_array($metric->source,['ga4','ga4-data-api'],true))
                    <p>Visitas atribuidas (sesiones): {{ $metric->metrics['sessions'] ?? 'No disponible' }} · Eventos clave: {{ $metric->metrics['key_events'] ?? 'No disponible' }}</p>
                    <p>Frescura: {{ $metric->metrics['status'] ?? 'No disponible' }} · Última consulta confirmada: {{ $metric->metrics['fetched_at'] ?? 'Sin confirmación' }}</p>
                    <p>Campaña {{ $metric->metrics['campaign'] ?? '' }} · Contenido {{ $metric->metrics['content'] ?? '' }} · {{ $metric->metrics['dateFrom'] ?? '' }} a {{ $metric->metrics['dateTo'] ?? '' }}</p>
                    <p>Datos agregados sujetos a consentimiento, retraso de procesamiento y eventos configurados; los informes antiguos pueden conservar cifras con estado stale.</p>
                
@else
                    
@foreach(($metric->metrics['values'] ?? []) as $name=>$counter)<p>{{ $name }}: {{ $counter['value'] ?? 'No disponible' }}</p>
@endforeach

                    <p>{{ $metric->metrics['window'] ?? 'Ventana no disponible' }}</p>
                
@endif

                </div>

@empty<p>Todavía no hay observaciones confirmadas.</p>

@endforelse

                <a href="{{ route('admin.modules.guides.show',['identifier'=>'social-publishing','guide'=>'ga4']) }}" class="admin-link">Configurar medición de tráfico y conversiones</a>
            </x-admin.form-card>
        </div>
    </div>
</div>

@endsection


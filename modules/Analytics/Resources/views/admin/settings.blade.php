@extends('admin.layout')

@section('title', 'Google Analytics 4')

@section('content')
<div class="admin-page space-y-6">
    <x-admin.form-header title="Ajustes del Módulo: Google Analytics 4" description="La medición respeta el consentimiento; los informes leen únicamente las campañas y piezas seleccionadas." :back-route="route('admin.modules.index')" />
    <form action="{{ route('admin.modules.settings.update', ['identifier' => 'analytics']) }}" method="POST" class="admin-card p-6 space-y-5">
        @csrf
        @method('PUT')
        <x-admin.input name="ga4_measurement_id" label="GA4 Measurement ID" :value="$settings['ga4_measurement_id'] ?? ''" placeholder="G-XXXXXXXXXX" hint="Identificador del flujo web. La etiqueta sólo se carga tras aceptar las cookies analíticas." />
        <x-admin.input name="ga4_property_id" label="ID numérico de propiedad" :value="$settings['ga4_property_id'] ?? ''" inputmode="numeric" pattern="[0-9]*" hint="Se utiliza para consultar informes. No es el identificador G- del flujo web." />
        <x-admin.secret-textarea name="ga4_service_account_json" label="JSON privado de la cuenta de servicio" :configured="!empty($settings['ga4_service_account_json'])" hint="Pega el JSON completo. Se almacena cifrado; dejarlo vacío conserva el anterior. Añade su correo como Lector de la propiedad GA4." />
        <x-admin.input name="ga4_conversion_events" label="Eventos de conversión autorizados" :value="$settings['ga4_conversion_events'] ?? ''" placeholder="generate_lead,purchase" hint="Activa el registro de acciones de éxito explícitas con estos nombres y márcalos como eventos clave en GA4. No se recopilan campos de formularios." />
        <p class="admin-field-hint">Anonimizar IPs (RGPD): la medición utiliza la protección de GA4 y requiere consentimiento analítico vigente.</p>
        <p class="text-sm text-[color:var(--admin-muted)]">Los informes pueden tener retrasos, umbrales y límites de atribución de Google. La ausencia de datos o de acceso se muestra como no disponible.</p>
        <div class="flex flex-wrap gap-3">
            <x-admin.button type="submit" variant="primary">Guardar configuración</x-admin.button>
            @if(app(\App\Core\Integrations\IntegrationGuideRegistry::class)->find('social-publishing', 'ga4'))
                <x-admin.button :href="route('admin.modules.guides.show', ['identifier' => 'social-publishing', 'guide' => 'ga4'])">Tutorial de GA4</x-admin.button>
            @endif
        </div>
    </form>
</div>
@endsection

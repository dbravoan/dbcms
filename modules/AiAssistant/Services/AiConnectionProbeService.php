<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use Modules\AiAssistant\Contracts\AiProviderRegistryInterface;
use Modules\AiAssistant\DTOs\ConnectionResult;

final class AiConnectionProbeService
{
    public function __construct(private readonly AiProviderRegistryInterface $registry, private readonly AiProviderSettings $settings) {}

    /** @return array{success:bool,status:string,provider:string,model:string,message:string,latency_ms:int,modality:string,connection_state:string,generation_verified:bool} */
    public function probe(string $provider, string $modality = 'text'): array
    {
        $start = microtime(true);
        $adapter = $this->registry->resolve($provider);
        $definition = $adapter->definition();
        $model = (string) ($this->settings->modelForModality($provider, $modality) ?? ($modality === 'text' ? $this->settings->model($provider) : null) ?? '');
        if (! in_array($modality, ['text', 'image', 'video'], true) || ! $definition->supportsModality($modality)) {
            return $this->result(false, $provider, $model, $modality, 'unsupported', 'Este adaptador no admite la modalidad solicitada. No se ha generado contenido.', $start);
        }
        if (! $this->settings->isConfigured($provider)) {
            return $this->result(false, $provider, $model, $modality, 'unconfigured', 'Guarda primero las credenciales o el endpoint. Esta comprobación no ejecuta generación.', $start);
        }
        if (! $definition->supportsModelListing) {
            return $this->result(false, $provider, $model, $modality, 'configured_unverified', 'Credenciales configuradas. Este adaptador no dispone de una comprobación de autenticación documentada sin generación; revisa la guía y realiza una muestra explícita con presupuesto en CreativeStudio.', $start);
        }
        try {
            $models = $adapter->listModels($this->settings->apiKey($provider), $this->settings->baseUrl($provider));
        } catch (\Throwable) {
            return $this->result(false, $provider, $model, $modality, 'unavailable', 'No se pudo consultar el catálogo. Revisa credenciales, endpoint y permisos en la guía; no se inició inferencia.', $start);
        }
        if ($models === []) {
            return $this->result(false, $provider, $model, $modality, 'unavailable', 'El catálogo no devolvió modelos accesibles. No se ha generado contenido.', $start);
        }
        if ($model === '') {
            return $this->result(false, $provider, $model, $modality, 'model_not_selected', 'El catálogo es accesible. Selecciona un modelo para esta modalidad; la generación sigue sin comprobar.', $start);
        }
        if (! in_array($model, array_map(static fn ($option): string => $option->id, $models), true)) {
            return $this->result(false, $provider, $model, $modality, 'model_unavailable', 'La clave permite leer el catálogo, pero el modelo seleccionado no aparece. Elige un modelo accesible.', $start);
        }

        return $this->result(true, $provider, $model, $modality, 'catalog_verified', 'Acceso al catálogo y presencia del modelo comprobados mediante lectura. Esto no acredita generación ni facturación del modelo.', $start);
    }

    /** @return array{success:bool,status:string,provider:string,model:string,message:string,latency_ms:int,modality:string,connection_state:string,generation_verified:bool} */
    private function result(bool $success, string $provider, string $model, string $modality, string $state, string $message, float $start): array
    {
        return (new ConnectionResult($success, $provider, $model, $message, (int) round((microtime(true) - $start) * 1000)))->toArray() + ['modality' => $modality, 'connection_state' => $state, 'generation_verified' => false];
    }
}

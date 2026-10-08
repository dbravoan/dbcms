<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use Modules\AiAssistant\Models\AiGenerationTask;

/** Public task status never serializes stored exception messages. */
final class AiTaskPresentation
{
    public const FAILURE_MESSAGE = 'La generación no pudo completarse.';

    /** @return array<string, mixed> */
    public function payload(AiGenerationTask $task): array
    {
        $result = $task->result ?? [];
        [$code, $message] = match (true) {
            $task->status === AiGenerationTask::STATUS_APPROVAL_REQUIRED => ['approval_required', 'Se requiere aprobación humana del cambio concreto.'],
            $task->status === AiGenerationTask::STATUS_UNCERTAIN => ['result_uncertain', 'No se pudo confirmar el resultado. Revisa la tarea antes de repetirla.'],
            $task->status !== AiGenerationTask::STATUS_FAILED => [null, null],
            ! empty($result['denied']) => ['permission_denied', 'Permisos insuficientes.'],
            ! empty($result['unavailable']) => ['capability_unavailable', 'La capacidad no está disponible. Revisa los módulos y proveedores configurados.'],
            ! empty($result['conflict']) => ['content_conflict', 'El contenido cambió. Revisa la versión actual.'],
            isset($result['validation']) => ['invalid_arguments', 'Revisa los datos de la solicitud.'],
            default => ['generation_failed', self::FAILURE_MESSAGE],
        };

        return [
            'success' => true,
            'task_id' => $task->id,
            'status' => $task->status,
            'action' => $task->action,
            'finished' => $task->isFinished(),
            'data' => in_array($task->status, [AiGenerationTask::STATUS_COMPLETED, AiGenerationTask::STATUS_NEEDS_INPUT], true) ? $result : null,
            'question' => $task->status === AiGenerationTask::STATUS_NEEDS_INPUT ? ($result['question'] ?? null) : null,
            'message' => $task->status === AiGenerationTask::STATUS_NEEDS_INPUT ? ($result['message'] ?? null) : null,
            'error' => $message,
            'error_code' => $code,
            'execution_id' => $result['execution_id'] ?? null,
            'review_url' => $result['review_url'] ?? null,
        ];
    }
}

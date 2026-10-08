<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Jobs;

use App\Contracts\Services\ModuleNotificationServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\AiAssistant\Models\AiGenerationTask;
use Modules\AiAssistant\Services\AiTaskPresentation;
use Modules\AiAssistant\Services\AiTaskService;
use Throwable;

/**
 * Runs a queued AI action out of band so slow providers (e.g. local Ollama on
 * CPU) never tie up a web worker. State lives in `ai_generation_tasks` and the
 * owner is notified (backoffice bell) when it finishes.
 */
final class ProcessAiGeneration implements ShouldQueue
{
    use Queueable;

    /**
     * Local providers can take several minutes; keep the worker alive.
     */
    public int $timeout = 1200;

    public int $tries = 1;

    public function __construct(public string $taskId) {}

    public function handle(AiTaskService $runner): void
    {
        $task = AiGenerationTask::find($this->taskId);

        if ($task === null) {
            return;
        }

        if ($task->isFinished()) {
            return;
        }

        try {
            $result = $runner->runTask($task);
            if (($result['status'] ?? '') === 'needs_input') {
                $task->update(['status' => AiGenerationTask::STATUS_NEEDS_INPUT, 'result' => $result, 'error' => null]);

                return;
            }
            if (! empty($result['pending'])) {
                $payload = $this->payload($task);
                $payload['provider_request_id'] = $result['provider_request_id'];
                $payload['provider'] = $result['provider'];
                $task->update(['status' => 'pending_remote', 'payload' => $payload, 'result' => $result]);
                self::dispatch($this->taskId)->delay(now()->addSeconds(30));

                return;
            }
            if (! empty($result['confirmation_required'])) {
                $error = (string) ($result['error'] ?? 'Se requiere aprobación humana del cambio concreto.');
                $task->update(['status' => AiGenerationTask::STATUS_APPROVAL_REQUIRED, 'result' => $result, 'error' => $error]);
                $this->notifyOwner($task, false, $error);

                return;
            }
            if (isset($result['error'])) {
                $task->update(['status' => ! empty($result['uncertain']) ? 'uncertain' : 'failed', 'result' => $result, 'error' => $result['error']]);
                $this->notifyOwner($task, false, (string) $result['error']);

                return;
            }

            $task->update([
                'status' => AiGenerationTask::STATUS_COMPLETED,
                'result' => $result,
                'error' => null,
            ]);

            $this->notifyOwner($task, true);
        } catch (Throwable $e) {
            $task->update([
                'status' => AiGenerationTask::STATUS_FAILED,
                'error' => 'La generación no pudo completarse.',
            ]);

            $this->notifyOwner($task, false, 'La generación no pudo completarse.');
        }
    }

    public function failed(?Throwable $e): void
    {
        $task = AiGenerationTask::find($this->taskId);

        if ($task === null) {
            return;
        }

        $message = AiTaskPresentation::FAILURE_MESSAGE;

        $task->update([
            'status' => AiGenerationTask::STATUS_FAILED,
            'error' => $message,
        ]);

        $this->notifyOwner($task, false, $message);
    }

    /**
     * Notify the task owner through the backoffice notification centre.
     *
     * A deep link back to where the generation was started (`task=<id>`) lets
     * the UI resume and show the result after navigating away.
     */
    private function notifyOwner(AiGenerationTask $task, bool $success, string $error = ''): void
    {
        if ($task->user_id === null) {
            return;
        }

        $previous = app()->getLocale();
        app()->setLocale((string) ($this->payload($task)['locale'] ?? config('app.locale')));

        try {
            $message = $success
                ? (string) __('admin.ai.notify_ready_message')
                : trim((string) __('admin.ai.notify_failed_message').($error !== '' ? ' — '.$error : ''));

            app(ModuleNotificationServiceInterface::class)->notify(
                module: 'ai_assistant',
                type: $success ? 'success' : 'alert',
                title: (string) ($success ? __('admin.ai.notify_ready_title') : __('admin.ai.notify_failed_title')),
                message: $message,
                actionUrl: $this->resumeUrl($task),
                data: [
                    'ai_task_id' => $task->id,
                    'action' => $task->action,
                    'entity_type' => $this->payload($task)['element_type'] ?? null,
                ],
                sendEmail: false,
                userId: (int) $task->user_id,
            );
        } finally {
            app()->setLocale($previous);
        }
    }

    private function resumeUrl(AiGenerationTask $task): string
    {
        $reviewId = $task->result['execution_id'] ?? null;
        if ($task->status === AiGenerationTask::STATUS_APPROVAL_REQUIRED && is_string($reviewId)) {
            return route('admin.executions.review', $reviewId);
        }
        $returnUrl = (string) ($this->payload($task)['return_url'] ?? '');

        if ($returnUrl === '') {
            $returnUrl = route('admin.ai_assistant.workbench');
        }

        $separator = str_contains($returnUrl, '?') ? '&' : '?';

        return $returnUrl.$separator.'task='.$task->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(AiGenerationTask $task): array
    {
        return is_array($task->payload) ? $task->payload : [];
    }
}

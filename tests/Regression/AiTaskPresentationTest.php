<?php

declare(strict_types=1);

namespace Tests\Regression;

use Modules\AiAssistant\Models\AiGenerationTask;
use Modules\AiAssistant\Services\AiTaskPresentation;
use PHPUnit\Framework\TestCase;

final class AiTaskPresentationTest extends TestCase
{
    public function test_old_worker_exceptions_are_not_exposed_in_status_payloads(): void
    {
        $task = new AiGenerationTask;
        $task->forceFill(['id' => 'safe-task', 'action' => 'block', 'status' => 'failed', 'error' => 'Target [Private\\CredentialService] is not instantiable. secret=private-value']);
        $payload = (new AiTaskPresentation)->payload($task);
        self::assertSame('La generación no pudo completarse.', $payload['error']);
        self::assertSame('generation_failed', $payload['error_code']);
        self::assertTrue($payload['finished']);
        self::assertNull($payload['data']);
        self::assertStringNotContainsString('private-value', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function test_terminal_review_and_uncertain_states_preserve_their_meaning(): void
    {
        $task = new AiGenerationTask;
        $task->forceFill(['id' => 'review-task', 'action' => 'block', 'status' => 'approval_required', 'result' => ['execution_id' => 'execution-1', 'review_url' => '/control/executions/execution-1/review'], 'error' => 'private internal failure']);
        $presentation = new AiTaskPresentation;
        $payload = $presentation->payload($task);
        self::assertSame('approval_required', $payload['error_code']);
        self::assertSame('/control/executions/execution-1/review', $payload['review_url']);
        self::assertTrue($payload['finished']);
        $task->status = 'uncertain';
        self::assertSame('result_uncertain', $presentation->payload($task)['error_code']);
        $task->forceFill(['status' => 'failed', 'result' => ['unavailable' => true, 'error' => 'sensitive details']]);
        self::assertSame('capability_unavailable', $presentation->payload($task)['error_code']);
        self::assertStringNotContainsString('sensitive', $presentation->payload($task)['error']);
    }
}

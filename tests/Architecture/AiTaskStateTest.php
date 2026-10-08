<?php

declare(strict_types=1);

use Modules\AiAssistant\Models\AiGenerationTask;

test('an uncertain or approval blocked generation is terminal for automatic polling', function (): void {
    foreach (['uncertain', 'approval_required'] as $status) {
        expect((new AiGenerationTask(['status' => $status]))->isFinished())->toBeTrue();
    }
    foreach (['pending', 'processing', 'pending_remote'] as $status) {
        expect((new AiGenerationTask(['status' => $status]))->isFinished())->toBeFalse();
    }
});

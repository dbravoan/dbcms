<?php

declare(strict_types=1);

namespace Tests\Regression;

use Modules\AiAssistant\DTOs\GenerationResponse;
use PHPUnit\Framework\TestCase;

final class AiGenerationResponseTest extends TestCase
{
    public function test_clarification_is_not_wrapped_as_success(): void
    {
        $data = ['success' => false, 'status' => 'needs_input', 'question' => 'Which market?', 'message' => 'Which market?'];
        self::assertSame(['success' => false, 'data' => $data, 'status' => 'needs_input', 'question' => 'Which market?', 'message' => 'Which market?'], (new GenerationResponse($data))->toArray());
    }

    public function test_existing_success_and_error_contracts_are_preserved(): void
    {
        $result = ['title' => 'Draft'];
        self::assertSame(['success' => true, 'data' => $result], (new GenerationResponse($result))->toArray());
        $error = ['error' => 'Permission denied', 'denied' => true];
        self::assertSame(['success' => false, 'data' => $error], (new GenerationResponse($error))->toArray());
    }
}

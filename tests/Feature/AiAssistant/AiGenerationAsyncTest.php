<?php

declare(strict_types=1);

use App\Core\Capabilities\ActorContext;
use App\Core\Capabilities\CallableCapability;
use App\Core\Capabilities\CapabilityRegistry;
use App\Core\Modules\ModuleManager;
use App\Models\Orchestration\Execution;
use App\Models\Role;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\AiAssistant\Jobs\ProcessAiGeneration;
use Modules\AiAssistant\Models\AiGenerationTask;
use Modules\AiAssistant\Services\AiTaskService;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['services.gemini.key' => 'fake-gemini-key']);
    Http::preventStrayRequests();
    app(ModuleManager::class)->discoverModules();

    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
    $this->admin = User::factory()->create();
    $this->admin->roles()->syncWithoutDetaching([$role->id]);
});

/**
 * @param  array<string, mixed>  $payload
 */
function makeTask(int $userId, string $action, array $payload): AiGenerationTask
{
    return AiGenerationTask::create([
        'id' => (string) Str::uuid(),
        'user_id' => $userId,
        'action' => $action,
        'payload' => $payload + ['_actor' => (new ActorContext($userId))->toArray()],
        'status' => AiGenerationTask::STATUS_PENDING,
    ]);
}

test('async generation queues a task and returns immediately', function (): void {
    Queue::fake();

    $response = $this->actingAs($this->admin, 'web')->postJson(
        route('admin.ai_assistant.generate_async'),
        ['action' => 'page_element', 'element_type' => 'page', 'prompt' => 'Página de servicios', 'locale' => 'es']
    );

    $response->assertOk()->assertJsonPath('success', true);
    $taskId = (string) $response->json('task_id');
    expect($taskId)->not->toBeEmpty();

    $task = AiGenerationTask::find($taskId);
    expect($task)->not->toBeNull();
    expect($task->status)->toBe(AiGenerationTask::STATUS_PENDING);

    Queue::assertPushed(ProcessAiGeneration::class);
});

test('processing the queued job completes the task with a result', function (): void {
    $task = makeTask($this->admin->id, 'seo', [
        'action' => 'seo',
        'title' => 'Título base',
        'content' => 'Contenido base',
        'locale' => 'es',
    ]);

    (new ProcessAiGeneration($task->id))->handle(app(AiTaskService::class));

    $task->refresh();
    expect($task->status)->toBe(AiGenerationTask::STATUS_COMPLETED);
    expect($task->result)->toBeArray();
    expect($task->result['meta_title'] ?? null)->not->toBeEmpty();
});

test('the status endpoint returns the completed result', function (): void {
    $task = makeTask($this->admin->id, 'seo', [
        'action' => 'seo',
        'title' => 'Título base',
        'content' => 'Contenido base',
        'locale' => 'es',
    ]);

    (new ProcessAiGeneration($task->id))->handle(app(AiTaskService::class));

    $response = $this->actingAs($this->admin, 'web')->getJson(
        route('admin.ai_assistant.generate_status', ['task' => $task->id])
    );

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('status', 'completed');
    $response->assertJsonPath('finished', true);
    expect($response->json('data.meta_title'))->not->toBeEmpty();
});

test('a failing action marks the task as failed with an error', function (): void {
    $task = makeTask($this->admin->id, 'not_a_real_action', ['locale' => 'es']);

    (new ProcessAiGeneration($task->id))->handle(app(AiTaskService::class));

    $task->refresh();
    expect($task->status)->toBe(AiGenerationTask::STATUS_FAILED);
    expect($task->error)->not->toBeEmpty();
});

test('a user cannot read another user generation task', function (): void {
    $other = User::factory()->create();
    $task = makeTask($other->id, 'seo', ['action' => 'seo', 'title' => 'x', 'content' => 'y']);

    $this->actingAs($this->admin, 'web')
        ->getJson(route('admin.ai_assistant.generate_status', ['task' => $task->id]))
        ->assertStatus(404);
});

test('completing a task notifies its owner (per-user, no email)', function (): void {
    $task = makeTask($this->admin->id, 'seo', [
        'action' => 'seo',
        'title' => 'Título base',
        'content' => 'Contenido base',
        'locale' => 'es',
        'return_url' => 'http://localhost/admin/ai-assistant',
    ]);

    (new ProcessAiGeneration($task->id))->handle(app(AiTaskService::class));

    $notification = SystemNotification::query()->latest('id')->first();

    expect($notification)->not->toBeNull();
    expect($notification->user_id)->toBe($this->admin->id)
        ->and($notification->module)->toBe('ai_assistant')
        ->and($notification->type)->toBe('success')
        ->and($notification->action_url)->toContain('task='.$task->id);
});

test('a failed task notifies its owner with an alert', function (): void {
    $task = makeTask($this->admin->id, 'not_a_real_action', ['locale' => 'es']);

    (new ProcessAiGeneration($task->id))->handle(app(AiTaskService::class));

    $notification = SystemNotification::query()->latest('id')->first();

    expect($notification)->not->toBeNull();
    expect($notification->type)->toBe('alert')
        ->and($notification->user_id)->toBe($this->admin->id);
});

test('the notifications centre is scoped per user (plus global ones)', function (): void {
    $other = User::factory()->create();

    foreach ([
        ['Global', null],
        ['Mine', $this->admin->id],
        ['Other', $other->id],
    ] as [$title, $userId]) {
        SystemNotification::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'module' => 'test',
            'type' => 'success',
            'title' => $title,
            'message' => 'msg',
            'is_read' => false,
        ]);
    }

    $response = $this->actingAs($this->admin, 'web')->getJson(route('admin.notifications.index'));

    $response->assertOk();
    $titles = array_column($response->json('notifications'), 'title');

    expect($titles)->toContain('Global', 'Mine')
        ->and($titles)->not->toContain('Other')
        ->and($response->json('unread_count'))->toBe(2);
});

test('the workbench can resume a queued generation after navigation', function (): void {
    $response = $this->actingAs($this->admin, 'web')->get(route('admin.ai_assistant.workbench'));

    $response->assertOk();
    $response->assertSee("localStorage.getItem('ai.workbench.task')", false);
    $response->assertSee('resumePending', false);
});

test('the create-with-ai form can resume a queued generation after navigation', function (): void {
    $response = $this->actingAs($this->admin, 'web')->get(route('admin.pages.create'));

    $response->assertOk();
    $response->assertSee('ai.create.task.', false);
    $response->assertSee('resume(', false);
});

test('an async action needing approval creates a concrete review and stops automatic replay', function (): void {
    $calls = 0;
    app(CapabilityRegistry::class)->register(new CallableCapability('cms_ai_seo', 'Test approval action', [], [], 'use:ai', 'write', function () use (&$calls): array {
        $calls++;

        return ['done' => true];
    }), effect: 'external');
    $task = makeTask($this->admin->id, 'seo', []);
    $job = new ProcessAiGeneration($task->id);
    $job->handle(app(AiTaskService::class));
    $task->refresh();
    expect($task->status)->toBe(AiGenerationTask::STATUS_APPROVAL_REQUIRED);
    expect($task->result['execution_id'])->not->toBeEmpty();
    expect($calls)->toBe(0);
    $review = route('admin.executions.review', $task->result['execution_id']);
    expect(SystemNotification::query()->latest('id')->first()->action_url)->toBe($review);
    $this->actingAs($this->admin)->getJson(route('admin.ai_assistant.generate_status', ['task' => $task->id]))
        ->assertOk()->assertJsonPath('finished', true)->assertJsonPath('review_url', $review)->assertJsonPath('data', null);
    $job->handle(app(AiTaskService::class));
    expect($calls)->toBe(0);
    expect(Execution::query()->count())->toBe(1);
});

<?php

declare(strict_types=1);

use App\Contracts\Services\UpdateExecutionServiceInterface;
use App\Core\Updates\UpdateRunStore;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->updatePrivate = sys_get_temp_dir().'/dbcms-execution-test-'.bin2hex(random_bytes(8));
    mkdir($this->updatePrivate, 0700, true);
    config(['updates.storage_path' => $this->updatePrivate]);
    $this->updateAdmin = User::factory()->create();
    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
    $this->updateAdmin->assignRole($role);
    $this->executionRun = app(UpdateRunStore::class)->create(['actor_id' => $this->updateAdmin->id, 'status' => 'prepared', 'mode' => 'runtime', 'version' => '1.1.0', 'plan_hash' => str_repeat('a', 64), 'conflicts' => [], 'preserved' => [], 'stage_path' => '/private/secret']);
});

afterEach(function (): void {
    File::deleteDirectory($this->updatePrivate);
});

it('rechecks current ownership and does not reveal private run paths', function (): void {
    $execution = app(UpdateExecutionServiceInterface::class);
    $state = $execution->status($this->executionRun['id'], $this->updateAdmin->id);
    expect($state)->not->toHaveKey('stage_path');
    $other = User::factory()->create();
    expect(fn () => $execution->status($this->executionRun['id'], $other->id))->toThrow(AuthorizationException::class);
    $this->updateAdmin->roles()->detach();
    expect(fn () => $execution->status($this->executionRun['id'], $this->updateAdmin->id))->toThrow(AuthorizationException::class);
});

it('rejects service identities even when they carry the administrator role', function (): void {
    $this->updateAdmin->forceFill(['is_service_account' => true])->save();
    expect(fn () => app(UpdateExecutionServiceInterface::class)->status($this->executionRun['id'], $this->updateAdmin->id))->toThrow(AuthorizationException::class);
});

it('does not accept an approval without a recently confirmed browser password', function (): void {
    expect(fn () => app(UpdateExecutionServiceInterface::class)->approve($this->executionRun['id'], $this->updateAdmin->id, $this->executionRun['plan_hash']))->toThrow(AuthorizationException::class);
    expect(app(UpdateRunStore::class)->read($this->executionRun['id'])['status'])->toBe('prepared');
});

it('does not advance an unapproved run and leaves its files untouched', function (): void {
    expect(fn () => app(UpdateExecutionServiceInterface::class)->advance($this->executionRun['id'], $this->updateAdmin->id))->toThrow(DomainException::class);
});

it('cancels only before application has begun', function (): void {
    $store = app(UpdateRunStore::class);
    $execution = app(UpdateExecutionServiceInterface::class);
    $run = $this->executionRun;
    $run['status'] = 'files_applied';
    $store->save($run['id'], $run);
    expect(fn () => $execution->cancel($run['id'], $this->updateAdmin->id))->toThrow(DomainException::class);
    $run['status'] = 'prepared';
    $store->save($run['id'], $run);
    expect($execution->cancel($run['id'], $this->updateAdmin->id)['status'])->toBe('cancelled');
});

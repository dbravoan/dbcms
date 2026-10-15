<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sp_accounts', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('provider');
            $t->string('remote_id');
            $t->string('identity')->unique();
            $t->string('label');
            $t->string('status')->default('unconfigured');
            $t->unsignedInteger('authorization_generation')->default(1);
            $t->text('access_token')->nullable();
            $t->text('refresh_token')->nullable();
            $t->json('grants');
            $t->json('metadata')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->unsignedBigInteger('connected_by')->nullable();
            $t->timestamps();
        });
        Schema::create('sp_publications', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('account_id')->constrained('sp_accounts')->restrictOnDelete();
            $t->uuid('revision_id')->nullable();
            $t->uuid('approved_revision_id')->nullable();
            $t->string('payload_hash', 64)->default('');
            $t->string('installation_id', 64);
            $t->unsignedBigInteger('owner_id');
            $t->json('owner_actor');
            $t->string('status')->default('draft');
            $t->timestamps();
        });
        Schema::create('sp_revisions', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('publication_id')->constrained('sp_publications')->restrictOnDelete();
            $t->unsignedInteger('number');
            $t->json('package');
            $t->json('metadata');
            $t->string('account_identity');
            $t->unsignedInteger('account_generation');
            $t->string('payload_hash', 64);
            $t->timestamps();
            $t->unique(['publication_id', 'number']);
        });
        Schema::create('sp_approvals', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('revision_id')->unique()->constrained('sp_revisions')->restrictOnDelete();
            $t->string('payload_hash', 64);
            $t->unsignedBigInteger('approved_by');
            $t->string('origin');
            $t->string('account_identity');
            $t->unsignedInteger('account_generation');
            $t->timestamps();
        });
        Schema::create('sp_deliveries', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('publication_id')->constrained('sp_publications')->restrictOnDelete();
            $t->foreignUuid('revision_id')->constrained('sp_revisions')->restrictOnDelete();
            $t->string('installation_id', 64);
            $t->string('status')->default('scheduled');
            $t->string('step')->default('prepare');
            $t->boolean('cancel_requested')->default(false);
            $t->uuid('claim')->nullable();
            $t->timestamp('lease_until')->nullable();
            $t->timestamp('due_at')->index();
            $t->timestamp('scheduled_at')->nullable();
            $t->text('receipt')->nullable();
            $t->json('scheduler_actor');
            $t->string('remote_id')->nullable();
            $t->string('error_code')->nullable();
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamps();
            $t->unique('revision_id');
            $t->index(['status', 'due_at']);
        });
        Schema::create('sp_attempts', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('delivery_id')->constrained('sp_deliveries')->restrictOnDelete();
            $t->string('operation_key')->unique();
            $t->string('step');
            $t->string('status');
            $t->boolean('mutation');
            $t->text('receipt')->nullable();
            $t->string('error_code')->nullable();
            $t->timestamps();
        });
        Schema::create('sp_metrics', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->foreignUuid('delivery_id')->constrained('sp_deliveries')->restrictOnDelete();
            $t->json('metrics');
            $t->string('source');
            $t->string('availability');
            $t->timestamp('observed_at');
            $t->timestamps();
        });
        Schema::create('sp_oauth_states', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('state_hash', 64)->unique();
            $t->string('provider');
            $t->unsignedBigInteger('user_id');
            $t->string('session_hash', 64);
            $t->text('redirect_uri');
            $t->text('verifier');
            $t->text('discovery')->nullable();
            $t->timestamp('expires_at');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['sp_oauth_states', 'sp_metrics', 'sp_attempts', 'sp_deliveries', 'sp_approvals', 'sp_revisions', 'sp_publications', 'sp_accounts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

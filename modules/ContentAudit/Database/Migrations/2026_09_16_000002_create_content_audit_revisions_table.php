<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('content_audit_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_audit_id')->nullable()->constrained('content_audits')->cascadeOnDelete();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('action', 40); // created, updated, submitted, approved, rejected, published, unpublished, rollback
            $table->string('status_before', 30)->nullable();
            $table->string('status_after', 30)->nullable();
            $table->string('title', 255);
            $table->text('summary')->nullable();
            $table->json('snapshot'); // Full json representation of the entity and translations
            $table->json('diff')->nullable(); // Structured diff array {field: {before: ..., after: ...}}
            $table->text('notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id', 'version'], 'content_audit_rev_entity_ver_idx');
            $table->index(['user_id', 'created_at'], 'content_audit_rev_user_idx');
            $table->index(['action', 'created_at'], 'content_audit_rev_action_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_audit_revisions');
    }
};

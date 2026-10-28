<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The changelog orders the global revision feed by created_at. Without this
     * index MySQL filesorts every matching row, including the snapshot/diff
     * JSON columns, which overflows sort_buffer_size on real content sizes.
     */
    public function up(): void
    {
        Schema::table('content_audit_revisions', function (Blueprint $table): void {
            $table->index('created_at', 'content_audit_rev_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('content_audit_revisions', function (Blueprint $table): void {
            $table->dropIndex('content_audit_rev_created_idx');
        });
    }
};

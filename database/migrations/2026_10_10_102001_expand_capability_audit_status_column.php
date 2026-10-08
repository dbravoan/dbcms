<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mcp_audit_logs')) {
            Schema::table('mcp_audit_logs', function (Blueprint $table): void {
                $table->string('status', 32)->default('ok')->change();
            });
        }
    }

    public function down(): void
    {
        // Retain the wider column: shrinking would discard recorded confirmation states.
    }
};

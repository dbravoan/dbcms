<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sp_publications') && ! Schema::hasColumn('sp_publications', 'owner_actor')) {
            Schema::table('sp_publications', fn (Blueprint $table) => $table->json('owner_actor')->nullable());
        }
        if (Schema::hasTable('sp_deliveries') && ! Schema::hasColumn('sp_deliveries', 'scheduler_actor')) {
            Schema::table('sp_deliveries', fn (Blueprint $table) => $table->json('scheduler_actor')->nullable());
        }
        if (Schema::hasTable('sp_deliveries') && ! Schema::hasColumn('sp_deliveries', 'scheduled_at')) {
            Schema::table('sp_deliveries', fn (Blueprint $table) => $table->timestamp('scheduled_at')->nullable());
        }
    }

    public function down(): void
    {
        // Keep authorization evidence on rollback; stripping it could replay old work.
    }
};

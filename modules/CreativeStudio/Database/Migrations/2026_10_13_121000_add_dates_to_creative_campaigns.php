<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creative_campaigns', function (Blueprint $table): void {
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('creative_campaigns', function (Blueprint $table): void {
            $table->dropColumn(['starts_at', 'ends_at']);
        });
    }
};

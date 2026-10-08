<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('markets', function (Blueprint $table): void {
            // Emoji assigned in the backoffice (a country flag, a supranational
            // flag or the globe for international markets).
            $table->string('flag', 16)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('markets', function (Blueprint $table): void {
            $table->dropColumn('flag');
        });
    }
};

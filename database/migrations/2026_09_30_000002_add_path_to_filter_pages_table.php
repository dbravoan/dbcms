<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('filter_pages', function (Blueprint $table): void {
            // Canonical friendly filter path of the curated landing (e.g.
            // `etiqueta/laravel/orden/recientes`), used by the sitemap and
            // llms.txt to publish the page without re-parsing the signature.
            $table->string('path', 191)->nullable()->after('signature');
        });
    }

    public function down(): void
    {
        Schema::table('filter_pages', function (Blueprint $table): void {
            $table->dropColumn('path');
        });
    }
};

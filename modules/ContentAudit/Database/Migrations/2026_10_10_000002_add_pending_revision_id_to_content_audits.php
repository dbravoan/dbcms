<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_audits', static function (Blueprint $table): void {
            $table->unsignedBigInteger('pending_revision_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('content_audits', static function (Blueprint $table): void {
            $table->dropColumn('pending_revision_id');
        });
    }
};

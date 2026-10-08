<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_service_account')->default(false);
        });
        Schema::create('oauth_service_accounts', function (Blueprint $table): void {
            $table->uuid('client_id')->primary();
            $table->foreign('client_id')->references('id')->on('oauth_clients')->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->json('allowed_scopes');
            $table->timestamps();
        });
        // Existing unbound clients deliberately remain disabled at the CMS boundary.
        // Do not infer their owner from a numeric conversion or the shared legacy email.
        DB::table('users')->where('email', 'mcp-agent@db-cms.io')->update(['is_service_account' => true]);
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_service_accounts');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_service_account');
        });
    }
};

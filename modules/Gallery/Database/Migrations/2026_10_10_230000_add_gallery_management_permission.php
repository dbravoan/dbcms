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
        if (! Schema::hasColumn('gallery_translations', 'schema_type')) {
            Schema::table('gallery_translations', function (Blueprint $table): void {
                $table->string('schema_type', 100)->nullable();
            });
        }
        DB::table('permissions')->insertOrIgnore(['slug' => 'manage:galleries', 'name' => 'Gestionar galerías', 'created_at' => now(), 'updated_at' => now()]);
        $gallery = DB::table('permissions')->where('slug', 'manage:galleries')->value('id');
        $media = DB::table('permissions')->where('slug', 'manage:media')->value('id');
        if ($gallery === null || $media === null) {
            return;
        }
        foreach (DB::table('permission_role')->where('permission_id', $media)->pluck('role_id') as $role) {
            DB::table('permission_role')->insertOrIgnore(['role_id' => $role, 'permission_id' => $gallery]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('gallery_translations', 'schema_type')) {
            Schema::table('gallery_translations', function (Blueprint $table): void {
                $table->dropColumn('schema_type');
            });
        }
        $permission = DB::table('permissions')->where('slug', 'manage:galleries')->value('id');
        if ($permission !== null) {
            DB::table('permission_role')->where('permission_id', $permission)->delete();
            DB::table('permissions')->where('id', $permission)->delete();
        }
    }
};

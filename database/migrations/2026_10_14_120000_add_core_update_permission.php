<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(['slug' => 'manage:updates'], ['name' => 'Administrar actualizaciones del núcleo']);
        $permission = DB::table('permissions')->where('slug', 'manage:updates')->value('id');
        foreach (DB::table('roles')->whereIn('slug', ['admin', 'super-admin'])->pluck('id') as $role) {
            DB::table('permission_role')->updateOrInsert(['permission_id' => $permission, 'role_id' => $role]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->where('slug', 'manage:updates')->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};

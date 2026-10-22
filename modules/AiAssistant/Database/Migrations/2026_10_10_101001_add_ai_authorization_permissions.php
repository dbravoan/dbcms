<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['use:ai' => 'Utilizar las capacidades IA autorizadas', 'manage:ai' => 'Administrar proveedores y configuración IA'] as $slug => $name) {
            DB::table('permissions')->updateOrInsert(['slug' => $slug], ['name' => $name]);
            $permission = DB::table('permissions')->where('slug', $slug)->value('id');
            $roles = $slug === 'use:ai' ? ['admin', 'editor', 'author', 'marketing'] : ['admin'];
            foreach (DB::table('roles')->whereIn('slug', $roles)->pluck('id') as $role) {
                DB::table('permission_role')->updateOrInsert(['permission_id' => $permission, 'role_id' => $role]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('slug', ['use:ai', 'manage:ai'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};

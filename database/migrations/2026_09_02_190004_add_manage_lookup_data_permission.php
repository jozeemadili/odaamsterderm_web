<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'manage-lookup-data', 'guard_name' => 'web']);

        $superAdmin = Role::where('name', 'super_admin')->where('guard_name', 'web')->first();
        $superAdmin?->givePermissionTo($permission);
    }

    public function down(): void
    {
        Permission::where('name', 'manage-lookup-data')->where('guard_name', 'web')->first()?->delete();
    }
};

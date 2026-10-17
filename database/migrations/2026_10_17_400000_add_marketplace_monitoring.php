<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            // Why the platform suspended this store (shown to the seller).
            $table->text('suspension_reason')->nullable()->after('status');
        });

        $permission = Permission::firstOrCreate(['name' => 'monitor-marketplace', 'guard_name' => 'web']);

        foreach (['super_admin', 'risk_compliance_officer', 'support_agent'] as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        Permission::where('name', 'monitor-marketplace')->where('guard_name', 'web')->first()?->delete();

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('suspension_reason');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ai_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('feature', 30);
            $table->string('query', 500);
            $table->string('model', 60)->nullable();
            $table->string('status', 20);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedSmallInteger('results_count')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('error', 255)->nullable();
            $table->timestamps();

            $table->index(['feature', 'created_at']);
        });

        $permission = Permission::firstOrCreate(['name' => 'manage-platform-settings', 'guard_name' => 'web']);
        Role::where('name', 'super_admin')->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'manage-platform-settings')->where('guard_name', 'web')->first()?->delete();
        Schema::dropIfExists('ai_requests');
        Schema::dropIfExists('platform_settings');
    }
};

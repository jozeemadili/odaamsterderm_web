<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('location_label');
            // False for accounts made through Google: they never chose a
            // password, so setting one must not ask for the current one.
            $table->boolean('has_password')->default(true)->after('avatar_path');
        });

        DB::table('users')->whereNotNull('google_id')->update(['has_password' => false]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'has_password']);
        });
    }
};

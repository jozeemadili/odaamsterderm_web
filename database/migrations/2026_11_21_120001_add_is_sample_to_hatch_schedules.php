<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sample hatch dates (for demos) can be told apart and removed in one go. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hatch_schedules', function (Blueprint $table) {
            $table->boolean('is_sample')->default(false)->after('is_closed');
        });
    }

    public function down(): void
    {
        Schema::table('hatch_schedules', fn (Blueprint $table) => $table->dropColumn('is_sample'));
    }
};

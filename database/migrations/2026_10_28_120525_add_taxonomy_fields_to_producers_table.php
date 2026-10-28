<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Producers double as brands (the name on the pack). The workbook code
 * (BR-SIL) keeps imports idempotent; is_demo marks fictional test makers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producers', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('id');
            $table->string('role')->nullable()->after('country'); // Manufacturer, Hatchery, Supplier…
            $table->boolean('is_demo')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('producers', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'role', 'is_demo']);
        });
    }
};

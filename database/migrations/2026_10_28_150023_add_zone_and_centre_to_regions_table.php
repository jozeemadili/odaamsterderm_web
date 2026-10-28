<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Region centre point (to place the map before a store is chosen) and zone. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->string('zone')->nullable()->after('code'); // Coastal, Lake Zone, Southern Highlands…
            $table->decimal('latitude', 10, 7)->nullable()->after('zone');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->dropColumn(['zone', 'latitude', 'longitude']);
        });
    }
};

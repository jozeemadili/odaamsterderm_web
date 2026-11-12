<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether customers see this producer's products on the marketplace. Off
 * keeps them in the catalogue and in stores' stock, just out of sight.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producers', function (Blueprint $table) {
            $table->boolean('on_marketplace')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('producers', function (Blueprint $table) {
            $table->dropColumn('on_marketplace');
        });
    }
};

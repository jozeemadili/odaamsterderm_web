<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->foreignId('business_type_id')->nullable()->after('business_type')
                ->constrained('business_types')->nullOnDelete();
            $table->foreignId('region_id')->nullable()->after('region')
                ->constrained('regions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_type_id');
            $table->dropConstrainedForeignId('region_id');
        });
    }
};

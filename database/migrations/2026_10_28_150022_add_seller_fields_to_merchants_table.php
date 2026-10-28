<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seller details from the workbook's Sellers tab. The code (SEL-AMS) keeps
 * imports idempotent; the Selcom merchant id is where split payments
 * (e.g. chick bookings with Oda's commission) are paid out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('id');
            $table->string('legal_name')->nullable()->after('business_name');
            $table->string('website')->nullable()->after('email');
            $table->string('selcom_merchant_id', 64)->nullable()->after('tin_number');
        });

        $now = now();
        DB::table('business_types')->insertOrIgnore(collect(['Agrovet', 'Hatchery'])->map(fn ($name) => [
            'name' => $name,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'legal_name', 'website', 'selcom_merchant_id']);
        });
    }
};

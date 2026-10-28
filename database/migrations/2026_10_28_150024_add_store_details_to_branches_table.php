<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store details from the workbook's Stores tab: delivery terms, what the
 * store can handle (cold chain, vet advice) and a bio in English and
 * Kiswahili. The code (ST-AMS-TEG) keeps imports idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('id');
            $table->string('short_code', 10)->nullable()->after('code'); // TEG, printed on receipts and labels
            $table->string('store_type')->nullable()->after('name'); // Flagship + warehouse, Regional branch…
            $table->string('area')->nullable()->after('city');
            $table->string('landmark')->nullable()->after('area');
            $table->string('whatsapp', 30)->nullable()->after('phone');
            $table->string('manager_name')->nullable()->after('email');

            // No radius means pickup only.
            $table->decimal('delivery_radius_km', 6, 1)->nullable()->after('opening_hours');
            $table->decimal('delivery_base_fee', 12, 2)->nullable()->after('delivery_radius_km');
            $table->decimal('delivery_fee_per_km', 12, 2)->nullable()->after('delivery_base_fee');
            $table->boolean('pickup_available')->default(true)->after('delivery_fee_per_km');
            $table->boolean('cold_chain_capable')->default(false)->after('pickup_available'); // monitored 2–8 °C fridge
            $table->boolean('vet_advice_on_site')->default(false)->after('cold_chain_capable');

            $table->text('bio')->nullable()->after('vet_advice_on_site');
            $table->text('bio_sw')->nullable()->after('bio');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn([
                'code', 'short_code', 'store_type', 'area', 'landmark', 'whatsapp', 'manager_name',
                'delivery_radius_km', 'delivery_base_fee', 'delivery_fee_per_km',
                'pickup_available', 'cold_chain_capable', 'vet_advice_on_site', 'bio', 'bio_sw',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product attributes from the taxonomy workbook. Fields used by sale rules
 * or filters are real columns; multi-select tags are JSON lists of
 * product_attribute_values codes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('id');
            $table->string('regulatory_class')->nullable()->after('pack_size');
            $table->boolean('prescription_required')->default(false)->after('regulatory_class');
            $table->boolean('cold_chain_required')->default(false)->after('prescription_required');
            $table->json('production_purpose')->nullable();
            $table->json('use_cases')->nullable();
            $table->json('disease_target')->nullable();
            $table->json('administration_route')->nullable();
            $table->string('form')->nullable();
            $table->string('active_ingredients', 500)->nullable();
            $table->decimal('crude_protein_pct', 5, 2)->nullable();
            $table->string('inclusion_or_dosage', 500)->nullable();
            $table->unsignedSmallInteger('withdrawal_meat_days')->nullable();
            $table->unsignedSmallInteger('withdrawal_eggs_days')->nullable();
            $table->unsignedSmallInteger('withdrawal_milk_days')->nullable();
            $table->string('storage')->nullable();
            $table->string('registration_no')->nullable();
            $table->string('capacity')->nullable();
            $table->string('power_source')->nullable();
            $table->string('breed_strain')->nullable();
            $table->string('country_of_origin')->nullable();
            $table->text('search_keywords')->nullable(); // pipe-separated, Kiswahili first
            $table->boolean('is_demo')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn([
                'code', 'regulatory_class', 'prescription_required', 'cold_chain_required',
                'production_purpose', 'use_cases', 'disease_target', 'administration_route', 'form',
                'active_ingredients', 'crude_protein_pct', 'inclusion_or_dosage',
                'withdrawal_meat_days', 'withdrawal_eggs_days', 'withdrawal_milk_days',
                'storage', 'registration_no', 'capacity', 'power_source', 'breed_strain',
                'country_of_origin', 'search_keywords', 'is_demo',
            ]);
        });
    }
};

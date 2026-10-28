<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The attribute dictionary (every product, variant and stock field, its type
 * and which categories need it) and the allowed values for the list fields
 * (form, route, regulatory class…), in English and Kiswahili.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('label_sw')->nullable();
            $table->string('level', 30); // product, variant, store_inventory, hatch_schedule
            $table->string('data_type', 30); // enum, text, number, integer, boolean, date, fk
            $table->boolean('is_multi_select')->default(false);
            $table->string('required_for')->nullable(); // "ALL", "C1010, C1020, C70"…
            $table->string('values_source')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_attribute_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('label');
            $table->string('label_sw')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['product_attribute_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_attributes');
    }
};

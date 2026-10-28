<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The hand-kept list of which categories each animal may use goes stale as
 * the catalogue grows. Categories shown for an animal are now worked out
 * from the products tagged with that animal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('animal_type_product_category');
    }

    public function down(): void
    {
        Schema::create('animal_type_product_category', function (Blueprint $table) {
            $table->foreignId('animal_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['animal_type_id', 'product_category_id']);
        });
    }
};

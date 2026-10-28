<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every animal a product's label covers (Albendazole = Cattle + Goats +
 * Sheep). products.animal_type_id stays as the main animal for display;
 * filters use this list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animal_type_product', function (Blueprint $table) {
            $table->foreignId('animal_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['animal_type_id', 'product_id']);
        });

        DB::table('animal_type_product')->insertUsing(
            ['animal_type_id', 'product_id'],
            DB::table('products')->select('animal_type_id', 'id'),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_type_product');
    }
};

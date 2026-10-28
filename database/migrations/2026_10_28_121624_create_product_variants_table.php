<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pack sizes of a catalog product (Vitamix Chick 30 g / 100 g / 250 g): one
 * product, many SKUs. Sellers stock a variant, so offers compare like with
 * like. Each existing product gets a variant from its pack size.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 40)->nullable()->unique(); // workbook SKU, e.g. P001-25KG
            $table->string('name'); // "25 kg bag", "100 doses"
            $table->decimal('pack_size_value', 12, 3)->nullable();
            $table->string('pack_size_unit', 20)->nullable(); // kg, g, ml, L, doses, pcs, birds…
            $table->string('selling_unit', 20); // bag, bottle, vial, bird…
            $table->decimal('net_weight_kg', 10, 3)->nullable(); // shipping weight
            $table->string('gtin', 20)->nullable();
            $table->decimal('reference_price', 14, 2)->nullable(); // suggested price; stores set their own
            $table->unsignedInteger('min_order_qty')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('products')->whereNotNull('pack_size')->orderBy('id')->each(function ($product) use ($now) {
            DB::table('product_variants')->insert([
                'product_id' => $product->id,
                'name' => $product->pack_size,
                'selling_unit' => $product->unit,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Shared product catalog. Sellers stock and price these per store
        // (inventory_items.product_id) so customers can compare sellers.
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('animal_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_category_id')->constrained()->restrictOnDelete();
            $table->string('brand')->nullable();
            $table->text('description')->nullable();
            $table->string('unit'); // bag, kg, bottle, sachet, box, piece...
            $table->string('pack_size')->nullable(); // e.g. 25KG, 100ml, 1000 doses

            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->foreignId('proposed_by_merchant_id')->nullable()->constrained('merchants')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'animal_type_id', 'product_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Platform-wide, admin-managed product categories (Feed > Starter,
        // Vaccines, ...). Separate from the per-merchant inventory_categories
        // that sellers use to group their own shelves.
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Which categories apply to which animal types (fish have no
        // vaccines category, for example). An empty set means "all".
        Schema::create('animal_type_product_category', function (Blueprint $table) {
            $table->foreignId('animal_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['animal_type_id', 'product_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_type_product_category');
        Schema::dropIfExists('product_categories');
    }
};

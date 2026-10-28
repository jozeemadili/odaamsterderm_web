<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Life stages (starter, grower, lactating…) belong to an animal group, so a
 * pig grower and a chicken grower are different stages. A product can
 * cover several stages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('life_stages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('animal_type_id')->nullable()->constrained()->cascadeOnDelete(); // null = every animal
            $table->string('slug');
            $table->string('name');
            $table->string('name_sw')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('life_stage_product', function (Blueprint $table) {
            $table->foreignId('life_stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['life_stage_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('life_stage_product');
        Schema::dropIfExists('life_stages');
    }
};

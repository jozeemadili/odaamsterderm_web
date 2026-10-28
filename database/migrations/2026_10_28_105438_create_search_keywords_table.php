<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kiswahili/English search dictionary: "vifaranga" points at the day-old
 * chicks category, "kideri" at the Newcastle disease value, and so on.
 * target_code holds the taxonomy code (AN-CHK, C7010, newcastle…).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_keywords', function (Blueprint $table) {
            $table->id();
            $table->string('term');
            $table->string('language', 5)->default('sw');
            $table->string('target_type', 40); // animal, category, life_stage, use_case, disease, production_purpose, product_term, category+animal
            $table->string('target_code');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['term', 'target_type', 'target_code']);
            $table->index('term');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_keywords');
    }
};

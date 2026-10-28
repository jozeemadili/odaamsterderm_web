<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taxonomy workbook fields: a stable code (AN-CHK, C1010) for imports,
 * the Kiswahili name, and the guidance text that tells sellers which
 * category a product belongs in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animal_types', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('id');
            $table->string('name_sw')->nullable()->after('name');
            $table->text('description')->nullable()->after('slug');
        });

        Schema::table('product_categories', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('id');
            $table->string('name_sw')->nullable()->after('name');
            $table->text('description')->nullable()->after('slug');
            $table->text('tie_break_rule')->nullable()->after('description');
            $table->json('required_attributes')->nullable()->after('tie_break_rule');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'name_sw', 'description', 'tie_break_rule', 'required_attributes']);
        });

        Schema::table('animal_types', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'name_sw', 'description']);
        });
    }
};

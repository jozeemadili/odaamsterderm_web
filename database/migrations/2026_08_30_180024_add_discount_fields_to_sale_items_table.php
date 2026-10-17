<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('gross_amount', 14, 2)->nullable()->after('unit_price');
            // quantity * unit_price, before discount
            $table->string('discount_type')->nullable()->after('gross_amount');
            // percent, fixed
            $table->decimal('discount_value', 14, 2)->nullable()->after('discount_type');
            $table->decimal('discount_amount', 14, 2)->default(0)->after('discount_value');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['gross_amount', 'discount_type', 'discount_value', 'discount_amount']);
        });
    }
};

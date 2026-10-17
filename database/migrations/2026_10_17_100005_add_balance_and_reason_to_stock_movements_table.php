<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('reason')->nullable()->after('reference');
            $table->decimal('balance_after', 14, 2)->nullable()->after('quantity');
            $table->decimal('marketplace_quantity_after', 14, 2)->nullable()->after('balance_after');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn(['reason', 'balance_after', 'marketplace_quantity_after']);
        });
    }
};

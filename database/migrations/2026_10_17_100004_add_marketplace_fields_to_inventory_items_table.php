<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->boolean('is_marketplace_visible')->default(false)->after('unit_price');
            // Units exposed for online purchase; never more than the stock
            // that is not already reserved for open orders.
            $table->decimal('marketplace_quantity', 14, 2)->default(0)->after('is_marketplace_visible');
            // Units held by open marketplace orders (still part of
            // quantity_on_hand until the order is completed).
            $table->decimal('reserved_quantity', 14, 2)->default(0)->after('marketplace_quantity');
            $table->string('marketplace_status')->default('inactive')->after('reserved_quantity'); // active, out_of_stock, inactive

            $table->index(['product_id', 'is_marketplace_visible', 'marketplace_status'], 'inventory_items_marketplace_index');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropIndex('inventory_items_marketplace_index');
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn(['is_marketplace_visible', 'marketplace_quantity', 'reserved_quantity', 'marketplace_status']);
        });
    }
};

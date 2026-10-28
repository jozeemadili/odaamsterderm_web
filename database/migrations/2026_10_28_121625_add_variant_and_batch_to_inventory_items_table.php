<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stock is held per variant (pack size). One batch per stock row for now:
 * batch_no and expiry_date describe the batch on the shelf. Existing stock
 * moves to its product's only variant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->string('batch_no', 64)->nullable()->after('expiry_date');
        });

        DB::table('product_variants')
            ->select('product_id', DB::raw('MIN(id) as id'))
            ->groupBy('product_id')
            ->havingRaw('COUNT(*) = 1')
            ->get()
            ->each(fn ($variant) => DB::table('inventory_items')
                ->where('product_id', $variant->product_id)
                ->update(['product_variant_id' => $variant->id]));
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropColumn('batch_no');
        });
    }
};

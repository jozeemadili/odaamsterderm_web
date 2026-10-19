<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Stock is received into one store; existing receipts go to the main store. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_receipts', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('merchant_id')->constrained()->nullOnDelete();
        });

        $mainStore = DB::table('branches')
            ->select('merchant_id', DB::raw('MIN(CASE WHEN is_primary = 1 THEN id END) as primary_id'), DB::raw('MIN(id) as first_id'))
            ->groupBy('merchant_id')->get();
        foreach ($mainStore as $r) {
            DB::table('stock_receipts')->where('merchant_id', $r->merchant_id)->whereNull('branch_id')
                ->update(['branch_id' => $r->primary_id ?? $r->first_id]);
        }
    }

    public function down(): void
    {
        Schema::table('stock_receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};

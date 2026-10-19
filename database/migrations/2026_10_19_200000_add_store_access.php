<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff store access: each seller staff member works in selected stores
 * (branch_user) or in all of the business's stores (users.all_branches).
 * Existing staff keep seeing everything; records saved before stores were
 * tracked go to the business's main store.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_user', function (Blueprint $table) {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['branch_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('all_branches')->default(false)->after('merchant_id');
        });

        DB::table('users')->whereNotNull('merchant_id')->update(['all_branches' => true]);

        $mainStore = DB::table('branches')
            ->select('merchant_id', DB::raw('MIN(CASE WHEN is_primary = 1 THEN id END) as primary_id'), DB::raw('MIN(id) as first_id'))
            ->groupBy('merchant_id')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->merchant_id => $r->primary_id ?? $r->first_id]);

        foreach (['sales_records', 'expenses', 'invoices', 'payment_records', 'damage_reports'] as $table) {
            if (! Schema::hasColumn($table, 'branch_id')) {
                continue;
            }
            foreach ($mainStore as $merchantId => $branchId) {
                DB::table($table)->where('merchant_id', $merchantId)->whereNull('branch_id')->update(['branch_id' => $branchId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('all_branches');
        });
        Schema::dropIfExists('branch_user');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_records', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->decimal('subtotal', 14, 2)->nullable()->after('amount');
            // sum of line items before the overall sale discount
            $table->string('discount_type')->nullable()->after('subtotal');
            // percent, fixed
            $table->decimal('discount_value', 14, 2)->nullable()->after('discount_type');
            $table->decimal('discount_amount', 14, 2)->default(0)->after('discount_value');
            $table->foreignId('discount_approved_by')->nullable()->after('discount_amount')->constrained('users')->nullOnDelete();
            $table->string('payment_method')->default('cash')->after('discount_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('sales_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropConstrainedForeignId('discount_approved_by');
            $table->dropColumn(['subtotal', 'discount_type', 'discount_value', 'discount_amount', 'payment_method']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A marketplace order: one customer, one seller, one pickup store.
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique(); // OH-2610-4F7K2
            $table->foreignId('user_id')->constrained()->restrictOnDelete(); // customer
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete(); // pickup store

            // pending, accepted, preparing, ready_for_pickup, completed, rejected, cancelled
            $table->string('status')->default('pending');
            $table->decimal('total', 14, 2)->default(0);
            $table->unsignedInteger('items_count')->default(0);

            // Snapshot of who to hand it to, in case the profile changes.
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->text('customer_note')->nullable();
            $table->text('status_reason')->nullable(); // why rejected/cancelled

            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('closed_at')->nullable(); // rejected or cancelled
            $table->timestamps();

            $table->index(['merchant_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('unit')->nullable();
            $table->decimal('unit_price', 14, 2);
            $table->decimal('quantity', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });

        // Status timeline shown to customer and seller, and an audit trail.
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('sales_records', function (Blueprint $table) {
            $table->string('source')->default('pos')->after('merchant_id'); // pos, invoice, online
            $table->foreignId('order_id')->nullable()->after('source')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn('source');
        });
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A product an agent stocks but can't find in the catalogue: what they call
 * it, the producer and pack size, and photos of the front and back label.
 * The data team builds the catalogue product from the label (with an AI
 * draft) or links it to an existing one; the agent's stock links on approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->foreignId('producer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('producer_name')->nullable(); // when the producer isn't listed
            $table->string('pack_size', 100)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete(); // their stock, linked on approval
            $table->string('status', 20)->default('pending'); // pending, approved, linked, rejected
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->json('ai_draft')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_notes', 1000)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_requests');
    }
};

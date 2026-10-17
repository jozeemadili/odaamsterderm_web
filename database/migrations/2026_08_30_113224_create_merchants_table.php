<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('business_name');
            $table->string('owner_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('business_type')->nullable();
            $table->string('tin_number')->nullable();
            $table->string('physical_address')->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();

            $table->string('kyc_status')->default('pending');
            // pending, under_review, approved, rejected

            $table->string('subscription_tier')->default('basic');
            // basic, business, professional

            $table->string('status')->default('active');
            // active, suspended

            $table->foreignId('created_by_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();

            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('merchant_id')->references('id')->on('merchants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['merchant_id']);
        });

        Schema::dropIfExists('merchants');
    }
};

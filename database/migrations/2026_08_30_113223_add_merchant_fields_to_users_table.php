<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('user_type')->default('internal')->after('email');
            $table->unsignedBigInteger('merchant_id')->nullable()->after('user_type');
            $table->string('phone')->nullable()->after('merchant_id');
            $table->string('status')->default('active')->after('phone');
            $table->index('merchant_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['user_type', 'merchant_id', 'phone', 'status']);
        });
    }
};

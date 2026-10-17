<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->decimal('max_percent', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['merchant_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_limits');
    }
};

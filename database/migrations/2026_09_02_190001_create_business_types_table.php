<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('business_types')->insert(collect([
            'Duka / Retail Shop',
            'Pharmacy',
            'Restaurant / Café',
            'Salon & Beauty',
            'Hardware',
            'Electronics',
            'Grocery / Supermarket',
            'Boutique / Clothing',
            'Other',
        ])->map(fn ($name) => [
            'name' => $name,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('business_types');
    }
};

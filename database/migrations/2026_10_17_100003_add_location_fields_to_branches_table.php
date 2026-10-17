<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('email')->nullable()->after('phone');
            $table->foreignId('region_id')->nullable()->after('address')->constrained()->nullOnDelete();
            $table->string('city')->nullable()->after('region_id');
            $table->decimal('latitude', 10, 7)->nullable()->after('city');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->text('pickup_instructions')->nullable()->after('longitude');
            // {"mon": {"open": "08:00", "close": "18:00"}, "sun": null, ...}
            $table->json('opening_hours')->nullable()->after('pickup_instructions');
            // Seller's own switch: list this store on the marketplace or not.
            $table->boolean('is_marketplace_enabled')->default(true)->after('opening_hours');
            // Admin-controlled: active or suspended.
            $table->string('status')->default('active')->after('is_marketplace_enabled');

            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropIndex(['latitude', 'longitude']);
            $table->dropConstrainedForeignId('region_id');
            $table->dropColumn(['email', 'city', 'latitude', 'longitude', 'pickup_instructions', 'opening_hours', 'is_marketplace_enabled', 'status']);
        });
    }
};

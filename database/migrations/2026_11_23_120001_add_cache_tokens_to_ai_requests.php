<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cached prompt tokens are billed differently from fresh input (reads at a
 * small fraction, writes at a premium), so they are kept apart for an
 * accurate cost. Older rows counted them inside input_tokens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_requests', function (Blueprint $table) {
            $table->unsignedInteger('cache_read_tokens')->default(0)->after('output_tokens');
            $table->unsignedInteger('cache_write_tokens')->default(0)->after('cache_read_tokens');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_requests', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropColumn(['cache_read_tokens', 'cache_write_tokens']);
        });
    }
};

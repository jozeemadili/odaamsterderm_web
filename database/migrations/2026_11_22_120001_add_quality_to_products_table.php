<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // 0–100: share of required + recommended fields filled for its category.
            $table->unsignedTinyInteger('completeness')->default(0)->after('status');
            // How many required fields are still empty (0 = ready to approve).
            $table->unsignedTinyInteger('missing_required')->default(0)->after('completeness');
            // Checked against the real label or the producer's own data.
            $table->timestamp('verified_at')->nullable()->after('missing_required');
            $table->foreignId('verified_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            $table->string('verified_source', 20)->nullable()->after('verified_by');
            $table->index(['status', 'completeness']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['status', 'completeness']);
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['completeness', 'missing_required', 'verified_at', 'verified_source']);
        });
    }
};

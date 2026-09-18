<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('balance_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('balance_transactions', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('status')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('balance_transactions', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('balance_transactions', 'rejection_reason')) {
                $table->string('rejection_reason')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('balance_transactions', 'reference_code')) {
                $table->string('reference_code')->nullable()->unique()->after('rejection_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('balance_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('balance_transactions', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }
            foreach (['approved_at', 'rejection_reason', 'reference_code'] as $col) {
                if (Schema::hasColumn('balance_transactions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

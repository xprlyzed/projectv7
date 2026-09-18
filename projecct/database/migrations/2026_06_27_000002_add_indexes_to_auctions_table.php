<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->index(['status', 'ends_at'], 'auctions_status_ends_at_idx');
            $table->index('created_at', 'auctions_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropIndex('auctions_status_ends_at_idx');
            $table->dropIndex('auctions_created_at_idx');
        });
    }
};

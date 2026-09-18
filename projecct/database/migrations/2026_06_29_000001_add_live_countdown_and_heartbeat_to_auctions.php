<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            // Sunucu-otoriter canlı satış geri sayımı (Görev 1 & 2)
            $table->timestamp('countdown_started_at')->nullable()->after('live_ended_at');
            $table->timestamp('countdown_ends_at')->nullable()->after('countdown_started_at');
            $table->unsignedBigInteger('countdown_bid_id')->nullable()->after('countdown_ends_at');
            $table->timestamp('countdown_cancelled_at')->nullable()->after('countdown_bid_id');

            // Canlı yayın heartbeat (Görev 3)
            $table->timestamp('last_heartbeat_at')->nullable()->after('countdown_cancelled_at');

            // "Bitmek üzere" bildirimleri tekilleştirme (Görev 4.2)
            $table->timestamp('notified_1h_at')->nullable()->after('last_heartbeat_at');
            $table->timestamp('notified_5m_at')->nullable()->after('notified_1h_at');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn([
                'countdown_started_at', 'countdown_ends_at', 'countdown_bid_id',
                'countdown_cancelled_at', 'last_heartbeat_at', 'notified_1h_at', 'notified_5m_at',
            ]);
        });
    }
};

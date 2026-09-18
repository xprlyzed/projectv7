<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Görev 6.2: Kargolama son tarihi (paid_at + 3 gün)
            $table->timestamp('ship_by_at')->nullable()->after('shipped_at');
            // Görev 6.3: Serbest bırakma sonrası itiraz penceresi (completed + 15 gün)
            $table->timestamp('dispute_window_ends_at')->nullable()->after('completed_at');
            // Görev 6.1: İkinci teklif sahibine devredilen sipariş zinciri
            $table->unsignedBigInteger('reoffer_of')->nullable()->after('winning_bid_id');
        });

        Schema::table('users', function (Blueprint $table) {
            // Görev 6.1: Ödeme kaçırma sayacı (otomatik askıya alma YOK, sadece kayıt)
            $table->unsignedInteger('payment_misses')->default(0)->after('email_notifications');
            // Görev 6.2: Kargolamama ihlali sayacı
            $table->unsignedInteger('shipping_violations')->default(0)->after('payment_misses');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['ship_by_at', 'dispute_window_ends_at', 'reoffer_of']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['payment_misses', 'shipping_violations']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auction_images', function (Blueprint $table) {
            $table->string('card_path')->nullable()->after('path');
            $table->string('thumb_path')->nullable()->after('card_path');
        });
    }

    public function down(): void
    {
        Schema::table('auction_images', function (Blueprint $table) {
            $table->dropColumn(['card_path', 'thumb_path']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('profile_public')->default(true);
            $table->boolean('bids_hidden')->default(false);
            $table->boolean('show_online')->default(true);
            $table->boolean('email_notifications')->default(true);
            $table->boolean('messages_followers_only')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'profile_public',
                'bids_hidden',
                'show_online',
                'email_notifications',
                'messages_followers_only',
            ]);
        });
    }
};

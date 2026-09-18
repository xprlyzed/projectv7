<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Unique constraint eklemeden önce olası tekrar eden çiftleri temizle (en küçük id korunur)
        $dupes = DB::table('follows')
            ->select('follower_id', 'following_id', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as c'))
            ->groupBy('follower_id', 'following_id')
            ->having('c', '>', 1)
            ->get();

        foreach ($dupes as $d) {
            DB::table('follows')
                ->where('follower_id', $d->follower_id)
                ->where('following_id', $d->following_id)
                ->where('id', '!=', $d->keep_id)
                ->delete();
        }

        Schema::table('follows', function (Blueprint $table) {
            $table->unique(['follower_id', 'following_id']);
        });
    }

    public function down(): void
    {
        Schema::table('follows', function (Blueprint $table) {
            $table->dropUnique(['follower_id', 'following_id']);
        });
    }
};

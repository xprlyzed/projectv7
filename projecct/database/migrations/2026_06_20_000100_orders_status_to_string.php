<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // status ve escrow_status ENUM/uzunluk kısıtı olmadan serbest string olsun.
        // MySQL'de yerel MODIFY; diğer sürücülerde (test = sqlite) taşınabilir change().
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `orders` MODIFY `status` VARCHAR(30) NOT NULL DEFAULT 'awaiting_payment'");
            DB::statement("ALTER TABLE `orders` MODIFY `escrow_status` VARCHAR(20) NOT NULL DEFAULT 'none'");
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->string('status', 30)->default('awaiting_payment')->change();
            $table->string('escrow_status', 20)->default('none')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `orders` MODIFY `status` VARCHAR(30) NOT NULL DEFAULT 'pending'");
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->string('status', 30)->default('pending')->change();
        });
    }
};

<?php

namespace App\Console\Commands;

use App\Models\Auction;
use Illuminate\Console\Command;

/**
 * Görev 3: Kalp atışı (heartbeat) 60 sn'den eski olan ama hâlâ is_live=true olan
 * yayınları kapatır. Satıcı sekmesi çökse/kapansa bile ilan sonsuza kadar "CANLI"
 * kalmaz.
 */
class ReapStaleStreams extends Command
{
    protected $signature = 'streams:reap';

    protected $description = 'Kalp atışı gelmeyen (kopmuş) canlı yayınları is_live=false yapar';

    public function handle(): int
    {
        $threshold = now()->subSeconds(60);

        $count = Auction::query()
            ->where('is_live', true)
            ->where(function ($q) use ($threshold) {
                // Heartbeat var ama eski
                $q->where(function ($q2) use ($threshold) {
                    $q2->whereNotNull('last_heartbeat_at')
                        ->where('last_heartbeat_at', '<', $threshold);
                })
                // Hiç heartbeat yok ve yayın 60 sn'den önce başladı (yeni başlayanlara tolerans)
                ->orWhere(function ($q2) use ($threshold) {
                    $q2->whereNull('last_heartbeat_at')
                        ->where(function ($q3) use ($threshold) {
                            $q3->whereNull('live_started_at')
                                ->orWhere('live_started_at', '<', $threshold);
                        });
                });
            })
            ->update([
                'is_live'       => false,
                'live_ended_at' => now(),
            ]);

        $this->info("Kapatılan kopmuş yayın: {$count}");

        return self::SUCCESS;
    }
}

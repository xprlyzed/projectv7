<?php

namespace App\Console\Commands;

use App\Models\Auction;
use App\Models\User;
use App\Notifications\EndingSoonNotification;
use Illuminate\Console\Command;

/**
 * Görev 4.2: Bitişine ~1 saat ve ~5 dakika kalan aktif ilanlarda, o ilana teklif vermiş
 * ve ilanı izleyen (watchlist) kullanıcılara "bitmek üzere" bildirimi gönderir.
 * Her pencere ilan başına yalnızca bir kez gönderilir (notified_1h_at / notified_5m_at).
 */
class NotifyEndingSoon extends Command
{
    protected $signature = 'auctions:notify-ending-soon';

    protected $description = 'Bitmek üzere olan ilanlar için teklif verenlere/izleyenlere bildirim gönderir';

    public function handle(): int
    {
        $now = now();
        $sent = 0;

        // 1 saat penceresi: bitişe kalan süre <= 60 dk ve henüz 1h bildirimi yok
        Auction::query()
            ->where('status', 'active')
            ->where('ends_at', '>', $now)
            ->where('ends_at', '<=', (clone $now)->addMinutes(60))
            ->whereNull('notified_1h_at')
            ->get()
            ->each(function (Auction $auction) use (&$sent) {
                $this->dispatchTo($auction, 60);
                $auction->forceFill(['notified_1h_at' => now()])->save();
                $sent++;
            });

        // 5 dakika penceresi: bitişe kalan süre <= 5 dk ve henüz 5m bildirimi yok
        Auction::query()
            ->where('status', 'active')
            ->where('ends_at', '>', $now)
            ->where('ends_at', '<=', (clone $now)->addMinutes(5))
            ->whereNull('notified_5m_at')
            ->get()
            ->each(function (Auction $auction) use (&$sent) {
                $this->dispatchTo($auction, 5);
                $auction->forceFill(['notified_5m_at' => now()])->save();
                $sent++;
            });

        $this->info("Bildirim gönderilen ilan sayısı: {$sent}");

        return self::SUCCESS;
    }

    private function dispatchTo(Auction $auction, int $minutesLeft): void
    {
        $bidderIds  = $auction->bids()->reorder()->pluck('user_id');
        $watcherIds = $auction->watchlist()->pluck('user_id');

        $userIds = $bidderIds->merge($watcherIds)
            ->unique()
            ->reject(fn ($id) => (int) $id === (int) $auction->user_id)
            ->values();

        if ($userIds->isEmpty()) {
            return;
        }

        User::whereIn('id', $userIds)->get()->each(function (User $user) use ($auction, $minutesLeft) {
            try {
                $user->notify(new EndingSoonNotification($auction, $minutesLeft));
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }
}

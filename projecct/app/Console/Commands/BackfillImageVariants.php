<?php

namespace App\Console\Commands;

use App\Models\AuctionImage;
use App\Services\ImageVariantService;
use Illuminate\Console\Command;

class BackfillImageVariants extends Command
{
    protected $signature = 'images:backfill {--force : card_path dolu olsa bile yeniden üret}';

    protected $description = 'Mevcut ilan görselleri için card/thumb webp variant\'larını üretir (idempotent).';

    public function handle(): int
    {
        $query = AuctionImage::query();
        if (! $this->option('force')) {
            $query->whereNull('card_path');
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Üretilecek variant yok. Hepsi güncel.');
            return self::SUCCESS;
        }

        $this->info("{$total} görsel için variant üretiliyor...");
        $bar = $this->output->createProgressBar($total);
        $done = 0;

        $query->chunkById(100, function ($images) use (&$done, $bar) {
            foreach ($images as $img) {
                $variants = ImageVariantService::generate($img->path);
                if ($variants['card'] || $variants['thumb']) {
                    $img->update([
                        'card_path'  => $variants['card'],
                        'thumb_path' => $variants['thumb'],
                    ]);
                    $done++;
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Tamamlandı. {$done}/{$total} görsel için variant üretildi (yerel olmayanlar atlandı).");

        return self::SUCCESS;
    }
}

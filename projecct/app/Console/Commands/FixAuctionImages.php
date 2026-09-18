<?php

namespace App\Console\Commands;

use App\Models\Auction;
use App\Models\AuctionImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class FixAuctionImages extends Command
{
    protected $signature = 'catalog:fix-images';
    protected $description = 'Kırık/eksik ilan kapak görsellerini gerçek katalog görselleriyle değiştirir';

    public function handle(): int
    {
        $pool = [
            'sanat'      => ['paint1', 'paint2', 'paint3', 'portre1', 'heykel1', 'heykel2'],
            'antika'     => ['saat1', 'saat2', 'porselen1', 'porselen2', 'mobilya1', 'mobilya2', 'kitap1'],
            'elektronik' => ['kamera1', 'kamera2', 'kamera3', 'plak1', 'plak2', 'plak3'],
            'mucevherat' => ['muc1', 'muc2', 'muc3', 'kol1'],
            'default'    => ['paint1', 'saat1', 'kamera1', 'muc1', 'porselen1', 'plak1', 'mobilya1', 'kol1'],
        ];

        $fixed = 0;
        $counters = [];

        Auction::with(['cover', 'category'])->chunk(100, function ($auctions) use (&$fixed, &$counters, $pool) {
            foreach ($auctions as $a) {
                $cover = $a->cover;
                $path = $cover?->card_path ?: $cover?->path;
                $exists = $path && Storage::disk('public')->exists($path);
                if ($exists) {
                    continue;
                }

                // Kategori kökünü belirle
                $cat = $a->category;
                $rootSlug = 'default';
                if ($cat) {
                    $root = $cat->parent_id ? $cat->parent : $cat;
                    $rootSlug = in_array($root->slug, ['sanat', 'antika', 'elektronik', 'mucevherat'])
                        ? $root->slug : 'default';
                }

                $keys = $pool[$rootSlug] ?? $pool['default'];
                $idx = ($counters[$rootSlug] ?? 0) % count($keys);
                $counters[$rootSlug] = ($counters[$rootSlug] ?? 0) + 1;
                $newPath = 'catalog/' . $keys[$idx] . '.jpg';

                if ($cover) {
                    $cover->update(['path' => $newPath, 'thumb_path' => null, 'card_path' => null]);
                } else {
                    AuctionImage::create([
                        'auction_id' => $a->id,
                        'path'       => $newPath,
                        'is_cover'   => true,
                        'sort_order' => 0,
                    ]);
                }
                $fixed++;
            }
        });

        $this->info("Düzeltilen görsel sayısı: {$fixed}");

        return self::SUCCESS;
    }
}

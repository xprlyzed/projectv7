<?php

namespace Database\Seeders;

use App\Models\Auction;
use App\Models\AuctionImage;
use App\Models\Bid;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RealCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $sellers = User::role('seller')->get();
        if ($sellers->isEmpty()) {
            $sellers = collect([User::where('email', 'seller@test.com')->first()])->filter();
        }
        if ($sellers->isEmpty()) {
            return;
        }
        $buyers = User::role('buyer')->take(12)->get();
        $cats = Category::pluck('id', 'slug');

        // [title, catSlug, price, imgKey, status, location, featured, live]
        $items = [
            ['18. yy Yağlıboya Kadın Portresi', 'portre', 42000, 'paint1', 'active', 'İstanbul', true, true],
            ['Mavi Elbiseli Leydi — Rokoko Tablo', 'tablo', 36500, 'paint2', 'active', 'İzmir', true, false],
            ['Beyaz Başörtülü Genç Kız — Portre', 'portre', 28000, 'paint3', 'active', 'Ankara', false, false],
            ['Klasik Bronz Kadın Heykeli', 'heykel', 54000, 'heykel1', 'active', 'İstanbul', true, true],
            ['Mermer Figür — Antik Stil Heykel', 'heykel', 61000, 'heykel2', 'active', 'Bursa', false, false],
            ['Oturan Adam — Bronz Döküm Heykel', 'heykel', 33000, 'heykel3', 'ended', 'Antalya', false, false],
            ['Oymalı Ahşap Berjer Koltuk', 'mobilya', 18500, 'mobilya1', 'active', 'İstanbul', true, false],
            ['Ceviz Antika Şifonyer', 'mobilya', 22000, 'mobilya2', 'active', 'Eskişehir', false, false],
            ['Aynalı Antika Tuvalet Masası', 'mobilya', 15900, 'mobilya3', 'active', 'İzmir', false, true],
            ['Mavi Kuş Desenli Porselen Vazo', 'porselen-seramik', 9800, 'porselen1', 'active', 'Kütahya', true, false],
            ['Emaye Çini Vazo — El Yapımı', 'porselen-seramik', 7400, 'porselen2', 'active', 'Bursa', false, false],
            ['Çiçek Desenli Antika Seramik Vazo', 'porselen-seramik', 5200, 'porselen3', 'ended', 'Ankara', false, false],
            ['İskeletli Antika Cep Saati', 'saat', 26500, 'saat1', 'active', 'İstanbul', true, true],
            ['Yeşil Emaye Kadran Cep Saati', 'saat', 19800, 'saat2', 'active', 'İzmir', false, false],
            ['Gümüş Kapaklı Zincirli Cep Saati', 'saat', 14200, 'saat3', 'active', 'Antalya', false, false],
            ['Deri Ciltli Nadir Kitap Koleksiyonu', 'kitap-harita', 31000, 'kitap1', 'active', 'İstanbul', true, false],
            ['Antika Kütüphane — Ciltli Set', 'kitap-harita', 24500, 'kitap2', 'active', 'Ankara', false, false],
            ['Pırlanta Kolye Ucu — Beyaz Altın', 'kolye', 48000, 'muc1', 'active', 'İstanbul', true, true],
            ['Altın Zincir Kolye — Vintage', 'kolye', 27000, 'muc3', 'active', 'İzmir', false, false],
            ['Üçlü Pırlanta Yüzük Seti', 'yuzuk', 39000, 'muc2', 'active', 'İstanbul', true, false],
            ['Canon Rangefinder Analog Kamera', 'fotograf-makinesi', 8600, 'kamera1', 'active', 'İstanbul', false, true],
            ['Vintage Canon 35mm Film Makinesi', 'fotograf-makinesi', 6900, 'kamera2', 'active', 'İzmir', true, false],
            ['Zenit Analog Fotoğraf Makinesi', 'fotograf-makinesi', 4300, 'kamera3', 'ended', 'Bursa', false, false],
            ['Pirinç Boru Antika Gramofon', 'plak-ses', 34000, 'plak1', 'active', 'İstanbul', true, true],
            ['Ahşap Kasa Vintage Pikap', 'plak-ses', 12500, 'plak2', 'active', 'Ankara', false, false],
            ['Nadir Osmanlı Pul Koleksiyonu', 'pul', 17800, 'kol2', 'active', 'İstanbul', true, false],
            ['Antik Gümüş Sikke Koleksiyonu', 'para', 45000, 'kol1', 'active', 'İzmir', true, true],
            ['Karışık Madeni Para Lotu', 'para', 9200, 'kol3', 'active', 'Antalya', false, false],
        ];

        foreach ($items as $i => [$title, $catSlug, $price, $img, $status, $loc, $featured, $live]) {
            if (Auction::where('title', $title)->exists()) {
                continue;
            }
            if (! isset($cats[$catSlug])) {
                continue;
            }

            $seller = $sellers[$i % $sellers->count()];
            $endsAt = $status === 'ended'
                ? now()->subDays(rand(1, 5))
                : now()->addDays(rand(1, 8))->addHours(rand(1, 20));

            $auction = Auction::create([
                'user_id'           => $seller->id,
                'category_id'       => $cats[$catSlug],
                'title'             => $title,
                'slug'              => Str::slug($title) . '-' . Str::random(4),
                'description'       => "Bu {$title}, orijinal ve bakımlı bir koleksiyon parçasıdır. Ekspertiz raporu ve detaylı fotoğraflar mevcuttur; ilgilenen alıcılar satıcıyla iletişime geçebilir. Güvenli teslimat ve orijinallik garantisi sunulur.",
                'starting_price'    => $price,
                'current_price'     => $price,
                'min_bid_increment' => max(50, round($price * 0.02)),
                'buy_now_price'     => round($price * 1.6),
                'starts_at'         => now()->subDays(1),
                'ends_at'           => $endsAt,
                'status'            => $status,
                'is_featured'       => $featured,
                'condition'         => 'used',
                'location'          => $loc,
                'view_count'        => rand(40, 1200),
                'stream_mode'       => ($status === 'active' && $live) ? 'live' : 'standard',
            ]);

            AuctionImage::create([
                'auction_id' => $auction->id,
                'path'       => "catalog/{$img}.jpg",
                'is_cover'   => true,
                'sort_order' => 0,
            ]);

            $bidCount = rand(4, 11);
            $amount = $price;
            for ($b = 0; $b < $bidCount; $b++) {
                $amount += rand(1, 4) * $auction->min_bid_increment;
                $bidder = $buyers->isNotEmpty() ? $buyers->random() : $seller;
                Bid::create([
                    'auction_id' => $auction->id,
                    'user_id'    => $bidder->id,
                    'amount'     => $amount,
                    'created_at' => now()->subMinutes(($bidCount - $b) * rand(5, 40)),
                ]);
            }
            $auction->update(['current_price' => $amount]);
        }
    }
}

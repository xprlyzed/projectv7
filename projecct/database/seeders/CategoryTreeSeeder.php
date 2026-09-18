<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryTreeSeeder extends Seeder
{
    public function run(): void
    {
        Storage::disk('public')->makeDirectory('categories');

        // [slug, name, unsplash_url, [children: slug, name, unsplash_url]]
        $tree = [
            ['sanat', 'Sanat', 'https://images.unsplash.com/photo-1513475382585-d06e58bcb0e0?w=800&h=600&fit=crop&auto=format', [
                ['tablo', 'Tablo & Resim', 'https://images.unsplash.com/photo-1579783902614-a3fb3927b6a5?w=800&h=600&fit=crop&auto=format'],
                ['portre', 'Portre', 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=800&h=600&fit=crop&crop=faces&auto=format'],
                ['heykel', 'Heykel', 'https://images.unsplash.com/photo-1558642452-9d2a7deb7f62?w=800&h=600&fit=crop&auto=format'],
                ['baski', 'Baskı & Gravür', 'https://images.unsplash.com/photo-1579783483458-83d02161294e?w=800&h=600&fit=crop&auto=format'],
            ]],
            ['antika', 'Antika', 'https://images.unsplash.com/photo-1567696911980-2eed69a46042?w=800&h=600&fit=crop&auto=format', [
                ['mobilya', 'Mobilya', 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=800&h=600&fit=crop&auto=format'],
                ['porselen-seramik', 'Porselen & Seramik', 'https://images.unsplash.com/photo-1578500494198-246f612d3b3d?w=800&h=600&fit=crop&auto=format'],
                ['saat', 'Saat', 'https://images.unsplash.com/photo-1594035910387-fea47794261f?w=800&h=600&fit=crop&auto=format'],
                ['kitap-harita', 'Kitap & Harita', 'https://images.unsplash.com/photo-1580136579312-94651dfd596d?w=800&h=600&fit=crop&auto=format'],
            ]],
            ['mucevherat', 'Mücevherat', 'https://images.unsplash.com/photo-1611652022419-a9419f74343d?w=800&h=600&fit=crop&auto=format', [
                ['yuzuk', 'Yüzük', 'https://images.unsplash.com/photo-1605100804763-247f67b3557e?w=800&h=600&fit=crop&auto=format'],
                ['kolye', 'Kolye', 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=800&h=600&fit=crop&auto=format'],
                ['bros', 'Broş & Küpe', 'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?w=800&h=600&fit=crop&auto=format'],
            ]],
            ['elektronik', 'Elektronik', 'https://images.unsplash.com/photo-1495707902641-75cac588d2e9?w=800&h=600&fit=crop&auto=format', [
                ['fotograf-makinesi', 'Fotoğraf Makinesi', 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=800&h=600&fit=crop&auto=format'],
                ['plak-ses', 'Plak & Ses Sistemleri', 'https://images.unsplash.com/photo-1539375665275-f9de415ef9ac?w=800&h=600&fit=crop&auto=format'],
                ['retro-bilgisayar', 'Retro Bilgisayar', 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=800&h=600&fit=crop&auto=format'],
            ]],
            ['koleksiyon', 'Koleksiyon', 'https://images.unsplash.com/photo-1597589827317-4c6d6e0a90bd?w=800&h=600&fit=crop&auto=format', [
                ['pul', 'Pul', 'https://images.unsplash.com/photo-1584727638096-042c45049ebe?w=800&h=600&fit=crop&auto=format'],
                ['para', 'Para & Madeni', 'https://images.unsplash.com/photo-1621981386829-9b458a2cddde?w=800&h=600&fit=crop&auto=format'],
                ['model', 'Model & Maket', 'https://images.unsplash.com/photo-1581235707960-35f13de9ee4b?w=800&h=600&fit=crop&auto=format'],
            ]],
            ['hali-kilim', 'Halı & Kilim', 'https://images.unsplash.com/photo-1600166898405-da9535204843?w=800&h=600&fit=crop&auto=format', [
                ['el-dokuma-hali', 'El Dokuma Halı', 'https://images.unsplash.com/photo-1600166898405-da9535204843?w=800&h=600&fit=crop&auto=format&q=80'],
                ['antik-kilim', 'Antik Kilim', 'https://images.unsplash.com/photo-1581783342308-f792dbdd27c5?w=800&h=600&fit=crop&auto=format'],
                ['ipek-hali', 'İpek Halı', 'https://images.unsplash.com/photo-1560448205-4d9b3e6bb6db?w=800&h=600&fit=crop&auto=format'],
            ]],
            ['spor-outdoor', 'Spor & Outdoor', 'https://images.unsplash.com/photo-1517649763962-0c623066013b?w=800&h=600&fit=crop&auto=format', [
                ['vintage-bisiklet', 'Vintage Bisiklet', 'https://images.unsplash.com/photo-1485965120184-e220f721d03e?w=800&h=600&fit=crop&auto=format'],
                ['outdoor-ekipman', 'Outdoor Ekipman', 'https://images.unsplash.com/photo-1551632811-561732d1e306?w=800&h=600&fit=crop&auto=format'],
                ['koleksiyon-spor', 'Koleksiyon Spor Malzemesi', 'https://images.unsplash.com/photo-1579952363873-27f3bade9f55?w=800&h=600&fit=crop&auto=format'],
            ]],
            ['muzik-aletleri', 'Müzik Aletleri', 'https://images.unsplash.com/photo-1511192336575-5a79af67a629?w=800&h=600&fit=crop&auto=format', [
                ['klasik-gitar', 'Klasik Gitar', 'https://images.unsplash.com/photo-1510915361894-db8b60106cb1?w=800&h=600&fit=crop&auto=format'],
                ['piyano-tuslu', 'Piyano & Tuşlu', 'https://images.unsplash.com/photo-1520523839897-bd0b52f945a0?w=800&h=600&fit=crop&auto=format'],
                ['uflemeli-yayli', 'Üflemeli & Yaylı', 'https://images.unsplash.com/photo-1558098329-a11cff621064?w=800&h=600&fit=crop&auto=format'],
            ]],
        ];

        $rootOrder = 0;
        foreach ($tree as [$slug, $name, $imgUrl, $children]) {
            $imgPath = $this->downloadCategoryImage($imgUrl, $name);
            $root = Category::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'parent_id' => null, 'image' => $imgPath, 'is_active' => true, 'sort_order' => $rootOrder++]
            );

            $childOrder = 0;
            foreach ($children as [$cslug, $cname, $cimgUrl]) {
                $cImgPath = $this->downloadCategoryImage($cimgUrl, $cname);
                Category::updateOrCreate(
                    ['slug' => $cslug],
                    ['name' => $cname, 'parent_id' => $root->id, 'image' => $cImgPath, 'is_active' => true, 'sort_order' => $childOrder++]
                );
            }
        }
    }

    private function downloadCategoryImage(string $url, string $name): ?string
    {
        try {
            $response = Http::timeout(20)->withoutVerifying()->get($url);
            if (!$response->successful()) return null;
            $filename = 'categories/' . Str::slug($name) . '-' . Str::random(4) . '.jpg';
            Storage::disk('public')->put($filename, $response->body());
            return $filename;
        } catch (\Throwable) {
            return null;
        }
    }
}

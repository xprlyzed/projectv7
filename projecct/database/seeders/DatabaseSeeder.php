<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Üretimde çalıştırma koruması: bu seeder sabit/zayıf parolalı (password) demo
        // hesapları oluşturur. Production'da ilk yöneticiyi `php artisan admin:create` ile kurun.
        if (app()->isProduction()) {
            $this->command?->warn('DatabaseSeeder production ortamında atlandı. Yönetici için: php artisan admin:create');
            return;
        }

        $this->call([SettingsSeeder::class]);
        $adminRole  = Role::firstOrCreate(['name' => 'admin',  'guard_name' => 'web']);
        $sellerRole = Role::firstOrCreate(['name' => 'seller', 'guard_name' => 'web']);
        $buyerRole  = Role::firstOrCreate(['name' => 'buyer',  'guard_name' => 'web']);

        $admin = User::factory()->create([
            'name'        => 'Admin',
            'username' => 'admin',
            'email'       => 'admin@test.com',
            'password'    => bcrypt('password'),
            'is_verified' => 1
        ]);

        $admin->assignRole($adminRole);

        $seller = User::factory()->create([
            'name'        => 'test satıcı',
            'username' => 'seller',
            'email'       => 'seller@test.com',
            'password'    => bcrypt('password'),
            'is_verified' => 1
        ]);

        $seller->assignRole($sellerRole);

        SellerProfile::create([
            'user_id'             => $seller->id,
            'company_name'        => 'Test Satıcı A.Ş.',
            'tax_number'          => fake()->numerify('##########'),
            'iban'                => fake()->iban('TR'),
            'id_document_path'    => null,
            'verification_status' => 'approved',
            'verified_at'         => now(),
        ]);

         $buyer = User::factory()->create([
            'name'        => 'test alıcı',
            'username' => 'buyer',
            'email'       => 'buyer@test.com',
            'password'    => bcrypt('password'),
            'is_verified' => 1
        ]);

        $buyer->assignRole($buyerRole);

        $sellers = User::factory(50)->create();

        foreach ($sellers as $seller) {
            $seller->assignRole($sellerRole);

            SellerProfile::create([
                'user_id'             => $seller->id,
                'company_name'        => fake()->company(),
                'tax_number'          => fake()->numerify('##########'),
                'iban'                => fake()->iban('TR'),
                'id_document_path'    => null,
                'verification_status' => 'approved',
                'verified_at'         => now(),
            ]);
        }

        $buyers = User::factory(50)->create();

        foreach ($buyers as $buyer) {
            $buyer->assignRole($buyerRole);
        }

        // Detaylı kategori ağacı (5 ana + 17 alt kategori)
        $this->call([CategoryTreeSeeder::class]);

        // Temel ilanlar + zengin katalog + canlı veri (Unsplash görselleri, hikaye, chat, review, watchlist)
        $this->call([
            AuctionSeeder::class,
            RealCatalogSeeder::class,
            LiveDataSeeder::class,
        ]);
    }
}

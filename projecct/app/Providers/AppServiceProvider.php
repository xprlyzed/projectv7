<?php

namespace App\Providers;

use App\Models\Auction;
use App\Policies\AuctionPolicy;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;
use Artesaos\SEOTools\Facades\SEOMeta;
use Artesaos\SEOTools\Facades\OpenGraph;
use Artesaos\SEOTools\Facades\TwitterCard;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Preview/prod ingress HTTPS'i sonlandırır. Sadece şemayı https'e sabitliyoruz;
        // host'u TrustProxies('*') üzerinden forwarded Host'tan türetiyoruz. Böylece hangi
        // geçerli preview alan adında olursak olalım linkler/Inertia POST'ları AYNI-ORIGIN kalır
        // (URL::forceRootUrl(APP_URL) çapraz-origin POST'a ve 419'a yol açıyordu — kaldırıldı).
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // SEO varsayılanları (controller'lar sayfa bazında override eder)
        $seoDesc = 'Canlı açık artırma platformu — antika, koleksiyon ve özel parçalar için gerçek zamanlı müzayedeler.';
        $seoImg  = asset('assets/media/logos/logo-dark.svg');
        SEOMeta::setDescription($seoDesc);
        OpenGraph::setSiteName(config('app.name'))
            ->setType('website')
            ->setTitle(config('app.name'))
            ->setDescription($seoDesc);
        TwitterCard::setType('summary_large_image')
            ->setTitle(config('app.name'))
            ->setDescription($seoDesc)
            ->setImage($seoImg);

        Horizon::auth(function ($request) {
            return $request->user()?->hasRole('admin');
        });

        require_once app_path('helpers.php');
        Gate::policy(Auction::class, AuctionPolicy::class);
        Event::listen(Registered::class, SendEmailVerificationNotification::class);

        // Login throttle: anahtar HESAP (email) + IP üzerine kurulur (yalnız IP değil).
        // Böylece farklı hesaplara yapılan denemeler bağımsız sayılır (NAT arkası kullanıcılar
        // topluca kilitlenmez), ama tek hesaba brute-force 5/dk ile sınırlanır.
        \Illuminate\Support\Facades\RateLimiter::for('login', function (\Illuminate\Http\Request $request) {
            $email = \Illuminate\Support\Str::lower(trim((string) $request->input('email')));
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)
                ->by(sha1($email . '|' . $request->ip()));
        });
    }
}

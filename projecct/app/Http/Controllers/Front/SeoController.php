<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use Illuminate\Support\Facades\Cache;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class SeoController extends Controller
{
    public function sitemap()
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), function () {
            $sitemap = Sitemap::create();

            foreach (['index', 'browse.auctions', 'browse.live', 'browse.explore', 'corporate', 'contact', 'privacy'] as $name) {
                $sitemap->add(Url::create(route($name))->setPriority(0.6));
            }

            Auction::whereIn('status', ['active', 'ended', 'sold'])
                ->select('slug', 'updated_at')
                ->orderByDesc('updated_at')
                ->limit(5000)
                ->get()
                ->each(function ($a) use ($sitemap) {
                    $sitemap->add(
                        Url::create(route('auctions.show', $a->slug))
                            ->setLastModificationDate($a->updated_at ?? now())
                            ->setPriority(0.8)
                    );
                });

            return $sitemap->render();
        });

        return response($xml, 200, ['Content-Type' => 'text/xml; charset=UTF-8']);
    }

    public function robots()
    {
        $base = rtrim(config('app.url'), '/');

        // Özel/hassas alanlar arama motorlarına kapalı
        $disallow = ['/admin', '/seller', '/messages', '/buyer/balance', '/notifications', '/support'];
        $body = "User-agent: *\n";
        foreach ($disallow as $path) {
            $body .= "Disallow: {$path}\n";
        }
        $body .= "\nSitemap: {$base}/sitemap.xml\n";

        return response($body, 200, ['Content-Type' => 'text/plain']);
    }
}

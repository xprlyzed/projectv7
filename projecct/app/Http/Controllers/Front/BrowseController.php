<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BrowseController extends Controller
{
    private const PER_PAGE = 12;

    private function buildAuctionQuery(Request $request)
    {
        $query = Auction::query()->public()->withCount('bids')->with(['category', 'cover']);

        if ($request->filled('q')) {
            $query->where('title', 'like', '%' . $request->q . '%');
        }

        if ($request->filled('category')) {
            $cat = Category::where('slug', $request->category)->first();
            if ($cat) {
                $ids = array_merge([$cat->id], $cat->allChildrenIds());
                $query->whereIn('category_id', $ids);
            }
        }

        if ($request->filled('min_price')) {
            $query->where('current_price', '>=', (int) $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('current_price', '<=', (int) $request->max_price);
        }

        if ($request->status === 'active') {
            $query->where('status', 'active')->where('ends_at', '>', now());
        } elseif ($request->status === 'ended') {
            $query->where(function ($q) {
                $q->where('status', '!=', 'active')->orWhere('ends_at', '<=', now());
            });
        }

        match ($request->sort) {
            'ending'    => $query->where('ends_at', '>', now())->orderBy('ends_at'),
            'new'       => $query->latest(),
            'price'     => $query->orderByDesc('current_price'),
            'price_asc' => $query->orderBy('current_price'),
            default     => $query->orderByDesc('bids_count'),
        };

        return $query;
    }

    public function auctions(Request $request)
    {
        $roots = Category::active()->roots()->ordered()
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->withCount(['auctions as auctions_count' => fn ($q) => $q->whereIn('status', ['active', 'ended', 'sold'])])
            ->get();

        $flatCategories = [];
        foreach ($roots as $root) {
            $flatCategories[] = ['slug' => $root->slug, 'name' => $root->name, 'depth' => 0, 'auctions_count' => $root->auctions_count];
            foreach ($root->children as $child) {
                $flatCategories[] = ['slug' => $child->slug, 'name' => $child->name, 'depth' => 1, 'auctions_count' => null];
            }
        }

        $auctions = $this->buildAuctionQuery($request)->paginate(self::PER_PAGE)->withQueryString();

        \Artesaos\SEOTools\Facades\SEOMeta::setTitle('Müzayedeler')
            ->setDescription('Tüm açık artırmaları keşfedin. Kategoriye, fiyata ve duruma göre filtreleyin, favori ürünlerinize teklif verin.')
            ->setCanonical(route('browse.auctions'));

        return Inertia::render('Browse/Auctions', [
            'auctions'   => [
                'data'         => collect($auctions->items())->map->toCard()->values(),
                'total'        => $auctions->total(),
                'current_page' => $auctions->currentPage(),
                'last_page'    => $auctions->lastPage(),
                'has_more'     => $auctions->hasMorePages(),
            ],
            'categories' => $flatCategories,
            'filters' => [
                'q' => $request->q ?? '', 'category' => $request->category ?? '',
                'status' => $request->status ?? '', 'sort' => $request->sort ?? 'bids',
                'min_price' => $request->min_price ?? '', 'max_price' => $request->max_price ?? '',
            ],
            'now' => now()->format('d.m.Y H:i'),
        ]);
    }

    public function auctionsFeed(Request $request)
    {
        $auctions = $this->buildAuctionQuery($request)->paginate(self::PER_PAGE);

        return response()->json([
            'data'         => collect($auctions->items())->map->toCard()->values(),
            'current_page' => $auctions->currentPage(),
            'last_page'    => $auctions->lastPage(),
            'has_more'     => $auctions->hasMorePages(),
        ]);
    }

    public function live()
    {
        $liveAuctions = Auction::query()
            ->withCount('bids')
            ->with(['category', 'cover'])
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->where('is_live', true)
            ->orderByRaw("stream_mode = 'live' DESC")
            ->orderBy('ends_at')
            ->get();

        $trueLive = $liveAuctions->where('stream_mode', 'live')->count();

        \Artesaos\SEOTools\Facades\SEOMeta::setTitle('Canlı Açık Artırmalar')
            ->setDescription('Şu an canlı yayında olan açık artırmalar. Gerçek zamanlı teklif verin, heyecanı kaçırmayın.')
            ->setCanonical(route('browse.live'));

        return Inertia::render('Browse/Live', [
            'liveAuctions' => $liveAuctions->map->toCard()->values(),
            'stats' => [
                'total'     => $liveAuctions->count(),
                'streaming' => $trueLive,
                'ending_soon' => $liveAuctions->filter(fn ($a) => $a->ends_at && $a->ends_at->lte(now()->addHours(6)))->count(),
            ],
            'now' => now()->format('d.m.Y H:i'),
        ]);
    }

    public function explore()
    {
        $roots = Category::active()->roots()->ordered()
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')
                ->withCount(['auctions as auctions_count' => fn ($qq) => $qq->whereIn('status', ['active', 'ended', 'sold'])])])
            ->withCount(['auctions as auctions_count' => fn ($q) => $q->whereIn('status', ['active', 'ended', 'sold'])])
            ->get();

        $tree = $roots->map(fn ($root) => [
            'slug' => $root->slug,
            'name' => $root->name,
            'image_url' => $root->image_url,
            'auctions_count' => $root->auctions_count,
            'browse_url' => route('browse.auctions', ['category' => $root->slug]),
            'children' => $root->children->map(fn ($c) => [
                'slug' => $c->slug,
                'name' => $c->name,
                'auctions_count' => $c->auctions_count,
                'browse_url' => route('browse.auctions', ['category' => $c->slug]),
            ])->values(),
        ])->values();

        $featuredAuctions = Auction::query()
            ->with(['category', 'cover'])
            ->withCount('bids')
            ->where('is_featured', true)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->latest()->take(8)->get();

        $newAuctions = Auction::query()
            ->with(['category', 'cover'])
            ->withCount('bids')
            ->public()
            ->latest()->paginate(self::PER_PAGE);

        \Artesaos\SEOTools\Facades\SEOMeta::setTitle('Keşfet')
            ->setDescription('Kategorilere göz atın, öne çıkan ve yeni eklenen açık artırmaları keşfedin.')
            ->setCanonical(route('browse.explore'));

        return Inertia::render('Browse/Explore', [
            'categoryTree'     => $tree,
            'featuredAuctions' => $featuredAuctions->map->toCard()->values(),
            'newAuctions'      => [
                'data'         => collect($newAuctions->items())->map->toCard()->values(),
                'current_page' => $newAuctions->currentPage(),
                'last_page'    => $newAuctions->lastPage(),
                'has_more'     => $newAuctions->hasMorePages(),
            ],
            'now' => now()->format('d.m.Y H:i'),
        ]);
    }

    public function exploreFeed(Request $request)
    {
        $newAuctions = Auction::query()
            ->with(['category', 'cover'])
            ->withCount('bids')
            ->public()
            ->latest()->paginate(self::PER_PAGE);

        return response()->json([
            'data'         => collect($newAuctions->items())->map->toCard()->values(),
            'current_page' => $newAuctions->currentPage(),
            'last_page'    => $newAuctions->lastPage(),
            'has_more'     => $newAuctions->hasMorePages(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionImage;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AuctionController extends Controller
{
    public function index()
    {
        $allAuctions = auth()->user()->auctions();

        $statusRaw = (clone $allAuctions)->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');
        $counts = [
            'all'      => (int) $statusRaw->sum(),
            'draft'    => (int) ($statusRaw['draft'] ?? 0),
            'active'   => (int) ($statusRaw['active'] ?? 0),
            'rejected' => (int) ($statusRaw['rejected'] ?? 0),
            'ended'    => (int) ($statusRaw['ended'] ?? 0),
        ];

        $auctions = (clone $allAuctions)
            ->when(request('search'), fn ($q, $v) => $q->where('title', 'like', "%$v%"))
            ->when(request('status'), fn ($q, $v) => $q->where('status', $v))
            ->with(['user', 'category'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $statusMap = [
            'draft'     => ['Bekliyor',   'pf-badge-warning'],
            'active'    => ['Aktif',      'pf-badge-success'],
            'rejected'  => ['Reddedildi', 'pf-badge-danger'],
            'ended'     => ['Bitti',      'pf-badge-dark'],
            'sold'      => ['Satıldı',    'pf-badge-cyan'],
            'cancelled' => ['İptal',      'pf-badge-danger'],
        ];

        return Inertia::render('Seller/Auctions/Index', [
            'counts'  => $counts,
            'filters' => [
                'search' => request('search', ''),
                'status' => request('status', ''),
            ],
            'auctions' => [
                'data' => collect($auctions->items())->map(function ($a) use ($statusMap) {
                    [$slabel, $sclass] = $statusMap[$a->status] ?? ['—', 'pf-badge-dark'];
                    // Onaylı ama başlamadıysa liste de public detay ile tutarlı olsun: "Planlı"
                    if ($a->isPlanned()) {
                        [$slabel, $sclass] = ['Planlı', 'pf-badge-warning'];
                    }

                    return [
                        'id'             => $a->id,
                        'title'          => Str::limit($a->title, 45),
                        'cover_url'      => $a->coverUrl(),
                        'category_name'  => $a->category?->name ?? '—',
                        'location'       => $a->location,
                        'seller_name'    => $a->user->name,
                        'seller_email'   => $a->user->email,
                        'starting_price' => number_format($a->starting_price, 0, ',', '.') . ' ₺',
                        'status_label'   => $slabel,
                        'status_class'   => $sclass,
                        'date'           => $a->created_at->format('d.m.Y'),
                        'show_url'       => route('seller.auctions.show', $a),
                        'edit_url'       => route('seller.auctions.edit', $a),
                        'destroy_url'    => route('seller.auctions.destroy', $a),
                    ];
                })->values(),
                'links'      => $auctions->linkCollection()->toArray(),
                'has_pages'  => $auctions->hasPages(),
                'total'      => $auctions->total(),
                'from'       => $auctions->firstItem(),
                'to'         => $auctions->lastItem(),
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Seller/Auctions/Create', [
            'categories' => $this->categoryOptions(),
            'defaults' => [
                'starts_at' => now()->format('Y-m-d\TH:i'),
                'ends_at'   => now()->addDays(7)->format('Y-m-d\TH:i'),
            ],
        ]);
    }

    private function categoryOptions(): array
    {
        $out = [];
        $walk = function ($nodes, $depth) use (&$walk, &$out) {
            foreach ($nodes as $node) {
                $out[] = [
                    'id'    => $node->id,
                    'label' => str_repeat('— ', $depth) . $node->name,
                ];
                if ($node->childrenRecursive && $node->childrenRecursive->count()) {
                    $walk($node->childrenRecursive, $depth + 1);
                }
            }
        };
        $walk(Category::tree(), 0);

        return $out;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:120',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'required|string|max:5000',
            'starting_price' => 'required|numeric|min:1',
            'reserve_price' => 'nullable|numeric|min:0',
            'buy_now_price' => 'nullable|numeric|min:0',
            'min_bid_increment' => 'required|numeric|min:1',
            'condition' => 'required|in:new,used,refurbished',
            'location' => 'nullable|string|max:100',
            'starts_at' => ['required', 'date', 'after_or_equal:' . now()->subMinutes(5)->toDateTimeString()],
            'ends_at' => 'required|date|after:starts_at',
            'images' => 'required|array|min:1|max:10',
            'images.*' => 'image|max:4096',
        ]);

        DB::transaction(function () use ($data, $request) {
            // "Şimdi" seçildiğinde form/gönderim gecikmesi tarihi birkaç saniye geçmiş gösterebilir → şimdiye sabitle
            // (5 dk toleransla gerçek geçmiş tarihler zaten reddedilir). TZ: Europe/Istanbul.
            $startsAt = \Carbon\Carbon::parse($data['starts_at']);
            if ($startsAt->isPast()) {
                $startsAt = now();
            }
            $data['starts_at'] = $startsAt;

            $auction = Auction::create(array_merge($data, [
                'user_id' => auth()->id(),
                'current_price' => $data['starting_price'],
                'status' => 'draft',
            ]));

            foreach ($request->file('images') as $i => $file) {
                $path = $file->store('auctions/'.$auction->id, 'public');
                $variants = \App\Services\ImageVariantService::generate($path);
                AuctionImage::create([
                    'auction_id' => $auction->id,
                    'path' => $path,
                    'card_path' => $variants['card'],
                    'thumb_path' => $variants['thumb'],
                    'is_cover' => $i === 0,
                    'sort_order' => $i,
                ]);
            }
        });

        return redirect()->route('seller.auctions.index', auth()->user())
            ->with('success', 'İlanın onaya gönderildi. Admin onayından sonra yayınlanacak. ✅');
    }

    public function show(Auction $auction)
    {
        abort_unless(
            auth()->id() === $auction->user_id || auth()->user()->hasRole('admin'),
            403
        );

        $auction->increment('view_count');
        $auction->load('user', 'category', 'images', 'bids.user');

        $statusMap = [
            'draft'     => ['Bekliyor',   'warning'],
            'active'    => ['Aktif',      'success'],
            'rejected'  => ['Reddedildi', 'danger'],
            'ended'     => ['Bitti',      'danger'],
            'sold'      => ['Satıldı',    'seller'],
            'cancelled' => ['İptal',      'warning'],
        ];
        [$statusLabel, $statusType] = $statusMap[$auction->status] ?? ['—', 'info'];

        $conditionLabel = match ($auction->condition) {
            'new' => 'Sıfır', 'used' => 'İkinci El', 'refurbished' => 'Yenilenmiş', default => '—',
        };

        return Inertia::render('Seller/Auctions/Show', [
            'auction' => [
                'title'          => $auction->title,
                'title_short'    => Str::limit($auction->title, 50),
                'description'    => $auction->description,
                'status'         => $auction->status,
                'status_label'   => $statusLabel,
                'status_type'    => $statusType,
                'cover_url'      => $auction->cover?->url() ?? asset('assets/media/placeholder.svg'),
                'images'         => $auction->images->map(fn ($img) => [
                    'url' => $img->url(), 'is_cover' => (bool) $img->is_cover,
                ])->values(),
                'display_price'    => $auction->displayPrice(),
                'bid_count'        => $auction->bidCount(),
                'view_count'       => number_format($auction->view_count),
                'time_left'        => $auction->timeLeft(),
                'category_name'    => $auction->category?->name ?? '—',
                'starting_price'   => number_format($auction->starting_price, 0, ',', '.') . ' ₺',
                'min_bid_increment'=> number_format($auction->min_bid_increment, 0, ',', '.') . ' ₺',
                'reserve_price'    => $auction->reserve_price ? number_format($auction->reserve_price, 0, ',', '.') . ' ₺' : '—',
                'buy_now_price'    => $auction->buy_now_price ? number_format($auction->buy_now_price, 0, ',', '.') . ' ₺' : '—',
                'condition_label'  => $conditionLabel,
                'location'         => $auction->location ?? '—',
                'starts_at'        => $auction->starts_at->format('d.m.Y H:i'),
                'ends_at'          => $auction->ends_at->format('d.m.Y H:i'),
                'is_live'          => (bool) $auction->is_live,
                'stream_mode'      => $auction->stream_mode ?? 'live',
                'promo_video_url'  => $auction->promo_video_url,
                'uses_promo_video' => $auction->usesPromoVideo(),
                'is_direct_video'  => $auction->isDirectVideoFile(),
                'embed_video_url'  => $auction->usesPromoVideo() && ! $auction->isDirectVideoFile() ? $auction->embedVideoUrl() : null,
                'can_broadcast'    => $auction->canBroadcast(),
                'edit_url'         => route('seller.auctions.edit', $auction),
                'destroy_url'      => route('seller.auctions.destroy', $auction),
                'index_url'        => route('seller.auctions.index'),
                'broadcast_url'    => route('seller.auctions.broadcast', $auction),
                'stream_settings_url' => route('seller.auctions.stream-settings', $auction),
                'bids'             => $auction->bids->take(8)->map(fn ($bid) => [
                    'user_name'   => $bid->user->name,
                    'user_avatar' => $bid->user->profile_img ?? 'https://ui-avatars.com/api/?name=' . urlencode($bid->user->name) . '&size=32&background=155eef&color=fff',
                    'is_auto'     => (bool) $bid->is_auto,
                    'amount'      => number_format($bid->amount, 0, ',', '.') . ' ₺',
                    'time'        => $bid->created_at->diffForHumans(),
                ])->values(),
            ],
        ]);
    }

    public function edit(Auction $auction)
    {
        abort_unless(auth()->id() === $auction->user_id, 403);

        $auction->load('images');

        return Inertia::render('Seller/Auctions/Edit', [
            'categories' => $this->categoryOptions(),
            'auction' => [
                'id'                => $auction->id,
                'title'             => $auction->title,
                'description'       => $auction->description,
                'category_id'       => $auction->category_id,
                'condition'         => $auction->condition,
                'location'          => $auction->location,
                'starting_price'    => $auction->starting_price,
                'min_bid_increment' => $auction->min_bid_increment,
                'reserve_price'     => $auction->reserve_price,
                'buy_now_price'     => $auction->buy_now_price,
                'ends_at'           => optional($auction->ends_at)->format('Y-m-d\TH:i'),
                'has_bids'          => $auction->bidCount() > 0,
                'images'            => $auction->images->map(fn ($img) => [
                    'id'       => $img->id,
                    'url'      => $img->url(),
                    'is_cover' => (bool) $img->is_cover,
                ])->values(),
                'update_url'        => route('seller.auctions.update', $auction),
                'show_url'          => route('seller.auctions.show', $auction),
            ],
        ]);
    }

    public function update(Request $request, Auction $auction)
    {
        abort_unless(auth()->id() === $auction->user_id, 403);

        $rules = [
            'title' => 'required|string|max:120',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'required|string|max:5000',
            'min_bid_increment' => 'required|numeric|min:1',
            'reserve_price' => 'nullable|numeric|min:0',
            'buy_now_price' => 'nullable|numeric|min:0',
            'condition' => 'required|in:new,used,refurbished',
            'location' => 'nullable|string|max:100',
            'ends_at' => 'required|date|after:starts_at',
            'new_images.*' => 'nullable|image|max:4096',
            'delete_images' => 'nullable|array',
            'delete_images.*' => 'exists:auction_images,id',
        ];

        if ($auction->bidCount() === 0) {
            $rules['starting_price'] = 'required|numeric|min:1';
        }

        $data = $request->validate($rules);

        // Görev 6.5: İlana teklif geldiyse ekonomik alanlar (fiyat/rezerv/artış/bitiş) kilitlenir.
        if ($auction->bidCount() > 0) {
            $eq = fn ($a, $b) => abs((float) $a - (float) $b) < 0.001;
            $incomingEnds = \Carbon\Carbon::parse($data['ends_at'])->format('Y-m-d H:i');
            $currentEnds  = optional($auction->ends_at)->format('Y-m-d H:i');

            $economicChanged =
                ! $eq($data['reserve_price'] ?? 0, $auction->reserve_price ?? 0)
                || ! $eq($data['buy_now_price'] ?? 0, $auction->buy_now_price ?? 0)
                || ! $eq($data['min_bid_increment'], $auction->min_bid_increment)
                || $incomingEnds !== $currentEnds
                || (isset($data['starting_price']) && ! $eq($data['starting_price'], $auction->starting_price));

            if ($economicChanged) {
                return back()->with('error', 'Bu ilana teklif verildiği için fiyat, rezerv fiyat, minimum artış ve bitiş tarihi değiştirilemez. Yalnızca başlık, açıklama, konum ve görselleri güncelleyebilirsiniz.');
            }
        }

        DB::transaction(function () use ($data, $request, $auction) {
            $auction->update($data);

            if ($request->filled('delete_images')) {
                $toDelete = AuctionImage::whereIn('id', $request->delete_images)
                    ->where('auction_id', $auction->id)->get();
                foreach ($toDelete as $img) {
                    Storage::disk('public')->delete(array_filter([$img->path, $img->card_path, $img->thumb_path]));
                    $img->delete();
                }
                if (! $auction->fresh()->images()->where('is_cover', true)->exists()) {
                    $auction->images()->oldest()->first()?->update(['is_cover' => true]);
                }
            }

            if ($request->hasFile('new_images')) {
                $nextOrder = $auction->images()->max('sort_order') + 1;
                foreach ($request->file('new_images') as $file) {
                    $path = $file->store('auctions/'.$auction->id, 'public');
                    $variants = \App\Services\ImageVariantService::generate($path);
                    AuctionImage::create([
                        'auction_id' => $auction->id,
                        'path' => $path,
                        'card_path' => $variants['card'],
                        'thumb_path' => $variants['thumb'],
                        'is_cover' => $auction->images()->count() === 0,
                        'sort_order' => $nextOrder++,
                    ]);
                }
            }
        });

        return redirect()->route('seller.auctions.show', $auction)
            ->with('success', 'İlan güncellendi.');
    }

    public function destroy(Auction $auction)
    {
        abort_unless(
            auth()->id() === $auction->user_id || auth()->user()->hasRole('admin'),
            403
        );

        $hasOrders = $auction->orders()->exists();
        $hasBids   = $auction->bids()->exists();

        // Görev 6.5: Siparişi olan ilan silinemez (finansal kayıt korunur).
        if ($hasOrders) {
            $msg = 'Bu ilana ait sipariş(ler) var; ilan silinemez. Sipariş anlaşmazlığı için yönetici ile iletişime geçin.';
            if (! request()->header('X-Inertia') && (request()->wantsJson() || request()->ajax())) {
                return response()->json(['message' => $msg], 422);
            }
            return back()->with('error', $msg);
        }

        // Görev 6.5: Teklifi olan ilan hard-delete edilmez → iptal edilir ve tüm teklif sahiplerine bildirim gider.
        if ($hasBids) {
            $auction->update(['status' => 'cancelled']);

            $bidderIds = $auction->bids()->reorder()->pluck('user_id')->unique();
            \App\Models\User::whereIn('id', $bidderIds)->get()->each(function ($u) use ($auction) {
                try {
                    $u->notify(new \App\Notifications\AuctionStatusNotification(
                        $auction, 'cancelled', 'İlan satıcı tarafından iptal edildi.'
                    ));
                } catch (\Throwable $e) {
                    report($e);
                }
            });

            $msg = 'İlanda teklifler olduğu için ilan iptal edildi ve teklif sahiplerine bildirim gönderildi.';
            if (! request()->header('X-Inertia') && (request()->wantsJson() || request()->ajax())) {
                return response()->json(['message' => $msg]);
            }
            return redirect()->route('seller.auctions.index')->with('success', $msg);
        }

        $auction->update(['status' => 'cancelled']);
        $auction->delete();

        // Inertia istekleri de X-Requested-With: XMLHttpRequest gönderir; JSON dalına DÜŞMEMELİ.
        // Yalnızca gerçek (Inertia olmayan) API/ajax isteklerinde JSON döndür.
        if (! request()->header('X-Inertia') && (request()->wantsJson() || request()->ajax())) {
            return response()->json(['message' => 'İlan kaldırıldı.']);
        }

        // Soft-delete sonrası geldiği sayfaya (silinen ilanın detayına) dönersek 404 olur.
        // Her zaman ilan listesine yönlendir.
        return redirect()->route('seller.auctions.index')->with('success', 'İlan kaldırıldı.');
    }
}

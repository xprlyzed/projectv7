<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\User;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $query = trim((string) $request->get('q'));

        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $query) . '%';

        // İlanlar — başlık, açıklama, konum ve kategori adında ara (yalnızca yayınlanmış durumlar)
        $auctions = Auction::query()
            ->whereIn('status', ['active', 'ended', 'sold'])
            ->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                  ->orWhere('description', 'like', $like)
                  ->orWhere('location', 'like', $like)
                  ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like));
            })
            ->with(['category', 'cover'])
            ->latest()
            ->take(6)
            ->get()
            ->map(fn ($auction) => [
                'title'    => $auction->title,
                'type'     => 'İlan',
                'subtitle' => $auction->category?->name ?? 'Müzayede',
                'avatar'   => $auction->coverUrl(),
                'url'      => route('auctions.show', $auction->slug),
            ])->values();

        // Kullanıcılar — ad veya kullanıcı adında ara; gizli profiller (profile_public=false) hariç
        $users = User::query()
            ->where('profile_public', true)
            ->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                  ->orWhere('username', 'like', $like);
            })
            ->orderBy('name')
            ->take(5)
            ->get()
            ->map(fn ($user) => [
                'title'    => $user->name,
                'type'     => 'Kullanıcı',
                'subtitle' => '@' . $user->username,
                'avatar'   => $user->profile_img,
                'url'      => route('profile.public', $user->username),
            ])->values();

        return response()->json($auctions->concat($users)->values());
    }
}

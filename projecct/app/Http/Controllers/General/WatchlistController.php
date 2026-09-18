<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use Illuminate\Http\Request;

/**
 * Görev 5: Watchlist (favoriler) toggle. Frontend'deki kalp ikonu bu endpoint'i çağırır.
 */
class WatchlistController extends Controller
{
    /** POST /auctions/{auction}/watch — izlemeye al / izlemekten çıkar (toggle) */
    public function toggle(Request $request, Auction $auction)
    {
        $user = $request->user();

        // Kendi ilanını izlemeye almak anlamsız
        if ((int) $auction->user_id === (int) $user->id) {
            return response()->json([
                'message' => 'Kendi ilanınızı favorilere ekleyemezsiniz.',
            ], 422);
        }

        $exists = $user->watchlist()->where('auctions.id', $auction->id)->exists();

        if ($exists) {
            $user->watchlist()->detach($auction->id);
            $watching = false;
        } else {
            // syncWithoutDetaching → çift kayıt/yarış durumunda güvenli (idempotent)
            $user->watchlist()->syncWithoutDetaching([$auction->id]);
            $watching = true;
        }

        return response()->json([
            'watching' => $watching,
            'count'    => $auction->watchlist()->count(),
        ]);
    }

    /** DELETE /auctions/{auction}/watch — açıkça izlemekten çıkar */
    public function destroy(Request $request, Auction $auction)
    {
        $request->user()->watchlist()->detach($auction->id);

        return response()->json([
            'watching' => false,
            'count'    => $auction->watchlist()->count(),
        ]);
    }
}

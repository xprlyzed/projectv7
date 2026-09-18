<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * auction.{id} — PresenceChannel
 *
 * Hem satıcı (auctions blade) hem izleyici (auctionsnew blade) bu kanala katılır.
 * Döndürülen dizi Echo presence API'sinde user olarak görünür.
 */
Broadcast::channel('auction.{id}', function ($user, $id) {
    // Yalnızca var olan bir müzayedenin presence kanalına katılıma izin ver
    if (! \App\Models\Auction::whereKey($id)->exists()) {
        return false;
    }

    return [
        'id'   => $user->id,
        'name' => $user->name,
    ];
});

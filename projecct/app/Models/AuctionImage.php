<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuctionImage extends Model
{
    protected $fillable = ['auction_id','path','card_path','thumb_path','is_cover','sort_order'];

    public function auction() { return $this->belongsTo(Auction::class); }
    public function url(): string { return asset('storage/' . $this->path); }
    public function cardUrl(): string { return asset('storage/' . ($this->card_path ?: $this->path)); }
    public function thumbUrl(): string { return asset('storage/' . ($this->thumb_path ?: $this->path)); }
}

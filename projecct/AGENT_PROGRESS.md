# AGENT PROGRESS — Canlı Müzayede Görevleri (8 Görev)

> Ajan (E1) oturumları arası hafıza. Mevcut `PROGRESS.md` önceki geliştiriciye ait
> (farklı görev seti) — o dosyaya dokunmadım. Bu dosya BENİM üstlendiğim
> "Canlı Yayın + Bildirim + Sipariş Yaşam Döngüsü + Mobil UX + Review" görevlerini takip eder.
> Her oturumda önce bunu oku.

Son güncelleme: 2026-06 — Faz 1 (G2,G3,G4,G5) + G6 backend TAMAM; frontend rebuild + testing_agent BEKLİYOR.

---

## 0) ORTAM / ÇALIŞTIRMA

- **Uygulama**: "Artirdim" canlı açık artırma. Laravel 12 + Inertia + Vue 3, MySQL/MariaDB, Spatie roles.
- **Kod kökü**: `/app/projecct`. (`/app/backend|frontend|memory|tests` Emergent iskelesi, ALAKASIZ.)
- **Gerçek zamanlı**: LiveKit (video + data channel) + Reverb (kısmen legacy).
- **Servis**: Pod imajında PHP/MariaDB YOK, restart'ta kaybolur. `/app/start_laravel.sh` kendi kendini
  onarır (PHP+MariaDB+Composer kurar, MariaDB `/app/.mariadb-data` kalıcı datadir, migrate/seed,
  `queue:work`, `php artisan serve :3000`). Supervisor `frontend` = `yarn start` →
  `/app/frontend/package.json` "start" = `bash /app/start_laravel.sh`.
- **Frontend build**: Vue değişince MUTLAKA `cd /app/projecct && npm run build` (dev vite YOK).
- **Test hesapları**: admin@test.com / seller@test.com / buyer@test.com — şifre `password`.
- **Testler**: `cd /app/projecct && php artisan test` → şu an **59 test geçiyor** (sqlite :memory:).

---

## 1) DURUM TABLOSU

| # | Görev | Durum |
|---|-------|-------|
| 1 | Canlı satış geri sayımı Vue render + geç-katılan senkron + reconnect + ret sebepleri + izleyici sayısı | **KISMEN** — server tarafı bitti, Vue render YAPILMADI |
| 2 | Satış finalizasyonu sunucuya (para güvenliği) | ✅ TAMAM |
| 3 | Sunucu tarafı yayın durumu (heartbeat/reap/is_live filtresi) | ✅ TAMAM (istemci heartbeat gönderimi G1 ile) |
| 4 | Eksik bildirimler + mapping | ✅ backend TAMAM / ⚠️ bell badge UI YAPILMADI |
| 5 | Watchlist + kalp butonu | ✅ TAMAM |
| 6 | Sipariş yaşam döngüsü otomasyonu | ✅ backend TAMAM / ⚠️ admin buton build+test bekliyor |
| 7 | Mobil UX | ❌ YAPILMADI |
| 8 | Review sistemi yeniden tasarımı | ❌ YAPILMADI |

---

## 2) YAPILANLAR (özet + dosyalar)

### G2 — Satış finalizasyonu sunucuda ✅
- `app/Jobs/FinalizeSaleJob.php` (YENİ): 10sn delay, `lockForUpdate`, token + iptal kontrolü,
  ÇALIŞMA ANINDAKİ gerçek en yüksek teklif, rezerv kontrolü, `OrderService::createFromWinningBid`.
- `BroadcastController::startSellCountdown` → DB'ye countdown durumu + job dispatch (delay).
- `BidController::store` → yeni teklif aktif geri sayımı `countdown_cancelled_at` ile İPTAL eder + `sell-cancelled` publish.
- Migration `...000001`: countdown_started_at/ends_at/bid_id/cancelled_at.

### G3 — Yayın durumu senkronu ✅
- `BroadcastController::heartbeat` + route `POST seller/auctions/{auction:slug}/heartbeat`.
- `liveStatus` → açılışta `last_heartbeat_at` + takipçilere `SellerWentLiveNotification`.
- `app/Console/Commands/ReapStaleStreams.php` (`streams:reap`, everyMinute) — 60sn ölü yayını kapatır.
- `BrowseController::live` → `where('is_live', true)`.
- Migration: last_heartbeat_at. YAPILMADI: LiveKit webhook (ops.), istemci heartbeat gönderimi (G1 ile).

### G4 — Bildirimler ✅ backend
- `OutbidNotification` (4.1) → `BidController::notifyPreviousTopBidder` (önceki en yüksek teklif sahibi).
- `EndingSoonNotification` (4.2) + `NotifyEndingSoon` (`auctions:notify-ending-soon`, everyFiveMinutes),
  1h/5m pencere, `notified_1h_at/5m_at` tekilleştirme, alıcı = teklif verenler ∪ izleyenler.
- `SellerWentLiveNotification` (4.3, sadece db).
- `NotificationController` mapping: yeni tipler + `$data['url']` link önceliği (order/ticket_reply düzeldi).
- YAPILMADI: `AppLayout.vue` bell — okunmamış badge + dropdown açınca hepsini okundu YAPMAMA.

### G5 — Watchlist ✅
- `WatchlistController::toggle/destroy` + route `POST/DELETE /auctions/{auction:slug}/watch`.
- `Watchlist` modeli `$table='watchlist'` (bug fix: Laravel `watchlists` çıkarıyordu).
- `BidController::show` → `watch_url` + `is_watching`. `liveState` → `countdown` + `sold` (G1 geç-katılan senkron için).
- Frontend: `Show.vue` başlık şeridinde optimistik kalp butonu + `auctions-show.css`.

### G6 — Sipariş yaşam döngüsü ✅ backend
- Migration `...000002`: orders(ship_by_at, dispute_window_ends_at, reoffer_of), users(payment_misses, shipping_violations).
- `OrderService`:
  - `expireUnpaidOrders(48h)` — **DİKKAT**: orders.auction_id UNIQUE → yeni satır DEĞİL, aynı sipariş
    satırı sıradaki uygun teklife DEVREDİLİR (`reoffer_of`=ödemeyen id). Sıradaki yoksa iptal + ilan `ended`.
    `payment_misses`++ (askıya alma yok).
  - `cancelUnshippedOrders()` — `ship_by_at` geçmiş `paid` → iptal + tam iade + satıcı `shipping_violations`++.
  - `reverseRelease()` — completed/released + 15g pencere açıkken admin geri alır.
  - `tryHoldEscrow` → `ship_by_at = paid+3g`. `markShipped` → `auto_release_at = now+15g` (eski 7g).
  - `confirmDelivered` → `dispute_window_ends_at = now+15g`.
- Komutlar: `orders:expire-unpaid` (6saatte), `orders:cancel-unshipped` (saatlik).
- 6.4 admin: `Admin/OrderController::cancel/reverse` + route + show payload flags;
  `Admin/Orders/Show.vue` iptal/reverse butonları (prompt sebep).
- 6.5: `Seller/AuctionController::update` teklif varsa ekonomik alan kilidi (reddeder);
  `destroy` siparişli ilan silinemez, teklifli ilan `cancelled`+bildirim.

### Testler (YENİ, hepsi geçiyor)
- LiveSaleFinalizationTest (4), BidNotificationAndCountdownTest (2),
  WatchlistAndStreamReapTest (3), OrderLifecycleTest (4).

### Kullanıcı UI geri bildirimi (onaylandı)
- Favoriler grid küçültüldü (`Buyer/Favorites.vue`); buyer sidebar aktif renkleri (`AppLayout.vue`);
  auction detay "aktif/pasif" badge kaldırıldı (`Show.vue`); favori butonu şık/küçük (`auctions-show.css`).

---

## 3) HEMEN SIRADAKİ (önce bunlar)

1. **`npm run build`** — Admin/Orders/Show.vue (6.4 butonları) düzenlemesinden sonra henüz build alınmadı.
2. **testing_agent** ile e2e doğrulama YAPILMADI (Faz 1 + G6). Kapsam: watchlist toggle+favoriler,
   bildirim mapping/link, canlı listeleme is_live, admin iptal/iade+reverse, ekonomik alan kilidi + silme koruması.
   Not: LiveKit gerçek yayın gerektiren akışlar (LIVEKIT_* boş → publish no-op) PHPUnit ile doğrulandı.

---

## 4) YAPILMAYANLAR (sonraki fazlar — kısa yol haritası)

### G1 — Canlı yayın Vue render (frontend, büyük)
- `resources/js/Components/MobileLiveRoom*.vue` + masaüstü canlı oda: LiveKit `onData` içine
  `sell-countdown`/`auction-sold`/`sell-cancelled` case'leri. Geri sayım component-state (z-index fix),
  süre `Math.max(0, endsAt - Date.now())`. Satıldı ekranı fullscreen içine.
- `Show.vue`'daki eski imperatif DOM enjeksiyonu (`__showCountdown/__cdEl/__cdTimer`) + varsa
  `public/assets/js/` legacy paralel geri sayım TEMİZLE. İstemci satışı TETİKLEMEZ (job yapıyor).
- Geç-katılan senkron: `liveState.countdown` (backend HAZIR) çekilip senkronlanacak.
- Reconnecting göstergesi; ret sebebi 422 vs 409 ayrımı; izleyici sayısı tek kaynak (LiveKit numParticipants).
- İstemci heartbeat (~20sn POST) + `pagehide/beforeunload` `sendBeacon` (G3 istemci parçası).

### G7 — Mobil UX
- Alt sabit tab-bar + safe-area; toast/modal `env(safe-area-inset-*)` + z-index; 44×44 dokunma;
  breakpoint `--bp-sm/md/lg` (detaydaki izole 1100px); galeri swipe + lightbox zoom + onclick→Vue;
  iOS `visualViewport` klavye.

### G8 — Review
- Sipariş-bazlı (unique `order_id`); 14 gün sonra kilit; satıcı 1 cevap (reply); eski veri migration ile
  order'a eşleyerek taşı (eşleşmeyeni logla+atla).

---

## 5) NOTLAR / KAPSAM DIŞI

- Queue: `database` + bootstrap `queue:work` → ShouldQueue gerçekten işleniyor (sync değil).
- LiveKit yapılandırılmadı (`LIVEKIT_*` boş) → `LiveKitPublisher::publish` no-op; gerçek video/data bu ortamda çalışmaz.
- Reverb `null` → broadcast no-op (try/catch'li).
- DOKUNULMADI (kapsam dışı): auth/login rate-limit/Google OAuth/session/güvenlik header,
  EFT-havale & para çekme onayı, escrow guard, komisyon validasyonu, satıcı onay/red, sanal POS.

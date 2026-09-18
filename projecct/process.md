# ═══════════════════════════════════════════════════════════════════
# SABİT KURAL — PUSH HEDEFİ
# ═══════════════════════════════════════════════════════════════════
# PUSH HEDEFİ SADECE VE HER ZAMAN: https://github.com/xprlyzed/projectv1
# — YENİ REPO OLUŞTURULMAYACAK, BAŞKA REPO'YA (projectv2, projectv9, projectv10 dahil) PUSH EDİLMEYECEK.
# ═══════════════════════════════════════════════════════════════════


# ═══════════════════════════════════════════════════════════════════
# SABİT KURAL — REPO YAPISI KONTROLÜ (HER GÖREVDEN ÖNCE + HER PUSH ÖNCESİ)
# ═══════════════════════════════════════════════════════════════════
# Amaç: Proje yapısı bir daha ASLA bozulmasın (nested .git / submodule / laravel_project sarmalayıcı klasör).
#
# HER YENİ GÖREVE BAŞLAMADAN ve HER PUSH'TAN HEMEN ÖNCE aşağıdaki kontrol listesini çalıştır:
#   1) find . -maxdepth 3 -name ".git" -type d       → SADECE  ./.git  çıkmalı (kökte tek .git).
#   2) git ls-files -s | grep "^160000"               → BOŞ olmalı (submodule referansı yok).
#   3) cat .gitmodules                                → Dosya OLMAMALI (vendor/node_modules hariç).
#   4) Kök dizinde app/ routes/ public/ composer.json artisan DOĞRUDAN durmalı (laravel_project/auction-project sarmalayıcısı YOK).
#   5) Yeni bir klasörü clone/kopyalayarak eklediysen: o klasörün kendi .git'ini MUTLAKA sil (rm -rf <klasor>/.git).
#   6) Push öncesi git status'ta dosyalar TEK TEK listelenmeli (tek satır "new file: <klasor>" submodule referansı DEĞİL).
#
# Bu kural bozulmadıkça, her doğrulamadan sonra buraya tarih + "temiz" notu düş (aşağıya ekle).
#
# DOĞRULAMA GEÇMİŞİ:
#   - 2026-09-03: Repo tarandı → tek ./.git, submodule yok, .gitmodules yok, kök DÜZ (app/, routes/, composer.json kökte).
#                 GitHub (xprlyzed/projectv10) kökü de DÜZ teyit edildi. Durum: TEMİZ.
# ═══════════════════════════════════════════════════════════════════


# ═══════════════════════════════════════════════════════════════════
# BÖLÜM 2 — CANLI YAYIN DENEYİMİ (TikTok/Kick seviyesi) — GÖREV LİSTESİ
# ═══════════════════════════════════════════════════════════════════
# TEŞHİS KÖK NEDEN (2026-09-03): LiveKit yayınının yanında ESKİ bir stream kontrolü
# (auction-show.js: WebRTC handleOffer + _showLiveStream/_hideLiveStream + pollLiveState'in
# is_live'a göre video temizlemesi) AYNI #liveVideo elementini yönetiyor. pollLiveState her 2.5sn
# `!is_live` görünce _hideLiveStream() → video.srcObject=null + "yayın başlatılmadı" banner.
# → Freeze + yanlış mesajın ana kaynağı: legacy ile LiveKit çakışması. Çözüm: LiveKit tek kaynak.
#
# [x] G1 [KRİTİK] "Yayın başlatılmadı" yanlış mesajı → 3-durumlu (checking/live/offline) nötr spinner;
#          is_live + grace mantığı; legacy'nin video/cam-off kontrolü no-op'landı.
#          KANIT: testing_agent iteration_1 → t0-3.5sn stream-checking=true & stream-offline=false; 8sn grace sonrası offline (beklenen).
# [~] G2 [KRİTİK] Freeze → legacy WebRTC/video kontrolü DEVRE DIŞI (auction-show.js handleOffer/_show/_hideLiveStream no-op);
#          publisher encoding/simulcast/degradation tuning (Broadcast.vue publishDefaults). KOD TAMAM + build'de.
#          NOT: Gerçek kamera yayınıyla throttling altında freeze testi headless'te SİMÜLE EDİLEMEZ → gerçek yayıncı ile doğrulanacak.
# [x] G3 [KRİTİK] Mobil tam-ekran dikey video + birleşik (mesaj+teklif) kayan feed +
#          alt sabit input (Sohbet/Teklif toggle + onay) + üst overlay + otomatik bağlan (MobileLiveRoom.vue).
#          + Marka renkleri (#155EEF mavi / #10b981 yeşil), kapat→FAB→tekrar aç, mobilde eski satır-içi sohbet/teklif GİZLİ, masaüstü etkilenmedi.
#          KANIT: testing_agent iteration_2 → 6/6 kriter PASS (chat gönder, teklif onay 31.200₺, close→FAB→reopen, desktop non-regression, marka rengi).
# [ ] G4 [YÜKSEK] Masaüstü canlı deneyimini (video + yan panel) rahatlat (mevcut üzerine).
# [ ] G5 [ORTA] Bağlantı kalite göstergesi (ConnectionQuality).
# [ ] G6 [ORTA] Yayıncı tek-tık başlat / izleyici otomatik bağlan sadeleştirme.
# ═══════════════════════════════════════════════════════════════════


# artirdim.com — Realtime & UI İyileştirme Süreç Takibi (process.md)

> Durum: [ ] yapılmadı · [~] devam · [x] tamam (KANITLI) · [B] bloke/ertelendi
> Karar: Realtime taşıyıcı = **LiveKit veri kanalı** (senkron `LiveKitPublisher`, queue gerekmez).
>        DM mesajları şu an polling; anlıklaştırılacak.
> KURAL: Bir madde ancak testing_agent (veya iki-kullanıcı gerçek test) KANITIYLA [x] olur.
>        Kanıt = değişen dosyalar + build/cache adımı + test sonucu.

---

## MİMARİ NOTLARI (ADIM 0 taramasında doğrulandı — 2026-09)
- Çift asset pipeline: `app.blade.php` → (a) statik `public/assets/**` (build GEREKMEZ: `auction-show.js`,
  `style.bundle.css`, `scripts.bundle.js`) + (b) `@vite(legacy.css, app.js)` (build GEREKİR).
  `legacy.css` → `theme-new.css`, `auction-show.css` vb. @import ediyor.
  → UI değişikliğinde: doğru pipeline'ı düzenle, gerekiyorsa `yarn build` + `php artisan view:clear config:clear`.
- İzleyici realtime: `public/assets/js/custom/auction-show.js` içinde `window.__onRemoteBid`/`__onRemoteChat`
  tanımlı; LiveKit `onData` → bunlar. Yedek: `pollLiveState` 2.5sn, `pollChat` 3sn.
- Broadcast=log + queue worker YOK → `broadcast(new BidPlaced)` no-op; gerçek taşıyıcı LiveKit.
- LiveKit: `config/services.php` → env. Bağlantı testi BAŞARILI. `.env` gitignore'lu, commit/log edilmedi.
- Repo: submodule/iç içe YOK; geçici `projectv8` gitlink temizlendi → yapı düz.

---

## KRİTİK

- [x] 1. Teklif anlıklığı — o ilanı görüntüleyen HERKESE (satıcı + tüm izleyiciler) sayfa yenilemeden anlık:
      güncel en yüksek teklif + teklif sayısı + teklif geçmişi. **KANITLI (testing_agent iteration_8, 2026-09):**
      2 ayrı oturum (buyer+admin), sayfa yenilemeden #live-price 5.568→99.999→150.000 ₺, #live-bid-count 7→9,
      feed dedup doğru, gecikme 0.46–0.47sn, JS/PHP hatası yok. Dosyalar zaten mevcuttu (kod değişmedi).
- [x] 2. Mesajlaşma anlıklığı — DM'ler artık LiveKit veri kanalıyla `dm-{conversationId}` odasına anlık yayınlanıyor;
      polling (2500ms) yalnızca yedek. **KANITLI (testing_agent iteration_9, 2026-09):** çift yönlü iletim
      0.26s/0.79s (<1sn), dedup doğru, 4 ardışık mesajda sıra korundu, /livekit/dm-token 200, hata yok.
      Dosyalar: LiveKitPublisher::publishToRoom, LiveKitDmTokenController (+route), MessageController@store,
      Messages/Index.vue (connectDm+watch), useLiveKit.js (fetchToken/tokenUrl).
- [x] 3. Sekme arka plan→ön / route değişimi / ağ kesintisi→dönüş → LiveKit otomatik yeniden bağlanıyor.
      **KANITLI (testing_agent iteration_10, 2026-09):** route değişimi 0.43s, offline→online 0.43s,
      sekme görünürlük DM 0.25s; hepsinde manuel reload YOK, dedup doğru, kalıcı hata yok.
      Dosya: useLiveKit.js connectRoom reconnect katmanı (Disconnected + visibilitychange + online/focus).
- [x] 4. Satıcının 10sn sayacı çalışırken mobilde alıcılar teklif vermeye DEVAM edebiliyor.
      **KANITLI (testing_agent iteration_11, 2026-09):** izleyici sayacı tam-ekran overlay yerine kompakt üst
      yeşil banner (44px, pointer-events:none); mobil teklif çubuğu görünür+tıklanabilir (bid-urgent vurgu),
      alıcı sayaç sırasında 350.000 ₺ teklif verebildi, elementFromPoint submit'i en üstte doğruladı.
      Dosyalar: Show.vue __showCountdown/__removeCd, theme-new.css (.lk-sale-banner, .bid-urgent).

## YÜKSEK

- [x] 5. LiveKit uçtan uca: **KANITLI (testing_agent iteration_12, 2026-09):** sahte kamerayla satıcı yayın
      açtı (1280x720, errorMsg yok), izleyici #liveVideo'da uzak track aldı (640x360, oynatılıyor),
      toggleCam detach/re-subscribe çalıştı. Bağlantı/token/veri kanalı zaten #1-#4'te kanıtlıydı.
- [x] 6. Satıcı canlı yayın ekranı yeniden tasarlandı: üst "Canlı Özet" şeridi (Güncel Teklif, Son Teklifi Veren,
      Teklif Sayısı, Kalan Süre) yeni teklifte anlık güncellenir; ferah düzen, mobilde 2 sütun.
      **KANITLI (testing_agent it13 A1/A2/A3 + it14 B1 PASS).** Ayrıca alıcı sayacı artık medya alanı ÜZERİNDE
      (masaüstünde header'da değil, y≈220) — kullanıcı şikayeti giderildi.
      Dosyalar: Seller/Broadcast.vue (hero+style), BroadcastController@show (current_price+ends_at_ts),
      Show.vue __showCountdown (medya alanına anchor), theme-new.css (.lk-sale-banner--onvideo).
- [ ] 7. Alıcı mobil ekranları (canlı yayın + teklif verme): büyük dokunma hedefleri, "Teklif Ver" sabit/erişilebilir,
      karmaşa yok. Yansıdığı KANITLANACAK.

## TASARIM YÖNÜ (tüm yeniden düzenlemelerde)
- Mevcut renk paleti/marka dili korunur (mavi accent, açık arkaplan, rounded-xl kartlar).
- Ferah, şık, nefes alan düzen; sıkışık/iç içe bileşenlerden kaçın.
- Kullanıcı hiçbir noktada butona ulaşamama/engellenme yaşamamalı.
- Hem masaüstü hem mobil ayrı ayrı test edilir.

---

## ÇALIŞMA GÜNLÜĞÜ (kanıtlı kayıtlar buraya)

### ADIM 0 — Mevcut durum taraması (2026-09) [x]
- Repo yapısı, process.md `[x]` maddeleri, realtime/messaging/countdown/LiveKit/UI-pipeline kod üzerinden doğrulandı.
- Bulgular yukarıdaki "MİMARİ NOTLARI" + görev listesine işlendi. `projectv8` gitlink temizlendi.
- LiveKit anahtarları `.env`'e eklendi (maskeli), bağlantı testi BAŞARILI.

### (Önceki oturumdan taşınan iddialar — kanıt yeniden alınana kadar [ ])
- Görev #1: "iteration_6 PASS" iddiası vardı; işaret çelişkili olduğu için sıfırlandı, yeniden doğrulanacak.
- Görev #2: backend LiveKit chat + DM polling 1500ms iddiası; DM hâlâ gecikmeli → yarım kabul edildi.


---

## OTURUM 2026-09 (B) — ADIM 0 yeniden tarama + repo + yeni revizyonlar

### ADIM 0 bulguları (KOD ile doğrulandı, işaretlere güvenilmedi)
- **Premise düzeltmesi:** "Alıcı tarafı yapılmadı" iddiası YANLIŞ. Alıcı canlı izleme sayfası VAR:
  `resources/js/Pages/Auctions/Show.vue` + `public/assets/js/custom/auction-show.js`.
  Satıcı ile AYNI realtime altyapısı: `useLiveKit.connectRoom` + `auction-{id}` LiveKit odası.
  Teklif anlık (`__onRemoteBid`), sohbet (`__onRemoteChat`), satış sayacı (medya-üstü non-blocking),
  viewer LiveKit izleme, mobil sticky teklif çubuğu — hepsi alıcıda mevcut ve çalışıyor.
- **Satıcı realtime (#1-6):** kod düzeyinde mevcut. Zayıf nokta: broadcaster yayın odası (`goLive` ham `new Room()`)
  kendi reconnect sarmalayıcısını kullanmıyor (izleyici/data odalarında var).
- **Pin (mesaj sabitleme): HİÇBİR yerde yok** — `auction_chat_messages`: id, auction_id, user_id, message, is_seller, ts. → tamamen yeni.
- **Satıcı `Broadcast.vue`:** `.bc-root{max-width:1400px}` (dar), kontroller video ALTINDA düz flex sıra (floating değil).
- **LiveKit** sunucu bağlantı testi: BAŞARILI (anahtarlar loglanmadı, `.env` gitignore'lu).

### Repo temizliği (2026-09-B) [x]
- Yorum 2 uygulandı: kökten `backend/ frontend/ tests/ test_reports/ memory/` + 5 kopya .md
  (CANLI_YAYIN/INERTIA/KURULUM/PROGRESS/test_result) silindi. Kök = `laravel_project/` + git + README.
  Submodule/iç içe yok; `laravel_project` kendi .git'i yok. Silmeler yeni commit'e yansır (geçmiş korunur).
- app-loader: kullanıcı "sağlıklı çalışıyor" dedi → bring-up geçici değişiklikleri geri alındı, orijinal korunur.

## YENİ REVİZYONLAR (öncelik sırası: a)

### SATICI CANLI YAYIN SAYFASI (Broadcast.vue)
- [x] R1 [KRİTİK] Genişlik: `.bc-root` 1400px sınırı kaldırıldı → full-width. AppLayout'a sayfa-bazlı
      akışkan kap eklendi (fluidPages=['Seller/Broadcast','Auctions/Show'] → container-fluid).
      **KANITLI (testing_agent it1):** #kt_app_content_container 1920 viewport'ta 1600px (container-xxl ~1320'ye karşı),
      sidebar sağında neredeyse tüm alanı kullanıyor. Masaüstü+mobil geçti, hata yok.
- [x] R2 [YÜKSEK] Floating kontrol çubuğu: kontroller `.bc-fab` ile video ÜZERİNE taşındı (yarı saydam, blur,
      ortalanmış, ikon+etiket, hover/active geri bildirimli; canlı modda kompakt ikon-only).
      **KANITLI (testing_agent it1):** [data-testid=broadcast-controls] position:absolute, parent .bc-video-wrap,
      bbox video kutusu içinde; ön-yayın butonları (preview/go-live/link) görünür+tıklanabilir; mobilde erişilebilir.
      Mobil kozmetik (ipucu örtme + hero kırpma) düzeltildi.
- [ ] R3 [YÜKSEK] Mesaj pin: `auction_chat_messages` + `is_pinned, pinned_at`; pin/unpin endpoint (yalnız satıcı);
      LiveKit `pin` event; satıcı+alıcı UI'da en üstte sabit "📌 Sabitlenmiş" mesaj; realtime yayılır.
- [ ] R4 [YÜKSEK] İyileştirmeler (hepsi onaylandı): video-üstü yeni-teklif toast; CANLI rozeti + bağlantı kalitesi göstergesi;
      sohbet moderasyonu (kullanıcı susturma + mesaj silme); mobilde sohbet collapse/expand.

### ALICI CANLI İZLEME SAYFASI (Auctions/Show.vue)
- [x] R5 [KRİTİK] Alıcı izleme sayfası satıcıyla tutarlı + full-width'e alındı; MEVCUT sayfa revize edildi.
      Video 16/9 & yükseklik-kapalı (max 72vh) & ortalı (satıcıyla tutarlı). **KANITLI (testing_agent it2):**
      teklif→anlık fiyat güncelleme reload'suz (6.324→6.426→6.528), bid feed anlık. Ayrıca cam-off bannerı hatası
      düzeltildi: video UI yalnızca gerçek video TrackSubscribed'da açılır (yayın yokken banner görünür, sahte CANLI yok)
      **KANITLI (testing_agent it3, masaüstü+mobil).**  NOT: "pin gösterimi" kısmı R3'e bağlı (pin henüz yok) → R3'te tamamlanacak.
- [x] R6 [YÜKSEK] Alıcı UI/UX: full-width geniş video; sağ kolon teklif paneli masaüstünde STICKY (Teklif Ver hep görünür);
      mobil alt sabit teklif çubuğu (chip'ler 40px dokunma hedefi), input + "Teklif Ver" erişilebilir, yatay taşma yok.
      **KANITLI (testing_agent it2, masaüstü 1920 + mobil 390).**  Sohbet + sabit mesaj gösterimi R3 (pin) ile gelecek.

> Test kuralı: her madde testing_agent (2 kullanıcı, gerçek LiveKit) kanıtıyla [x]. Kanıt yoksa [x] YOK.

# ═══════════════════════════════════════════════════════════════════
# BÖLÜM 5 — MASAÜSTÜ TAM EKRAN CANLI YAYIN  (2026-09-04) ✅ KANITLI
# ═══════════════════════════════════════════════════════════════════
# - YENİ dosya: resources/js/Components/DesktopLiveRoom.vue (mobilin masaüstü kardeşi).
#   Geniş/yatay yerleşim: SOLDA geniş video (~1520px) + SAĞDA dikey sohbet/teklif paneli (~400px).
#   İşlev MobileLiveRoom ile birebir (birleşik feed, Sohbet/Teklif toggle, hızlı çip, tek-dokunuş onay, sabit bilgi kutusu).
# - Paylaşımlı LiveKit odası (:room=liveRoomRef): kendi bağlantısını KURMAZ/KAPATMAZ; tam ekran SADECE görsel geçiş.
#   onMounted attach+listener, onBeforeUnmount detach+off. 5x aç/kapa → reconnect yok, "bağlanılıyor" flaşı yok.
# - Show.vue: desktopLiveActive state + enter-fullscreen-desktop ikonu (v-if !isMobile && is_live && !has_finished && !is_owner)
#   + <Teleport><DesktopLiveRoom/></Teleport>.  MobileLiveRoom.vue'ya DOKUNULMADI (mobil %100 korundu).
# - testing_agent iteration_3: 7/7 PASS, 0 console hatası, mobil regresyon temiz.
# - Ek: Önizlemede Laravel Debugbar KAPATILDI (.env DEBUGBAR_ENABLED=false) — mobilde alt input'u kapatan overlay giderildi.
# ═══════════════════════════════════════════════════════════════════

# ═══════════════════════════════════════════════════════════════════
# EK İYİLEŞTİRMELER (2026-09-04) ✅ KANITLI (testing_agent iteration_4, 6/6 PASS, 0 console hatası)
# ═══════════════════════════════════════════════════════════════════
# 1) SES (mute) FIX: useLiveKit.js — uzak audio track'leri gizli <audio> elemanlarına bağlanır;
#    room.setStreamMuted()/isStreamMuted() API + connectRoom({startAudioMuted:true}). Eskiden mute
#    #liveVideo.muted'ı değiştiriyordu ama ses ayrı elemanda olduğu için çalışmıyordu. Inline + Mobil + Masaüstü mute artık gerçek sesi kontrol ediyor. (DM/yayıncı davranışı korundu — opt-in.)
# 2) TAM EKRAN İKONU: mobil+masaüstü tam ekran odası tetikleyicisi [ ] (bi-fullscreen) ikonuna geçirildi;
#    gereksiz native browser-fullscreen butonu (fs-btn/toggleFullscreen) kaldırıldı.
# 3) FULL HD YAYIN: Broadcast.vue — 1080p capture + simulcast [h360,h720]+h1080 encoding + musicHighQuality ses
#    + AEC/NS/AGC. (Eskiden h180/h360 = maks 360p idi.)
# 4) YUMUŞAK GEÇİŞ: MobileLiveRoom/DesktopLiveRoom videosu opacity 0→1 fade-in (@loadeddata/@playing,
#    1.2sn güvenlik yedeği). Kullanıcı BLURLU poster'ı beğenmedi → tamamen KALDIRILDI (.mlr-poster/.dlr-poster yok).
# ═══════════════════════════════════════════════════════════════════

# ═══════════════════════════════════════════════════════════════════
# KOD TEMİZLİĞİ + LOADER CSS (2026-09-04) ✅ KANITLI (testing_agent iteration_5, %100 PASS, 0 console hatası)
# ═══════════════════════════════════════════════════════════════════
# YAPILAN (davranış değişmedi — pure refactor):
#  - Bölüm 4 LOADER: app.blade.php <head> içindeki loader <style> bloğu KALDIRILDI → resources/css/loader.css
#    (legacy.css'e @import edildi, derlenmiş legacy bundle'da). Layout'ta artık <style> YOK; sadece #app-loader markup kaldı.
#    legacy.css üstündeki yorum bloğu da silindi. Loader'ın görsel davranışı AYNI.
#  - MobileLiveRoom.vue + DesktopLiveRoom.vue: <style scoped> → harici <style scoped src="./X.css"> (MobileLiveRoom.css,
#    DesktopLiveRoom.css). SCOPE KORUNUR (regresyon yok). CSS yorumları (/* */) ve template HTML yorumları (<!-- -->) SİLİNDİ.
#
# MİMARİ KARAR (gerekçe): Vue component stilleri global CSS'e taşınMADI; bunun yerine `<style scoped src="external.css">`
#  kullanıldı. Böylece hem "Vue dosyasında <style> içi CSS olmasın" kuralı sağlandı hem de Vue scope izolasyonu (data-v)
#  korunarak diğer sayfalarda çakışma/regresyon riski sıfırlandı. Global CSS'e dökmek scope kaybı + geniş regresyon riski taşırdı.
#
# KAPSAM DIŞI (bu turda YAPILMADI — büyük/riskli, ayrı bir temizlik turu gerektirir; onayla yapılacak):
#  - Show.vue ve Seller/Broadcast.vue: çok sayıda inline style + yorum var. Inline→class taşınması + <style scoped src>
#    çıkarımı geniş ve davranış-riskli; bu feature turunda kapsam dışı bırakıldı (mevcudu bozmamak için).
#  - Proje geneli diğer .vue / blade / CSS / Controller dosyaları: bu turda taranmadı. Öneri: aynı `<style scoped src>`
#    deseni + inline→class + yorum temizliği dosya-dosya, her adımda test ile uygulanmalı.
# ═══════════════════════════════════════════════════════════════════

# ═══════════════════════════════════════════════════════════════════
# PROJE GENELİ KOD TEMİZLİĞİ — ADIM 1-4 TAMAMLANDI (2026-09-04) ✅ KANITLI
# ═══════════════════════════════════════════════════════════════════
# Adım 1: resources/css/*.css içindeki /* */ yorumları silindi (8 dosya).
# Adım 2: .vue içindeki <style scoped> → harici <style scoped src="./Base.css"> (6 dosya; scope korunur).
# Adım 3: .vue template'lerindeki <!-- --> yorumları silindi (8 dosya).
# Adım 4: 32 .vue dosyasındaki 440 STATİK inline style="..." → generate edilmiş scoped class (.il-<hash8>);
#         karşılık gelen CSS her component'in harici scoped .css dosyasına eklendi (341 benzersiz class).
#         Dinamik :style bağlamaları (59 adet) DEĞİŞTİRİLMEDİ. Script: /tmp/inline_to_class.py (tokenizer;
#         yalnızca <template> bölgesinde, tırnak-duyarlı; <script>'e dokunmaz).
# KANIT: yarn build (Vue derleyicisi katı) PASS; testing_agent iteration_6 (Adım1-3, %100) + iteration_7
#        (Adım4, 14 sayfa gezildi, %100 regresyonsuz, 0 pageerror). Specificity düşüşü hiçbir sayfada bozulma yaratmadı.
# BİLİNÇLİ İSTİSNA: 2 adet style= <script> içindeki SweetAlert HTML string'lerinde bırakıldı (Vue markup değil,
#        runtime modal HTML; component scope dışında render edildiği için scoped class işe yaramaz).
# NOT: testing_agent'ın "GET /seller/profile/edit 405" tespiti YANLIŞ URL kaynaklı — doğru rota /seller/profile
#        (GET, seller.profile.edit). Route bug'ı YOK.
# ═══════════════════════════════════════════════════════════════════

# ═══════════════════════════════════════════════════════════════════
# BAKIM SAYFASI BLADE → VUE (2026-09-04) ✅ KANITLI (testing_agent iteration_8, %100)
# ═══════════════════════════════════════════════════════════════════
# - YENİ: resources/js/Pages/Errors/Maintenance.vue (+ Maintenance.css, mnt- prefixli, <style src>, çakışmasız).
#   Tasarım korundu: dalgalanan logo barları, 'CANLI MÜZAYEDE SİSTEMİ' rozeti, başlık/desc, %75 shimmer progress,
#   Durum/Hedef kutuları (yeşil pulse nokta), footer, sağ üst tema toggle (dark<->light, data-bs-theme + localStorage).
# - app/Http/Middleware/CheckMaintenanceMode.php: response()->view('errors.maintenance') YERİNE
#   Inertia::render('Errors/Maintenance')->toResponse($request)->setStatusCode(503).
# - SİLİNDİ: resources/views/errors/maintenance.blade.php.
# - ÖNEMLİ ORTAM DÜZELTMESİ (testing_agent): .env'e ASSET_URL=<preview url> eklendi. Reverse proxy Host'u iç cluster
#   host'una çevirdiği için asset() URL'leri cluster origin'e gidiyordu → CORS → boş/beyaz sayfa (ekran görüntüsü aracındaki
#   'spinner/beyaz' artefaktının GERÇEK nedeni). ASSET_URL ile asset'ler same-origin (preview) → tüm uygulama düzeldi.
# - Opsiyonel sertleştirme (yapılmadı): AppServiceProvider'da URL::forceRootUrl(config('app.url')) — url()/route()
#   çağrılarını da garanti same-origin yapmak için. Şu an APP_URL zaten preview olduğu için route()/Ziggy sorunsuz.
# ═══════════════════════════════════════════════════════════════════

# ═══════════════════════════════════════════════════════════════════
# ADMIN DASHBOARD MOBİL + BİLDİRİM KÜÇÜLTME (2026-09-04) ✅ KANITLI (testing_agent iteration_9, %100)
# ═══════════════════════════════════════════════════════════════════
# - Admin/Dashboard.css @media(max-width:576px) genişletildi: hero + 4'lü stat + mini kartlar kompakt;
#   alt kartlarda TAŞMA giderildi (.adm-break-row label esnek+ellipsis, .adm-qa-s ellipsis, list/ava/badge/chart küçültme).
#   Masaüstü 4-sütun düzen KORUNDU (regresyon yok).
# - theme-new.css .notif-* için @media(max-width:576px): /notifications kartları kompakt (avatar 44→36px, padding/yazı küçültme).
#   Header bildirim dropdown'ı farklı sınıf (dropdown-item) kullanıyor; zaten kompakt (34px avatar) ve 390px'te taşmasız doğrulandı.
# - Ek düzeltme: Admin/Dashboard.vue ve Seller/Auctions/Show.vue'daki MÜKERRER <style src> tag'leri tekilleştirildi
#   (step-4 refactor'ın attribute-sırası kaynaklı yan etkisi).
# - Kanıt: 390x844 mobilde 8 admin kartında yatay overflow yok, sayfa yatay scroll yok; notif kart 370<390px; 0 console hatası.
# ═══════════════════════════════════════════════════════════════════

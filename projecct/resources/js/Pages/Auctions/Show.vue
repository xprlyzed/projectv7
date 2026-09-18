<script>
import AppLayout from '@/Layouts/AppLayout.vue';
export default { layout: AppLayout };
</script>

<script setup>
import { onMounted, onUnmounted, nextTick, ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { connectRoom } from '@/composables/useLiveKit';
import { RoomEvent, Track } from 'livekit-client';
import { fireSmall, fireBig } from '@/lib/confetti';
import MobileLiveRoom from '@/Components/MobileLiveRoom.vue';
import DesktopLiveRoom from '@/Components/DesktopLiveRoom.vue';

// Canlı yayın gerçek-zamanlı olayları (izleyici tarafı) — DOM overlay (body'ye eklenir, scoped değil)
let __cdEl = null, __cdTimer = null;
function __removeCd() {
    if (__cdTimer) { clearInterval(__cdTimer); __cdTimer = null; }
    if (__cdEl) { __cdEl.remove(); __cdEl = null; }
    // Teklif çubuğu/sütunu vurgusunu kaldır
    document.querySelectorAll('.bid-urgent').forEach((el) => el.classList.remove('bid-urgent'));
}
function __showCountdown(endsAt) {
    __removeCd();
    // Sayaç GÖRÜNÜR medya alanının üzerinde gösterilir (masaüstünde header'da durması saçmaydı):
    // yayın canlıysa video kutusu, değilse görünür galeri paneli. Hiçbiri yoksa sabit yedek.
    const streamTabBtn = document.getElementById('tab-stream');
    const streamAvailable = streamTabBtn && !streamTabBtn.classList.contains('d-none');
    let host = null;
    if (streamAvailable) {
        try { window.switchTab && window.switchTab('stream'); } catch (e) {}
        const cs = document.getElementById('liveVideo')?.closest('.camera-section') || null;
        if (cs && cs.offsetParent !== null && cs.getBoundingClientRect().height > 0) host = cs;
    }
    if (!host) {
        host = document.querySelector('.section-panel.active') || null;
    }
    __cdEl = document.createElement('div');
    __cdEl.setAttribute('data-testid', 'viewer-sell-countdown');
    __cdEl.innerHTML = '<span class="lk-sb-ic"><i class="bi bi-hammer"></i></span>'
        + '<span class="lk-sb-txt">SATIŞA <b class="lk-sb-num">10</b> sn<span class="lk-sb-extra"> — son teklif şansın!</span></span>';
    if (host && host.offsetParent !== null) {
        if (getComputedStyle(host).position === 'static') host.style.position = 'relative';
        __cdEl.className = 'lk-sale-banner lk-sale-banner--onvideo';
        host.appendChild(__cdEl);
    } else {
        __cdEl.className = 'lk-sale-banner';
        document.body.appendChild(__cdEl);
    }
    // Mobil/masaüstü teklif alanını vurgula: sayaç sırasında teklif hâlâ mümkün
    document.querySelectorAll('.bid-sticky-bar, .bid-column').forEach((el) => el.classList.add('bid-urgent'));
    const numEl = __cdEl.querySelector('.lk-sb-num');
    const tick = () => {
        const s = Math.max(0, Math.ceil((endsAt - Date.now()) / 1000));
        if (numEl) numEl.textContent = s;
        if (s <= 0) __removeCd();
    };
    tick();
    __cdTimer = setInterval(tick, 200);
}
function __showSold(text) {
    __removeCd();
    fireBig();
    const el = document.createElement('div');
    el.className = 'lk-sale-overlay';
    el.setAttribute('data-testid', 'viewer-sold-banner');
    el.innerHTML = '<div class="lk-sale-card lk-sold"><i class="bi bi-patch-check-fill"></i><div class="lk-sold-title">İLAN SATILDI!</div><div class="lk-sold-sub">' + (text || 'Tebrikler!') + '</div></div>';
    document.body.appendChild(el);
    setTimeout(() => { try { el.remove(); } catch (e) {} }, 5000);
}
function onLiveData(msg) {
    if (!msg || !msg.type) return;
    if (msg.type === 'sell-countdown') {
        __showCountdown(msg.ends_at || (Date.now() + (msg.seconds || 10) * 1000));
    } else if (msg.type === 'new-bid') {
        fireSmall({ x: 0.5, y: 0.5 });
        __removeCd();
        // Teklif feed'i + güncel fiyat + sayı + min teklif → tüm izleyicilerde anlık güncelle
        try { window.__onRemoteBid && window.__onRemoteBid(msg); } catch (e) {}
    } else if (msg.type === 'auction-sold') {
        __showSold(msg.display ? (msg.winner_name + ' — ' + msg.display) : '');
    } else if (msg.type === 'chat') {
        try { window.__onRemoteChat && window.__onRemoteChat(msg); } catch (e) {}
    }
}
import { useClock, formatCountdown } from '@/useClock';

const props = defineProps({
    a: Object,
    config: Object,
});

function messageSeller() {
    router.post(props.config.messages_start_url, { user_id: props.a.seller.id });
}

// Görev 5: Favori (watchlist) toggle — optimistik UI (tıkla-anında dolar, hata olursa geri alınır)
const isWatching = ref(!!props.config?.is_watching);
const watchBusy = ref(false);
async function toggleWatch() {
    if (!props.config?.is_auth || props.config.is_auth === '0') {
        window.location.href = props.config.login_url;
        return;
    }
    if (watchBusy.value) return;
    watchBusy.value = true;
    const prev = isWatching.value;
    isWatching.value = !prev; // optimistik
    try {
        const res = await fetch(props.config.watch_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': props.config.csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('watch failed');
        const data = await res.json();
        isWatching.value = !!data.watching;
        if (window.appToast) window.appToast('success', isWatching.value ? 'Favorilere eklendi' : 'Favorilerden çıkarıldı');
    } catch (e) {
        isWatching.value = prev; // geri al
        if (window.appToast) window.appToast('error', 'İşlem başarısız, tekrar deneyin');
    } finally {
        watchBusy.value = false;
    }
}

// Mobil: tek dokunuşla hızlı teklif — CANLI minimumdan GERÇEK sonuç değerini hesaplar, sadece inputa yazar (göndermez)
const bidStep = Number(props.config?.min_increment) || 0;
function fmtTL(v) { return new Intl.NumberFormat('tr-TR').format(Math.round(v)) + ' ₺'; }
// Canlı minimum teklif; auction-show.js her yeni teklifte günceller → çipler bayat değer göstermez
const liveMin = ref(Number(props.a?.min_bid) || 0);
// Planlı ilan için başlangıca kalan süre (canlı, paylaşılan formatCountdown ile)
const clockNow = useClock();
const startsIn = computed(() => (props.a?.is_planned && props.a?.starts_at_ts)
    ? formatCountdown(props.a.starts_at_ts, clockNow.value).text : '');
const quickSteps = computed(() => {
    const base = liveMin.value || 0;
    return [
        { amount: base,               label: fmtTL(base) },
        { amount: base + bidStep,     label: fmtTL(base + bidStep) },
        { amount: base + bidStep * 5, label: fmtTL(base + bidStep * 5) },
    ];
});
function quickBidMobile(amount) {
    const input = document.getElementById('bid-input-mobile');
    if (!input) return;
    input.value = amount;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    // Otomatik focus YOK — kullanıcı kendisi dokunmadıkça mobil klavye açılmasın
    // Görsel ipucu: "Teklif Ver" butonunu kısa vurgula (kullanıcı basınca gönderilecek)
    const btn = document.querySelector('.bid-sticky-bar .sticky-submit');
    if (btn) { btn.classList.add('pulse'); setTimeout(() => btn.classList.remove('pulse'), 700); }
}

function scrollToChat() {
    // Canlı sekmesindeki sohbete yumuşak kaydır ve inputa odaklan
    try { window.switchTab && window.switchTab('stream'); } catch (e) {}
    const el = document.getElementById('chatInput') || document.querySelector('[data-testid="viewer-chat-messages"]');
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => { try { document.getElementById('chatInput')?.focus(); } catch (e) {} }, 350);
    }
}

function loadScript(src) {
    return new Promise((resolve) => {
        const s = document.createElement('script');
        s.src = src;
        s.dataset.auctionShow = '1';
        s.onload = () => resolve();
        s.onerror = () => resolve();
        document.body.appendChild(s);
    });
}

async function boot() {
    // Mobil/masaüstü: ilan detayına girince sayfa en üstten açılmalı
    try { window.scrollTo({ top: 0, left: 0 }); } catch (e) { window.scrollTo(0, 0); }
    // Canlı minimum güncellemelerini dinle → mobil hızlı teklif çipleri gerçek değeri gösterir
    window.__onLiveMin = (v) => { const n = Number(v); if (n > 0) liveMin.value = n; };
    // Canlı veri köprüsü (auction-show.js ↔ Vue; ayrıca satış geri sayımı tetikleme)
    window.__lkOnData = onLiveData;
    await nextTick();
    // config.js bir IIFE — her mount'ta güvenle yeniden çalışıp window.* değerlerini tazeler
    await loadScript('/assets/js/custom/auctions-new-config.js');
    if (window.__auctionShowInit) {
        // auction-show.js zaten yüklü: yeniden enjekte etme (top-level let çakışır), init'i tekrar çağır
        window.__auctionShowInit();
    } else {
        await loadScript('/assets/js/custom/auction-show.js');
    }
    // Yayın canlıysa (veya tanıtım videosu varsa) otomatik "Canlı İzle" sekmesine geç
    if ((props.a?.is_live && !props.a?.has_finished) || props.a?.uses_promo_video) {
        try { window.switchTab && window.switchTab('stream'); } catch (e) {}
    }
    // Satır-içi izleyici akışı (mobil + masaüstü aynı): video doğrudan/otomatik oynar.
    connectViewerStream();
}

let lkRoom = null;
const liveRoomRef = ref(null);
let graceTimer = null;
// Mobil TikTok-tarzı tam ekran canlı deneyim: yalnızca mobilde + canlı ilanda devreye girer.
const isMobile = ref(typeof window !== 'undefined' && window.matchMedia('(max-width: 767px)').matches);
const mobileLiveActive = ref(false);
// Mobilde canlı ilan modunda: satır-içi sohbet/teklif alanları gizlenir, tam ekran oda kullanılır.
const mobileLiveMode = computed(() => isMobile.value && !!props.a?.is_live && !props.a?.has_finished && !props.a?.is_owner);
function openMobileLive() {
    // Paylaşılan LiveKit bağlantısı Show.vue'da yaşıyor; tam ekran YALNIZCA görsel bir geçiş.
    // Bağlantıya dokunma → reconnect yok, "bağlanılıyor" görünmez.
    mobileLiveActive.value = true;
}
function onMobileLiveClose() {
    mobileLiveActive.value = false;
}
// Masaüstü tam ekran canlı deneyim (mobil ile simetrik): paylaşılan LiveKit odası kullanılır,
// tam ekran YALNIZCA görsel bir geçiş → bağlantıya dokunulmaz, "bağlanılıyor" görünmez.
const desktopLiveActive = ref(false);
function openDesktopLive() {
    desktopLiveActive.value = true;
}
function onDesktopLiveClose() {
    desktopLiveActive.value = false;
}
// Yayın durumu (izleyici): 'checking' = bağlanılıyor/kontrol ediliyor (nötr spinner),
// 'live' = video geldi, 'offline' = yayın kesin kapalı. Başlangıç: sunucu is_live derse 'checking'.
const streamState = ref((props.a?.is_live && !props.a?.has_finished) ? 'checking' : 'offline');
// Satır-içi yayın sesi (paylaşımlı LiveKit odası üzerinden). Varsayılan: sessiz (autoplay politikası).
const streamMuted = ref(true);
function toggleInlineMute() {
    if (!lkRoom || !lkRoom.setStreamMuted) return;
    streamMuted.value = !streamMuted.value;
    try { lkRoom.setStreamMuted(streamMuted.value); } catch (e) {}
}
// Canlı video UI'ını yalnızca GERÇEK video track'i geldiğinde göster.
// (İzleyici, teklif veri kanalı için odaya her durumda bağlanır; oda bağlantısı ≠ yayın var.)
function revealLiveVideo() {
    if (graceTimer) { clearTimeout(graceTimer); graceTimer = null; }
    streamState.value = 'live';
    const v = document.getElementById('liveVideo'); if (v) v.style.display = 'block';
    const off = document.getElementById('cam-off-state'); if (off) off.style.display = 'none';
    const pill = document.getElementById('stream-live-pill'); if (pill) pill.style.display = 'inline-flex';
}
function hideLiveVideo() {
    streamState.value = 'offline';
    const v = document.getElementById('liveVideo'); if (v) v.style.display = 'none';
    const off = document.getElementById('cam-off-state'); if (off) off.style.display = '';
    const pill = document.getElementById('stream-live-pill'); if (pill) pill.style.display = 'none';
}
function disconnectViewerStream() {
    if (graceTimer) { clearTimeout(graceTimer); graceTimer = null; }
    if (lkRoom) { try { lkRoom.disconnect(); } catch (e) {} lkRoom = null; }
    liveRoomRef.value = null;
    hideLiveVideo();
}
async function connectViewerStream() {
    // Her izleyici LiveKit odasına bağlanır (yayın canlı olmasa da) → teklifler anlık düşer.
    // Video UI ise yalnızca satıcı gerçekten yayındayken (video track) açılır.
    if (!props.a?.slug) return;
    if (props.a?.is_live && !props.a?.has_finished) streamState.value = 'checking';
    const videoEl = document.getElementById('liveVideo');
    try {
        lkRoom = await connectRoom({
            auctionSlug: props.a.slug,
            role: 'viewer',
            csrf: (props.config?.csrf || document.querySelector('meta[name="csrf-token"]')?.content || ''),
            videoEl,
            onData: onLiveData,
            startAudioMuted: true,
        });
        liveRoomRef.value = lkRoom;
        streamMuted.value = lkRoom.isStreamMuted ? lkRoom.isStreamMuted() : true;
        const hasVideo = () => {
            let found = false;
            lkRoom.remoteParticipants.forEach((p) => p.trackPublications.forEach((pub) => {
                if (pub.isSubscribed && pub.track && pub.track.kind === Track.Kind.Video) found = true;
            }));
            return found;
        };
        lkRoom.on(RoomEvent.TrackSubscribed, (track) => { if (track.kind === Track.Kind.Video) revealLiveVideo(); });
        lkRoom.on(RoomEvent.TrackUnsubscribed, (track) => { if (track.kind === Track.Kind.Video && !hasVideo()) hideLiveVideo(); });
        lkRoom.on(RoomEvent.ParticipantDisconnected, () => { if (!hasVideo()) hideLiveVideo(); });
        // İlk durum netleştir: video zaten varsa göster; yoksa sunucu is_live diyorsa
        // NÖTR "bağlanılıyor" durumunda kal (grace süresi) — kesin "yayın yok" mesajını gösterme.
        if (hasVideo()) {
            revealLiveVideo();
        } else if (props.a?.is_live && !props.a?.has_finished) {
            streamState.value = 'checking';
            if (graceTimer) clearTimeout(graceTimer);
            graceTimer = setTimeout(() => { if (streamState.value !== 'live') hideLiveVideo(); }, 8000);
        } else {
            hideLiveVideo();
        }
    } catch (e) { /* LiveKit yapılandırılmadıysa sessizce eski davranışa düş */ }
}

onMounted(boot);

onUnmounted(() => {
    __removeCd();
    if (graceTimer) { clearTimeout(graceTimer); graceTimer = null; }
    if (window.__onLiveMin) delete window.__onLiveMin;
    if (window.__lkOnData) delete window.__lkOnData;
    if (window.__auctionShowCleanup) { try { window.__auctionShowCleanup(); } catch (e) {} }
    if (lkRoom) { try { lkRoom.disconnect(); } catch (e) {} lkRoom = null; }
    liveRoomRef.value = null;
    // sadece config script'lerini temizle; auction-show.js tekrar kullanılmak üzere kalır
    document.querySelectorAll('script[data-auction-show="1"]').forEach((s) => {
        if (s.src.includes('auctions-new-config.js')) s.remove();
    });
});
</script>

<template>
    <Head :title="a.title" />

    <div class="container-fluid py-3">

        <div class="au-toolbar">
            <div>
                <div class="au-title">{{ a.title_70 }}</div>
                <div class="au-breadcrumb">
                    <Link :href="route('index')">Ana Sayfa</Link>
                    <span class="sep">/</span>
                    <a href="#">Müzayedeler</a>
                    <span class="sep">/</span>
                    <span>{{ a.title_30 }}</span>
                </div>
            </div>
            <div class="au-status-badges">
                <span v-if="a.is_live && !a.has_finished" class="live-pill"><span class="live-dot"></span> CANLI</span>
                <span class="viewer-pill">
                    <i class="bi bi-eye il-6b9c179a"></i>
                    <span id="viewer-count" data-testid="viewer-count">—</span> izleyici
                </span>
                <button
                    v-if="!a.is_owner"
                    type="button"
                    class="watch-btn"
                    :class="{ 'is-on': isWatching }"
                    :disabled="watchBusy"
                    @click="toggleWatch"
                    :aria-pressed="isWatching"
                    :title="isWatching ? 'Favorilerden çıkar' : 'Favorilere ekle'"
                    data-testid="watch-toggle-button">
                    <i :class="isWatching ? 'bi bi-heart-fill' : 'bi bi-heart'"></i>
                    <span class="watch-btn-lbl">{{ isWatching ? 'Favoride' : 'Favori' }}</span>
                </button>
            </div>
        </div>

        <div class="auction-grid">

            <div class="il-37d2b62e">

                <div class="il-7d76e46d">
                    <div class="mode-toggle">
                        <button class="mode-btn active" id="tab-gallery" onclick="switchTab('gallery')">
                            <i class="bi bi-images"></i> Fotoğraflar
                        </button>
                        <button class="mode-btn" :class="{ 'd-none': !(a.uses_promo_video || (a.is_live && !a.has_finished)) }"
                                id="tab-stream" onclick="switchTab('stream')">
                            <template v-if="a.uses_promo_video"><i class="bi bi-film"></i> Tanıtım Videosu</template>
                            <template v-else><i class="bi bi-camera-video"></i> Canlı İzle</template>
                        </button>
                    </div>
                    <div class="il-667d43f0">
                        <i class="bi bi-geo-alt il-831b94f4"></i>{{ a.location ?? '—' }}
                        &nbsp;·&nbsp;
                        <i class="bi bi-tag il-831b94f4"></i>{{ a.category_name ?? '—' }}
                    </div>
                </div>

                <div id="panel-gallery" class="section-panel active au-card il-3e3b3e71">
                    <div class="il-23cd36a5">
                        <img id="mainImg" :src="a.cover_url" class="gallery-main" onclick="openLightbox(this.src)" :alt="a.title">
                    </div>
                    <div v-if="a.images.length > 1" class="gallery-thumbs">
                        <img v-for="(img, i) in a.images" :key="i" :src="img.url"
                             :onclick="`switchImg(this,'${img.url}')`"
                             class="gallery-thumb" :class="{ active: img.is_cover }" :alt="`Görsel ${i+1}`">
                    </div>
                </div>

                <div id="panel-stream" class="section-panel">
                    <template v-if="a.uses_promo_video">
                        <div class="camera-section" data-testid="promo-video-section">
                            <video v-if="a.is_direct_video" :src="a.promo_video_url" class="camera-video il-27a3b2f6" controls></video>
                            <iframe class="il-91fae6b4" v-else :src="a.embed_video_url" allow="autoplay; encrypted-media; fullscreen" allowfullscreen></iframe>
                        </div>
                    </template>
                    <template v-else>
                        <div class="camera-section">
                            <div v-show="streamState === 'checking'" class="cam-off-banner" id="cam-checking-state" data-testid="stream-checking">
                                <div class="cam-spinner" aria-hidden="true"></div>
                                <p>Yayına bağlanılıyor…</p>
                            </div>
                            <div v-show="streamState === 'offline'" class="cam-off-banner" id="cam-off-state" data-testid="stream-offline">
                                <i class="bi bi-camera-video-off"></i>
                                <p>Satıcı henüz yayın başlatmadı</p>
                            </div>
                            <video id="liveVideo" class="camera-video il-cb458930" autoplay playsinline muted></video>

                            <div class="il-908f7cf9" id="viewer-sell-bar">
                                <i class="bi bi-hourglass-split"></i>
                                <span id="viewer-sell-bar-text">3 saniye sonra satış tamamlanacak…</span>
                            </div>

                            <div class="il-e3cc89b6" id="viewer-sold-overlay">
                                <div class="il-5b71f0ee">🎉</div>
                                <div class="il-fc6c5db8">Satış Tamamlandı!</div>
                                <div class="il-b9d6a430" id="viewer-sold-sub">—</div>
                            </div>
                            <div class="camera-overlay">
                                <div class="camera-top-bar">
                                    <div>
                                        <span class="live-pill il-cb458930" id="stream-live-pill">
                                            <span class="live-dot"></span> CANLI
                                        </span>
                                    </div>
                                    <div class="il-91294d29">
                                        <button class="cam-btn-icon" id="vol-btn" v-show="streamState === 'live'" @click="toggleInlineMute" title="Ses aç/kapat" data-testid="stream-mute-btn">
                                            <i class="bi" :class="streamMuted ? 'bi-volume-mute' : 'bi-volume-up'"></i>
                                        </button>
                                        <button v-if="isMobile && a.is_live && !a.has_finished && !a.is_owner"
                                                class="cam-btn-icon" @click="openMobileLive"
                                                data-testid="enter-fullscreen-mobile" title="Tam ekran canlı yayın">
                                            <i class="bi bi-fullscreen"></i>
                                        </button>
                                        <button v-if="!isMobile && a.is_live && !a.has_finished && !a.is_owner"
                                                class="cam-btn-icon" @click="openDesktopLive"
                                                data-testid="enter-fullscreen-desktop" title="Tam ekran canlı yayın">
                                            <i class="bi bi-fullscreen"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="camera-bottom-bar">
                                    <span class="viewer-pill">
                                        <i class="bi bi-people il-6b9c179a"></i>
                                        <span id="viewer-count-stream">—</span> izleyici
                                    </span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div class="stream-seller-strip" data-testid="stream-seller-strip" v-show="!mobileLiveMode">
                        <Link :href="a.seller.profile_url" class="sss-ava-link">
                            <img class="sss-ava" :src="a.seller.profile_img" :alt="a.seller.name">
                        </Link>
                        <div class="sss-meta">
                            <Link :href="a.seller.profile_url" class="sss-name">{{ a.seller.name }}</Link>
                            <div class="sss-rating">
                                <i class="bi bi-star-fill"></i> {{ a.seller.rating_fmt }}
                                <span class="sss-cnt">({{ a.seller.review_count }})</span>
                            </div>
                        </div>
                        <div class="sss-actions">
                            <Link :href="a.seller.profile_url" class="sss-btn sss-btn-ghost" data-testid="stream-view-profile">
                                <i class="bi bi-person"></i><span class="sss-btn-lbl">Profil</span>
                            </Link>
                            <button type="button" class="sss-btn sss-btn-primary" @click="scrollToChat" data-testid="stream-ask-seller">
                                <i class="bi bi-chat-dots"></i><span class="sss-btn-lbl">Satıcıya Sor</span>
                            </button>
                        </div>
                    </div>

                    <div class="au-card mt-3 il-b0043ef4" data-testid="viewer-chat-card" v-show="!mobileLiveMode">
                        <div class="au-card-head">
                            <div class="au-card-title"><i class="bi bi-chat-dots"></i> Canlı Sohbet · Satıcıya Sor</div>
                        </div>
                        <div class="il-c9643350" id="chatMessages" data-testid="viewer-chat-messages">
                            <div class="il-8de03df2" id="chatEmpty">
                                <i class="bi bi-chat il-443eefda"></i>
                                İlk mesajı sen yaz
                            </div>
                        </div>
                        <template v-if="config.is_auth === '1'">
                            <div class="il-30d1b52c" v-if="a.has_finished">
                                <i class="bi bi-lock"></i> Yayın sona erdi, sohbet kapalı.
                            </div>
                            <template v-else>
                                <form class="il-345b081b" id="chatForm">
                                    <input class="il-e2f4ef1f" id="chatInput" type="text" maxlength="300" autocomplete="off" data-testid="viewer-chat-input" placeholder="Satıcıya bir soru sor...">
                                    <button class="il-26665949" type="submit" data-testid="viewer-chat-send">
                                        <i class="bi bi-send"></i>
                                    </button>
                                </form>
                                <div class="il-83472c2a" id="chatError"></div>
                            </template>
                        </template>
                        <div class="il-f9d03d02" v-else>
                            Sohbete katılmak için <Link class="il-035cc887" :href="config.login_url">giriş yap</Link>.
                        </div>
                    </div>
                </div>

                <div class="au-card">
                    <div class="au-card-head">
                        <div class="au-card-title"><i class="bi bi-file-text"></i> Açıklama</div>
                    </div>
                    <div class="au-desc-body">{{ a.description }}</div>
                </div>

                <div class="au-card">
                    <div class="au-card-head">
                        <div class="au-card-title"><i class="bi bi-info-circle"></i> Ürün Özellikleri</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-icon"><i class="bi bi-tag"></i></div>
                        <div><div class="detail-lbl">Kategori</div><div class="detail-val">{{ a.category_name ?? '—' }}</div></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-icon"><i class="bi bi-arrow-up-circle"></i></div>
                        <div><div class="detail-lbl">Min. Artış</div><div class="detail-val">{{ a.min_increment_fmt }}</div></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-icon"><i class="bi bi-star"></i></div>
                        <div><div class="detail-lbl">Durum</div><div class="detail-val">{{ a.condition_label }}</div></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-icon"><i class="bi bi-geo-alt"></i></div>
                        <div><div class="detail-lbl">Konum</div><div class="detail-val">{{ a.location ?? '—' }}</div></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-icon"><i class="bi bi-calendar3"></i></div>
                        <div><div class="detail-lbl">Başlangıç</div><div class="detail-val">{{ a.starts_at_fmt }}</div></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-icon"><i class="bi bi-calendar-x"></i></div>
                        <div><div class="detail-lbl">Bitiş</div><div class="detail-val">{{ a.ends_at_fmt }}</div></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-icon"><i class="bi bi-eye"></i></div>
                        <div><div class="detail-lbl">Görüntülenme</div><div class="detail-val">{{ a.view_count_fmt }}</div></div>
                    </div>
                </div>
            </div>

            <div class="bid-column">

                <div class="au-card il-3e3b3e71">
                    <div class="price-hero">
                        <div class="price-lbl">Güncel En Yüksek Teklif</div>
                        <div class="price-value" id="live-price">{{ a.display_price }}</div>
                        <div class="price-start">Başlangıç: {{ a.starting_price_fmt }}</div>
                        <div v-if="a.buy_now_price_fmt" class="buy-now-box">
                            <div>
                                <div class="buy-now-lbl">⚡ Hemen Satın Al</div>
                                <div class="buy-now-val">{{ a.buy_now_price_fmt }}</div>
                            </div>
                            <button class="cam-btn il-c8cd1a75">Hemen Al</button>
                        </div>
                    </div>
                    <div class="stats-row">
                        <div class="stat-cell"><div class="stat-lbl">Teklif</div><div class="stat-val" id="live-bid-count">{{ a.bid_count }}</div></div>
                        <div class="stat-cell"><div class="stat-lbl">Kalan</div><div class="stat-val" id="live-timer">—</div></div>
                        <div class="stat-cell"><div class="stat-lbl">İzleyici</div><div class="stat-val" id="live-viewer-stat">—</div></div>
                    </div>
                    <div class="bid-form-area">
                        <div v-if="a.is_planned" class="alert alert-info mb-0 il-410d8d12" data-testid="auction-planned-box">
                            <div class="il-83e4487b"><i class="bi bi-clock-history me-1"></i> Planlı — henüz başlamadı</div>
                            <div class="mt-1">Başlangıç: {{ a.starts_at_fmt }}</div>
                            <div class="mt-1" v-if="startsIn">Başlamasına: {{ startsIn }}</div>
                            <div class="mt-1 il-62f51076">Açık artırma başladığında teklif verebilirsiniz.</div>
                        </div>
                        <div v-else-if="a.status === 'draft'" class="alert alert-warning mb-0 il-410d8d12">
                            <i class="bi bi-hourglass-split me-1"></i> Bu ilan admin onayı bekliyor.
                        </div>
                        <div v-else-if="a.status === 'rejected'" class="alert alert-danger mb-0 il-410d8d12">
                            <i class="bi bi-x-circle me-1"></i> Bu ilan reddedildi.
                        </div>
                        <div v-else-if="!a.is_active" class="alert alert-danger mb-0 il-410d8d12">
                            <i class="bi bi-clock me-1"></i> Bu müzayede sona erdi.
                        </div>
                        <template v-else>
                            <template v-if="config.is_auth === '1'">
                                <template v-if="!a.is_owner">
                                    <div class="quick-grid" id="quick-btns">
                                        <button v-for="(q, qi) in a.quick" :key="qi" class="quick-btn" :data-testid="`quick-bid-${qi}`" :onclick="`setQuick(${q.val})`">
                                            +{{ q.inc_fmt }}
                                            <span>{{ q.val_fmt }}</span>
                                        </button>
                                    </div>
                                    <div class="bid-input-wrap">
                                        <input type="number" id="bid-input" name="amount" data-testid="bid-amount-input"
                                               :min="a.min_bid" :step="config.min_increment" :placeholder="`Min: ${a.min_bid_fmt}`">
                                        <div class="currency">₺</div>
                                    </div>
                                    <div class="bid-error" id="bid-error"></div>
                                    <button class="bid-submit" id="bid-btn" onclick="submitBid()" data-testid="bid-submit-btn">
                                        <i class="bi bi-lightning-charge-fill"></i>
                                        <span id="bid-btn-text">Teklif Ver</span>
                                    </button>
                                </template>
                                <div v-else class="alert alert-warning mb-0 il-410d8d12">
                                    <i class="bi bi-info-circle me-1"></i> Kendi ilanınıza teklif veremezsiniz.
                                </div>
                            </template>
                            <Link v-else :href="config.login_url" class="bid-submit il-116e33d4">
                                <i class="bi bi-box-arrow-in-right"></i> Teklif vermek için giriş yap
                            </Link>
                        </template>
                    </div>
                </div>

                <div class="au-card seller-card" data-testid="seller-card">
                    <div class="au-card-head">
                        <div class="au-card-title"><i class="bi bi-shop"></i> Satıcı</div>
                    </div>
                    <div class="seller-card-body">
                        <Link :href="a.seller.profile_url" class="seller-ava-link">
                            <img class="seller-ava" :src="a.seller.profile_img" :alt="a.seller.name">
                        </Link>
                        <div class="seller-meta">
                            <Link :href="a.seller.profile_url" class="seller-name" data-testid="seller-name">{{ a.seller.name }}</Link>
                            <div class="seller-handle">&#64;{{ a.seller.username }}</div>
                            <div class="seller-rating" data-testid="seller-rating">
                                <span class="stars">
                                    <i v-for="(st, si) in a.seller.stars" :key="si" class="bi"
                                       :class="st === 'full' ? 'bi-star-fill' : (st === 'half' ? 'bi-star-half' : 'bi-star')"></i>
                                </span>
                                <span class="seller-rating-num">{{ a.seller.rating_fmt }}</span>
                                <span class="seller-rating-cnt">({{ a.seller.review_count }} değerlendirme)</span>
                            </div>
                        </div>
                    </div>
                    <div class="seller-actions">
                        <template v-if="config.is_auth === '1'">
                            <form v-if="config.current_user_id !== a.seller.id" @submit.prevent="messageSeller" class="seller-msg-form">
                                <button type="submit" class="seller-btn-primary" data-testid="message-seller-btn">
                                    <i class="bi bi-chat-dots"></i> Satıcıya Mesaj Gönder
                                </button>
                            </form>
                        </template>
                        <Link v-else :href="config.login_url" class="seller-btn-primary" data-testid="message-seller-btn">
                            <i class="bi bi-chat-dots"></i> Satıcıya Mesaj Gönder
                        </Link>
                        <Link :href="a.seller.profile_url" class="seller-btn-ghost">
                            <i class="bi bi-person"></i> Profili Gör
                        </Link>
                    </div>
                </div>

                <div class="au-card">
                    <div class="au-card-head">
                        <div class="au-card-title"><i class="bi bi-activity"></i> Teklif Akışı</div>
                        <span class="a-badge info" id="bid-count-badge">{{ a.bid_count }} teklif</span>
                    </div>
                    <div class="feed-scroll">
                        <div id="bid-feed">
                            <template v-if="a.bids.length">
                                <div v-for="(bid, bi) in a.bids" :key="bi" class="bid-item" :class="{ 'bid-top': bid.is_top }">
                                    <span v-if="bid.is_top" class="top-label">En Yüksek</span>
                                    <span class="bid-rank" :class="bid.rank_class">{{ bid.rank }}</span>
                                    <img class="bid-avatar" :src="bid.avatar" :alt="bid.name">
                                    <div class="il-89bd09bd">
                                        <div class="bid-name">{{ bid.name }}</div>
                                        <div class="bid-time">{{ bid.time }}</div>
                                    </div>
                                    <div class="bid-amount">{{ bid.amount_fmt }}</div>
                                </div>
                            </template>
                            <div v-else class="feed-empty" id="feed-empty">
                                <i class="bi bi-inbox"></i>
                                <p>Henüz teklif yok. İlk teklifi sen ver!</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="config.is_auth === '1' && a.is_active && !a.is_owner && !mobileLiveMode" class="bid-sticky-bar">
            <div class="sticky-top-line">
                <div class="stl-price">
                    <span class="stl-lbl">Güncel</span>
                    <span class="sticky-price" id="live-price-mobile">{{ a.display_price }}</span>
                </div>
                <div class="stl-timer">
                    <i class="bi bi-clock"></i>
                    <span class="sticky-timer" id="live-timer-mobile">—</span>
                </div>
            </div>
            <div class="sticky-quick-row" data-testid="mobile-quick-row">
                <button v-for="(qs, qi) in quickSteps" :key="qi" type="button" class="sticky-quick-chip"
                        @click="quickBidMobile(qs.amount)" :data-testid="`mobile-quick-${qi}`">
                    {{ qs.label }}
                </button>
            </div>
            <div class="sticky-input-row">
                <input type="number" id="bid-input-mobile" data-testid="mobile-bid-input" :min="a.min_bid" :step="config.min_increment" :placeholder="`En az ${a.min_bid_fmt}`">
                <button class="sticky-submit" onclick="submitBidMobile()" data-testid="mobile-bid-submit">
                    <i class="bi bi-lightning-charge-fill"></i> Teklif Ver
                </button>
            </div>
            <div class="bid-error il-c4ed8a74" id="bid-error-mobile"></div>
        </div>

        <div class="il-3d914d6d" id="lightbox" onclick="closeLightbox()">
            <img class="il-4480e7c3" id="lightbox-img">
        </div>

        <Teleport to="body">
            <MobileLiveRoom v-if="mobileLiveActive" :a="a" :config="config" :room="liveRoomRef" :stream-state="streamState" @close="onMobileLiveClose" />
        </Teleport>

        <Teleport to="body">
            <DesktopLiveRoom v-if="desktopLiveActive" :a="a" :config="config" :room="liveRoomRef" :stream-state="streamState" @close="onDesktopLiveClose" />
        </Teleport>

        <div id="auctionNewConfigRoot"
             :data-auction-id="config.auction_id"
             :data-min-increment="config.min_increment"
             :data-bid-url="config.bid_url"
             :data-csrf="config.csrf"
             :data-seller-id="config.seller_id"
             :data-remaining-secs="config.remaining_secs"
             :data-live-state-url="config.live_state_url"
             :data-chat-poll-url="config.chat_poll_url"
             :data-chat-store-url="config.chat_store_url"
             :data-is-finished="config.is_finished"
             :data-uses-video="config.uses_video"
             :data-last-bid-id="config.last_bid_id"
             :data-sold-handled="config.sold_handled"
             :data-is-auth="config.is_auth"
             :data-current-user-id="config.current_user_id"
             :data-current-min="config.current_min"></div>
    </div>
</template>


<style scoped src="../../../css/pages/auctions-show.css"></style>

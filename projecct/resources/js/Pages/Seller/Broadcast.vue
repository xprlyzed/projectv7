<script>
import AppLayout from '@/Layouts/AppLayout.vue';
export default { layout: AppLayout };
</script>

<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, nextTick, computed } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { Room, Track, RoomEvent, VideoPresets, AudioPresets } from 'livekit-client';
import { fetchLiveKitToken, connectRoom } from '@/composables/useLiveKit';
import { fireSmall, fireBig } from '@/lib/confetti';
import { useClock, formatCountdown } from '@/useClock';

const props = defineProps({
    auction: Object,
    bids: Array,
    routes: Object,
});

const page = usePage();
const csrf = () => page.props.csrf_token || document.querySelector('meta[name="csrf-token"]')?.content || '';

// Native confirm/alert yerine mevcut SweetAlert2 (Swal) kullan; yoksa native'e düş
function swalConfirm(opts) {
    if (window.Swal) {
        return window.Swal.fire({
            showCancelButton: true, reverseButtons: true, heightAuto: false,
            confirmButtonColor: '#ef4444', cancelButtonText: 'Vazgeç', ...opts,
        }).then((r) => r.isConfirmed);
    }
    return Promise.resolve(window.confirm((opts.title || '') + (opts.text ? '\n' + opts.text : '')));
}
function swalToast(icon, title) {
    if (window.Swal) {
        window.Swal.fire({ toast: true, position: 'top-end', timer: 3400, showConfirmButton: false, icon, title, heightAuto: false });
    }
}

const videoEl = ref(null);
const status = ref('idle');          // idle | connecting | live | error
const errorMsg = ref('');
const camOn = ref(false);
const micOn = ref(false);
const viewers = ref(0);
const bidList = ref([...(props.bids || [])]);
// Panel başlığındaki sayaç: gerçek toplam ile başlar, her yeni realtime teklifte artar
const bidCount = ref(Number(props.auction?.bid_count) || (props.bids || []).length);

// Canlı özet şeridi (tek bakışta kritik bilgi) — hepsi reaktif
const clockNow = useClock();
const currentPrice = computed(() => Number(bidList.value[0]?.amount) || Number(props.auction?.current_price) || 0);
const lastBidder = computed(() => bidList.value[0]?.name || '—');
const timeLeft = computed(() => props.auction?.ends_at_ts
    ? formatCountdown(props.auction.ends_at_ts, clockNow.value)
    : { text: '—', critical: false });

// Satış geri sayımı (satıcı ekranı — minimal rozet)
const countdown = reactive({ active: false, secs: 0, bid: null });
let cdTimer = null;

function cancelCountdown() {
    if (cdTimer) { clearInterval(cdTimer); cdTimer = null; }
    countdown.active = false;
    countdown.bid = null;
}

// LiveKit veri kanalından gelen gerçek-zamanlı olaylar
function onRoomData(msg) {
    if (!msg || !msg.type) return;
    if (msg.type === 'new-bid') {
        if (!bidList.value.some((b) => b.id === msg.bid_id)) {
            bidList.value.unshift({ id: msg.bid_id, name: msg.name, amount: msg.amount, time: 'şimdi' });
            bidCount.value++;
        }
        fireSmall({ x: 0.5, y: 0.5 });
        if (countdown.active) { cancelCountdown(); swalToast('info', 'Yeni teklif geldi — geri sayım iptal edildi.'); }
    } else if (msg.type === 'auction-sold') {
        fireBig();
    } else if (msg.type === 'chat') {
        if (!chat.messages.some((m) => m.id === msg.id)) {
            chat.messages.push({ id: msg.id, user_id: msg.user_id, user_name: msg.user_name, message: msg.message, is_seller: msg.is_seller });
            chat.lastId = Math.max(chat.lastId, msg.id);
            nextTick().then(() => { if (chatBox.value) chatBox.value.scrollTop = chatBox.value.scrollHeight; });
        }
    }
}

let room = null;
let previewStream = null;
let dataRoom = null;       // yayın açılmadan da teklifleri anlık almak için veri-only bağlantı
let unmounting = false;

// Yayın açık DEĞİLKEN bile teklifleri anlık almak için odaya veri-only bağlan
async function connectDataOnly() {
    if (unmounting || dataRoom || status.value === 'live' || status.value === 'connecting') return;
    try {
        dataRoom = await connectRoom({
            auctionSlug: props.auction.slug,
            role: 'viewer',
            csrf: csrf(),
            videoEl: null,
            onData: onRoomData,
        });
    } catch (e) { /* LiveKit yoksa sessizce geç (yedek: chat polling) */ }
}
function disconnectDataOnly() {
    if (dataRoom) { try { dataRoom.disconnect(); } catch (e) {} dataRoom = null; }
}

function stopPreview() {
    if (previewStream) { previewStream.getTracks().forEach((t) => t.stop()); previewStream = null; }
    if (videoEl.value) videoEl.value.srcObject = null;
}

async function previewCamera() {
    if (status.value === 'live' || status.value === 'connecting') return;
    errorMsg.value = '';
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        errorMsg.value = 'Kamera önizlemesi için güvenli bağlam (HTTPS) gerekli veya bu sekmede izin engelli. Yeni sekmede aç.';
        return;
    }
    try {
        previewStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
        if (videoEl.value) { videoEl.value.srcObject = previewStream; videoEl.value.muted = true; }
        status.value = 'preview';
    } catch (e) {
        errorMsg.value = e.name === 'NotAllowedError'
            ? 'Kamera izni reddedildi. Adres çubuğundaki kamera simgesinden izin ver.'
            : 'Kamera önizlemesi açılamadı.';
    }
}

const copied = ref(false);
async function copyViewerLink() {
    const url = new URL(props.routes.view_public, window.location.origin).href;
    try {
        await navigator.clipboard.writeText(url);
        copied.value = true; setTimeout(() => { copied.value = false; }, 1800);
    } catch (e) {
        window.prompt('İzleyici linki:', url);
    }
}

async function goLive() {
    if (status.value === 'connecting' || status.value === 'live') return;
    errorMsg.value = '';

    // Ön kontrol: güvenli bağlam + medya API'si (önizleme iframe'inde kamera engelli olabilir)
    if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        errorMsg.value = 'Kamera erişimi için güvenli bağlam (HTTPS) gerekli veya bu sekmede izin engelli. Uygulamayı yeni bir sekmede açıp tekrar dene.';
        status.value = 'error';
        return;
    }

    status.value = 'connecting';
    // Yayına geçmeden önce veri-only bağlantıyı kapat (aynı identity ile çift bağlantı olmasın)
    disconnectDataOnly();
    // Önizleme akışı kamerayı tutuyorsa serbest bırak ve cihazın boşalması için kısa bekle
    // (aksi halde LiveKit kamerayı açarken "Could not start video source" alınır)
    stopPreview();
    await new Promise((r) => setTimeout(r, 350));

    try {
        const { server_url, participant_token } = await fetchLiveKitToken({
            auctionSlug: props.auction.slug, role: 'broadcaster', csrf: csrf(),
        });
        room = new Room({
            adaptiveStream: true,
            dynacast: true,
            // Full HD yayın: kamerayı 1080p yakala; simulcast ile izleyiciye ağına göre 360p/720p/1080p sun
            // (donma yerine katman düşürülür). Sesde processing + yüksek kalite preset ile bozulma azaltılır.
            videoCaptureDefaults: { resolution: VideoPresets.h1080.resolution },
            audioCaptureDefaults: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
            publishDefaults: {
                simulcast: true,
                videoSimulcastLayers: [VideoPresets.h360, VideoPresets.h720],
                videoCodec: 'vp8',
                videoEncoding: VideoPresets.h1080.encoding,
                degradationPreference: 'balanced',
                dtx: true,
                red: true,
                audioPreset: AudioPresets.musicHighQuality,
            },
        });
        room.on(RoomEvent.DataReceived, (payload) => {
            try { onRoomData(JSON.parse(new TextDecoder().decode(payload))); } catch (e) { /* yok say */ }
        });
        room.on('participantConnected', () => { viewers.value = Math.max(0, room.numParticipants - 1); });
        room.on('participantDisconnected', () => { viewers.value = Math.max(0, room.numParticipants - 1); });
        // Sadece KALICI kopmada idle'a düş (geçici reconnect'te yayını kapatma)
        room.on('disconnected', () => { status.value = 'idle'; camOn.value = false; micOn.value = false; if (!unmounting) connectDataOnly(); });

        await room.connect(server_url, participant_token, { autoSubscribe: false });

        // Kamerayı LiveKit üzerinden TEK SEFERDE aç (çift açılış yok)
        let camOk = false, micOk = false, camErr = null;
        try { await room.localParticipant.setCameraEnabled(true); camOk = true; }
        catch (e) { camErr = e; }
        try { await room.localParticipant.setMicrophoneEnabled(true); micOk = true; }
        catch (e) { /* mikrofon opsiyonel */ }

        camOn.value = camOk; micOn.value = micOk;
        if (camOk) attachLocalPreview();

        if (!camOk && !micOk) {
            throw Object.assign(new Error(mapMediaError(camErr)), { handled: true });
        }

        // Backend'e "canlı" bilgisini bildir
        await fetch(props.routes.live_status, {
            method: 'POST', credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({ live: 1 }),
        });

        status.value = 'live';
        viewers.value = Math.max(0, room.numParticipants - 1);
        // Kamera açılamadı ama mikrofon açıldıysa uyar (yayın sesli devam eder)
        if (!camOk) errorMsg.value = 'Kamera açılamadı (' + mapMediaError(camErr) + ') — yayın şimdilik sesli. "Kamera Kapalı"ya basıp tekrar açmayı deneyebilirsin.';
    } catch (e) {
        if (e.code === 'not_configured') {
            errorMsg.value = 'Canlı yayın altyapısı (LiveKit) henüz yapılandırılmadı. Yönetici .env içine LIVEKIT_* anahtarlarını eklemeli.';
        } else {
            errorMsg.value = e.handled ? e.message : (e.message || 'Yayın başlatılamadı.');
        }
        status.value = 'error';
        await stopRoom(false);
    }
}

// Tarayıcı medya hatalarını anlaşılır Türkçe mesaja çevir
function mapMediaError(e) {
    if (!e) return 'Bilinmeyen hata';
    const n = e.name || '';
    if (n === 'NotReadableError' || /could not start video source/i.test(e.message || '')) {
        return 'Kamera başka bir uygulama/sekme tarafından kullanılıyor. Kamerayı kullanan diğer uygulamaları ve sekmeleri (Zoom, Meet, başka yayın vb.) kapatıp tekrar dene.';
    }
    if (n === 'NotAllowedError' || n === 'SecurityError') return 'Kamera/mikrofon izni reddedildi. Adres çubuğundaki kamera simgesinden izin ver.';
    if (n === 'NotFoundError' || n === 'OverconstrainedError') return 'Cihazda kamera/mikrofon bulunamadı.';
    return e.message || n || 'Kamera açılamadı';
}

function attachLocalPreview() {
    if (!room || !videoEl.value) return;
    const pub = room.localParticipant.getTrackPublication(Track.Source.Camera);
    if (pub?.track) pub.track.attach(videoEl.value);
}

async function toggleCam() {
    if (!room) return;
    const next = !camOn.value;
    try {
        await room.localParticipant.setCameraEnabled(next);
        camOn.value = next;
        if (next) nextTick(attachLocalPreview);
        if (next) errorMsg.value = '';
    } catch (e) {
        errorMsg.value = mapMediaError(e);
    }
}
async function toggleMic() {
    if (!room) return;
    const next = !micOn.value;
    try {
        await room.localParticipant.setMicrophoneEnabled(next);
        micOn.value = next;
    } catch (e) {
        errorMsg.value = mapMediaError(e);
    }
}

async function stopRoom(notify = true) {
    try { room?.disconnect(); } catch (e) {}
    room = null; camOn.value = false; micOn.value = false;
    if (notify) {
        await fetch(props.routes.end, {
            method: 'POST', credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
        }).catch(() => {});
    }
    status.value = 'idle';
    // Yayın kapandı → teklifleri almaya veri-only bağlantı ile devam et
    if (!unmounting) connectDataOnly();
}

async function endBroadcast() {
    if (!await swalConfirm({ title: 'Yayını sonlandır?', text: 'Canlı yayın kapatılacak.', icon: 'warning', confirmButtonText: 'Evet, bitir' })) return;
    await stopRoom(true);
}

async function sellTo(bid) {
    if (countdown.active) return;
    if (!await swalConfirm({ title: 'Satışı başlat', text: `${bid.name} — ${formatPrice(bid.amount)} için 10 sn geri sayım başlayacak. Bu sürede yeni teklif gelirse satış iptal olur.`, icon: 'question', confirmButtonColor: '#16a34a', confirmButtonText: 'Başlat' })) return;
    try {
        const res = await fetch(props.routes.start_countdown, {
            method: 'POST', credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({}),
        });
        const j = await res.json();
        if (!res.ok || !j.success) { swalToast('error', j.message || 'Geri sayım başlatılamadı.'); return; }
        countdown.active = true;
        countdown.secs = j.seconds || 10;
        countdown.bid = { id: j.bid_id, name: bid.name, amount: bid.amount };
        cdTimer = setInterval(() => {
            countdown.secs -= 1;
            if (countdown.secs <= 0) {
                if (cdTimer) { clearInterval(cdTimer); cdTimer = null; }
                const bidId = countdown.bid?.id;
                countdown.active = false;
                if (bidId) finalizeSale(bidId);
            }
        }, 1000);
    } catch (e) { swalToast('error', 'Bağlantı hatası.'); }
}

async function finalizeSale(bidId) {
    try {
        const res = await fetch(props.routes.sell, {
            method: 'POST', credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({ bid_id: bidId }),
        });
        const j = await res.json();
        if (res.ok && j.success) {
            fireBig();
            swalToast('success', `Satıldı! Kazanan: ${j.winner_name} — Sipariş: ${j.order_number}`);
            setTimeout(() => { stopRoom(true); router.visit(props.routes.view_public); }, 2400);
        } else {
            swalToast('error', j.message || 'Satış yapılamadı.');
        }
    } catch (e) { swalToast('error', 'Satış sırasında hata oluştu.'); }
}

function formatPrice(v) {
    return new Intl.NumberFormat('tr-TR').format(Math.round(v || 0)) + ' ₺';
}

/* ---------------- CHAT (polling) ---------------- */
const chat = reactive({ messages: [], input: '', lastId: 0, error: '' });
const chatBox = ref(null);
let chatTimer = null;

async function pollChat() {
    try {
        const res = await fetch(`${props.routes.chat_poll}?after=${chat.lastId}`, { credentials: 'include' });
        const j = await res.json();
        if (j.messages?.length) {
            chat.messages.push(...j.messages);
            chat.lastId = j.messages[j.messages.length - 1].id;
            // Bellek koruması: en fazla 200 mesaj tut
            if (chat.messages.length > 200) chat.messages.splice(0, chat.messages.length - 200);
            await nextTick();
            if (chatBox.value) chatBox.value.scrollTop = chatBox.value.scrollHeight;
        }
    } catch (e) { /* sessiz geç */ }
}

async function sendChat() {
    const text = chat.input.trim();
    if (!text) return;
    chat.input = ''; chat.error = '';
    try {
        const res = await fetch(props.routes.chat_store, {
            method: 'POST', credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({ message: text }),
        });
        const j = await res.json();
        if (!res.ok) { chat.error = j.message || 'Mesaj gönderilemedi.'; return; }
        // Kendi mesajımızı hemen göster (poll toOthers olduğu için)
        if (j.id && !chat.messages.some((m) => m.id === j.id)) {
            chat.messages.push(j); chat.lastId = Math.max(chat.lastId, j.id);
            await nextTick(); if (chatBox.value) chatBox.value.scrollTop = chatBox.value.scrollHeight;
        }
    } catch (e) { chat.error = 'Bağlantı hatası.'; }
}

onMounted(() => {
    try { window.scrollTo(0, 0); } catch (e) {}
    connectDataOnly();
    pollChat();
    chatTimer = setInterval(pollChat, 3000);
});

onBeforeUnmount(() => {
    unmounting = true;
    if (chatTimer) clearInterval(chatTimer);
    if (cdTimer) clearInterval(cdTimer);
    disconnectDataOnly();
    stopPreview();
    stopRoom(false);
});
</script>

<template>
    <Head :title="`Canlı Yayın — ${auction.title}`" />

    <div class="bc-root" data-testid="seller-broadcast-page">
        <div class="bc-topbar">
            <div>
                <h1 class="bc-title"><i class="bi bi-broadcast"></i> Canlı Yayın</h1>
                <div class="bc-sub">{{ auction.title }} · #{{ auction.id }}</div>
            </div>
            <div class="bc-top-actions">
                <span v-if="status === 'live'" class="bc-live-badge" data-testid="broadcast-live-badge">
                    <span class="bc-dot"></span> CANLI
                </span>
                <span class="bc-viewers"><i class="bi bi-eye"></i> {{ viewers }}</span>
                <Link :href="routes.view_public" class="bc-ghost-btn"><i class="bi bi-box-arrow-up-right"></i> İzleyici Görünümü</Link>
            </div>
        </div>

        <div class="bc-hero" data-testid="broadcast-hero">
            <div class="bc-hero-cell bc-hero-primary">
                <span class="bc-hero-lbl">Güncel Teklif</span>
                <span class="bc-hero-val bc-hero-price" data-testid="broadcast-hero-price">{{ formatPrice(currentPrice) }}</span>
            </div>
            <div class="bc-hero-cell">
                <span class="bc-hero-lbl">Son Teklifi Veren</span>
                <span class="bc-hero-val" data-testid="broadcast-hero-bidder"><i class="bi bi-person-fill"></i> {{ lastBidder }}</span>
            </div>
            <div class="bc-hero-cell">
                <span class="bc-hero-lbl">Teklif Sayısı</span>
                <span class="bc-hero-val" data-testid="broadcast-hero-count"><i class="bi bi-hammer"></i> {{ bidCount }}</span>
            </div>
            <div class="bc-hero-cell" :class="{ 'bc-hero-crit': timeLeft.critical }">
                <span class="bc-hero-lbl">Kalan Süre</span>
                <span class="bc-hero-val" data-testid="broadcast-hero-time"><i class="bi bi-clock"></i> {{ timeLeft.text }}</span>
            </div>
        </div>

        <div class="bc-grid">
            <div class="bc-main">
                <div class="bc-video-wrap">
                    <video ref="videoEl" class="bc-video" autoplay playsinline muted data-testid="broadcast-video"></video>
                    <div v-if="status !== 'live' && status !== 'preview'" class="bc-video-overlay">
                        <i class="bi bi-camera-video-off"></i>
                        <p v-if="status === 'connecting'">Bağlanıyor…</p>
                        <template v-else>
                            <p class="bc-ov-title">Yayına hazır mısın?</p>
                            <p class="bc-ov-hint">Önce "Kamerayı Önizle" ile kendini kontrol et, sonra "Yayını Başlat"a bas.</p>
                        </template>
                    </div>
                    <span v-if="status === 'preview'" class="bc-preview-tag"><i class="bi bi-eye"></i> Önizleme (yayında değil)</span>

                    <div v-if="countdown.active" class="bc-cd-badge" data-testid="seller-countdown">
                        <span class="bc-cd-ring">{{ countdown.secs }}</span>
                        <span class="bc-cd-txt">Satışa<br>saniye</span>
                    </div>

                    <div class="bc-fab" :class="{ 'bc-fab-live': status === 'live' }" data-testid="broadcast-controls">
                        <template v-if="status !== 'live'">
                            <button class="bc-fab-btn" @click="previewCamera" :disabled="status === 'connecting'"
                                    data-testid="broadcast-preview" title="Kamerayı Önizle" aria-label="Kamerayı Önizle">
                                <i class="bi bi-eye"></i><span class="bc-fab-lbl">Önizle</span>
                            </button>
                            <button class="bc-fab-btn bc-fab-golive" @click="goLive" :disabled="status === 'connecting'"
                                    data-testid="broadcast-go-live" :title="status === 'connecting' ? 'Bağlanıyor' : 'Yayını Başlat'">
                                <i class="bi bi-broadcast"></i><span class="bc-fab-lbl">{{ status === 'connecting' ? 'Bağlanıyor…' : 'Yayını Başlat' }}</span>
                            </button>
                            <button class="bc-fab-btn" @click="copyViewerLink"
                                    data-testid="broadcast-copy-link" :title="copied ? 'Kopyalandı' : 'İzleyici Linki'" aria-label="İzleyici Linki">
                                <i class="bi" :class="copied ? 'bi-check2' : 'bi-link-45deg'"></i><span class="bc-fab-lbl">{{ copied ? 'Kopyalandı!' : 'Link' }}</span>
                            </button>
                        </template>
                        <template v-else>
                            <button class="bc-fab-btn" :class="micOn ? 'is-on' : 'is-off'" @click="toggleMic"
                                    data-testid="broadcast-toggle-mic" :title="micOn ? 'Mikrofonu Kapat' : 'Mikrofonu Aç'" :aria-label="micOn ? 'Mikrofonu Kapat' : 'Mikrofonu Aç'">
                                <i class="bi" :class="micOn ? 'bi-mic-fill' : 'bi-mic-mute-fill'"></i>
                            </button>
                            <button class="bc-fab-btn" :class="camOn ? 'is-on' : 'is-off'" @click="toggleCam"
                                    data-testid="broadcast-toggle-cam" :title="camOn ? 'Kamerayı Kapat' : 'Kamerayı Aç'" :aria-label="camOn ? 'Kamerayı Kapat' : 'Kamerayı Aç'">
                                <i class="bi" :class="camOn ? 'bi-camera-video-fill' : 'bi-camera-video-off-fill'"></i>
                            </button>
                            <button class="bc-fab-btn" @click="copyViewerLink"
                                    data-testid="broadcast-copy-link" :title="copied ? 'Kopyalandı' : 'İzleyici Linki'" aria-label="İzleyici Linki">
                                <i class="bi" :class="copied ? 'bi-check2' : 'bi-link-45deg'"></i>
                            </button>
                            <button class="bc-fab-btn bc-fab-end" @click="endBroadcast"
                                    data-testid="broadcast-end" title="Yayını Bitir" aria-label="Yayını Bitir">
                                <i class="bi bi-stop-fill"></i>
                            </button>
                        </template>
                    </div>
                </div>

                <p v-if="errorMsg" class="bc-error" data-testid="broadcast-error">{{ errorMsg }}</p>

                <div class="bc-panel">
                    <div class="bc-panel-title"><i class="bi bi-hammer"></i> Teklifler ({{ bidCount }})</div>
                    <div class="bc-bids" data-testid="broadcast-bids">
                        <div v-if="!bidList.length" class="bc-empty">Henüz teklif yok.</div>
                        <div v-for="(b, i) in bidList" :key="b.id" class="bc-bid" :class="{ 'bc-bid-top': i === 0 }">
                            <div class="bc-bid-info">
                                <span class="bc-bid-name">{{ b.name }}</span>
                                <span class="bc-bid-time">{{ b.time }}</span>
                            </div>
                            <div class="bc-bid-right">
                                <span class="bc-bid-amount">{{ formatPrice(b.amount) }}</span>
                                <button class="bc-sell-btn" @click="sellTo(b)" :disabled="countdown.active" data-testid="broadcast-sell-btn">{{ countdown.active ? countdown.secs + 's' : 'Sat' }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bc-chat" data-testid="broadcast-chat">
                <div class="bc-chat-head"><i class="bi bi-chat-dots"></i> Canlı Sohbet</div>
                <div ref="chatBox" class="bc-chat-body" data-testid="broadcast-chat-messages">
                    <div v-if="!chat.messages.length" class="bc-empty">Henüz mesaj yok. İlk mesajı sen yaz!</div>
                    <div v-for="m in chat.messages" :key="m.id" class="bc-msg" :class="{ 'bc-msg-seller': m.is_seller }">
                        <span class="bc-msg-user">{{ m.user_name }}<i v-if="m.is_seller" class="bi bi-patch-check-fill"></i>:</span>
                        <span class="bc-msg-text">{{ m.message }}</span>
                    </div>
                </div>
                <form class="bc-chat-form" @submit.prevent="sendChat">
                    <input v-model="chat.input" type="text" maxlength="300" placeholder="Mesaj yaz…"
                           data-testid="broadcast-chat-input" />
                    <button type="submit" data-testid="broadcast-chat-send"><i class="bi bi-send"></i></button>
                </form>
                <div v-if="chat.error" class="bc-chat-error">{{ chat.error }}</div>
            </div>
        </div>
    </div>
</template>

<style scoped src="../../../css/pages/seller-broadcast.css"></style>

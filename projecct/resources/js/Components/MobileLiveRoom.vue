<script setup>
/*
 * MobileLiveRoom — TikTok/Kick tarzı mobil tam-ekran canlı yayın deneyimi.
 * - Dikey tam ekran video (LiveKit viewer, otomatik bağlan, muted autoplay + tek dokunuşla ses)
 * - Birleşik kayan feed: sohbet mesajları + gelen teklifler tek listede (teklif satırı vurgulu)
 * - Alt sabit yarı saydam input: "Sohbet / Teklif" toggle; teklifte tek-dokunuş onay
 * - Üst overlay: güncel en yüksek teklif + kalan süre + izleyici + CANLI
 * Legacy auction-show.js mobilde bu deneyimi yönetmez; bileşen kendi LiveKit odasını kurar.
 */
import { ref, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue';
import { connectRoom } from '@/composables/useLiveKit';
import { RoomEvent, Track } from 'livekit-client';

const props = defineProps({ a: Object, config: Object, room: Object, streamState: String });
const emit = defineEmits(['close']);

const videoEl = ref(null);
const feedBox = ref(null);
const videoReady = ref(false);
let readyFallback = null;

// Yayın durumu Show.vue'daki paylaşılan bağlantıdan gelir → tam ekran geçişinde SIFIRLANMAZ.
const connState = computed(() => props.streamState || ((props.a?.is_live && !props.a?.has_finished) ? 'checking' : 'offline')); // checking|live|offline
const muted = ref(true);
const viewers = ref(0);
const feed = ref([]);
let feedSeq = 0;

const step = Number(props.config?.min_increment) || 0;
const minBid = ref(Number(props.a?.min_bid) || 0);
const topPrice = ref(props.a?.display_price || '—');
const bidCount = ref(Number(props.a?.bid_count) || 0);

const mode = ref('chat');        // 'chat' | 'bid'
const draft = ref('');           // sohbet metni
const bidDraft = ref('');        // teklif tutarı
const sending = ref(false);
const pendingBid = ref(null);    // onay bekleyen teklif tutarı
const errorMsg = ref('');

const remaining = ref(Number(props.config?.remaining_secs) || 0);
let clockTimer = null;
let chatTimer = null;
let chatLastId = 0;
let bidLastId = Number(props.config?.last_bid_id) || 0;

const isAuth = computed(() => props.config?.is_auth === '1');
const csrf = () => props.config?.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';
const fmtTL = (v) => new Intl.NumberFormat('tr-TR').format(Math.round(Number(v) || 0)) + ' ₺';

const quickBids = computed(() => {
    const base = minBid.value || 0;
    return [
        { amount: base, label: fmtTL(base) },
        { amount: base + step, label: fmtTL(base + step) },
        { amount: base + step * 5, label: fmtTL(base + step * 5) },
    ];
});

const remainingText = computed(() => {
    let s = remaining.value;
    if (s <= 0) return 'Bitti';
    const d = Math.floor(s / 86400), h = Math.floor((s % 86400) / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
    if (d > 0) return `${d}g ${h}s`;
    if (h > 0) return `${h}s ${m}dk`;
    if (m > 0) return `${m}dk ${sec}sn`;
    return `${sec}sn`;
});

const seenChatIds = new Set();
const seenBidIds = new Set();
function pushFeed(item) {
    // id bazlı dedupe: optimistic ekleme + poll/broadcast yarışında aynı kayıt iki kez eklenmesin.
    if (item.kind === 'chat' && item.id != null) {
        if (seenChatIds.has(item.id)) return;
        seenChatIds.add(item.id);
    } else if (item.kind === 'bid' && item.bidId != null) {
        if (seenBidIds.has(item.bidId)) return;
        seenBidIds.add(item.bidId);
    }
    feed.value.push({ _k: ++feedSeq, ...item });
    if (feed.value.length > 60) feed.value.splice(0, feed.value.length - 60);
    nextTick(() => {
        const el = feedBox.value;
        if (el) el.scrollTop = el.scrollHeight;
    });
}

function onData(msg) {
    if (!msg || !msg.type) return;
    if (msg.type === 'new-bid') {
        if (msg.bid_id && msg.bid_id <= bidLastId) return;
        if (msg.bid_id) bidLastId = msg.bid_id;
        if (msg.display_price) topPrice.value = msg.display_price;
        if (typeof msg.total_bids !== 'undefined') bidCount.value = msg.total_bids;
        if (msg.amount) minBid.value = Number(msg.amount) + step;
        pushFeed({ kind: 'bid', bidId: msg.bid_id, name: msg.bidder_name || msg.name || 'Bir alıcı', amount: fmtTL(msg.amount) });
    } else if (msg.type === 'chat') {
        if (msg.id && msg.id <= chatLastId) return;
        if (msg.id) chatLastId = msg.id;
        pushFeed({ kind: 'chat', id: msg.id, name: msg.user_name || 'Kullanıcı', text: msg.message || '', seller: !!msg.is_seller });
    }
}

function findVideoTrack() {
    if (!props.room) return null;
    let t = null;
    props.room.remoteParticipants.forEach((p) => p.trackPublications.forEach((pub) => {
        if (pub.isSubscribed && pub.track && pub.track.kind === Track.Kind.Video) t = pub.track;
    }));
    return t;
}
function attachVideo() {
    const t = findVideoTrack();
    if (t && videoEl.value) { try { t.attach(videoEl.value); } catch (e) {} }
}
function updateViewers() {
    if (props.room) viewers.value = Math.max(0, (props.room.numParticipants || 1) - 1);
}
// Paylaşılan oda dinleyicileri — kendi bağlantımızı KURMUYORUZ (Show.vue yönetir).
function onRoomData(payload) { try { onData(JSON.parse(new TextDecoder().decode(payload))); } catch (e) {} }
const onTrackSub = (track) => { if (track && track.kind === Track.Kind.Video) attachVideo(); };
const onPartChange = () => updateViewers();

function toggleMute() {
    muted.value = !muted.value;
    if (videoEl.value) videoEl.value.muted = muted.value;
    if (props.room && props.room.setStreamMuted) { try { props.room.setStreamMuted(muted.value); } catch (e) {} }
}

async function loadChatHistory() {
    if (!props.config?.chat_poll_url) return;
    try {
        const res = await fetch(props.config.chat_poll_url + '?after=' + chatLastId, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) return;
        const d = await res.json();
        (d.messages || []).forEach((m) => {
            if (m.id > chatLastId) { chatLastId = m.id; pushFeed({ kind: 'chat', id: m.id, name: m.user_name, text: m.message, seller: !!m.is_seller }); }
        });
    } catch (e) { /* sessiz */ }
}

async function sendChat() {
    const text = (draft.value || '').trim();
    if (!text || sending.value) return;
    errorMsg.value = '';
    sending.value = true;
    try {
        const res = await fetch(props.config.chat_store_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
            body: JSON.stringify({ message: text }),
        });
        const d = await res.json().catch(() => ({}));
        if (res.ok) {
            draft.value = '';
            if (d.id && d.id > chatLastId) { chatLastId = d.id; }
            pushFeed({ kind: 'chat', id: d.id, name: d.user_name || 'Sen', text: d.message || text, seller: !!d.is_seller, mine: true });
        } else {
            errorMsg.value = d.message || 'Mesaj gönderilemedi.';
        }
    } catch (e) { errorMsg.value = 'Bağlantı hatası.'; }
    sending.value = false;
}

function askBid() {
    errorMsg.value = '';
    const amount = parseFloat(bidDraft.value);
    if (!amount || amount < minBid.value) {
        errorMsg.value = `En az ${fmtTL(minBid.value)} girmelisiniz.`;
        return;
    }
    pendingBid.value = amount;
}

function quickPick(amount) {
    bidDraft.value = amount;
    errorMsg.value = '';
    pendingBid.value = amount;
}

async function confirmBidNow() {
    const amount = pendingBid.value;
    if (!amount || sending.value) return;
    sending.value = true;
    errorMsg.value = '';
    try {
        const res = await fetch(props.config.bid_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ amount }),
        });
        const d = await res.json().catch(() => ({}));
        if (res.ok) {
            bidDraft.value = '';
            pendingBid.value = null;
            if (d.bid_id && d.bid_id > bidLastId) bidLastId = d.bid_id;
            if (d.display_price) topPrice.value = d.display_price;
            if (typeof d.total_bids !== 'undefined') bidCount.value = d.total_bids;
            minBid.value = Number(d.amount) + step;
            pushFeed({ kind: 'bid', bidId: d.bid_id, name: (d.bidder_name || 'Sen'), amount: fmtTL(d.amount), mine: true });
        } else {
            errorMsg.value = d.message || 'Teklif verilemedi.';
        }
    } catch (e) { errorMsg.value = 'Bağlantı hatası.'; }
    sending.value = false;
}

function cancelBid() { pendingBid.value = null; }

function onSubmit() {
    if (!isAuth.value) { window.location.href = props.config.login_url; return; }
    if (mode.value === 'chat') sendChat();
    else askBid();
}

onMounted(async () => {
    // Sayfa arka planı kaymasın
    document.body.style.overflow = 'hidden';
    if (videoEl.value) videoEl.value.muted = true;
    // Güvenlik: 'playing'/'loadeddata' hiç gelmezse (nadir autoplay engeli) yine de fade-in yap → siyah kalmasın
    readyFallback = setTimeout(() => { videoReady.value = true; }, 1200);
    // İlk teklifleri feed'e koy (varsa) — en yeni altta
    if (Array.isArray(props.a?.bids)) {
        [...props.a.bids].reverse().forEach((b) => pushFeed({ kind: 'bid', name: b.name, amount: b.amount_fmt, seed: true }));
    }
    await loadChatHistory();
    // Kendi LiveKit bağlantımızı KURMUYORUZ — Show.vue'daki paylaşılan odayı kullan.
    // Video track'i bu <video>'ya da attach et (satır-içi bağlantı hiç kopmaz → sıfır reconnect).
    if (props.room) {
        attachVideo();
        if (props.room.isStreamMuted) { try { muted.value = props.room.isStreamMuted(); if (videoEl.value) videoEl.value.muted = muted.value; } catch (e) {} }
        props.room.on(RoomEvent.TrackSubscribed, onTrackSub);
        props.room.on(RoomEvent.DataReceived, onRoomData);
        props.room.on(RoomEvent.ParticipantConnected, onPartChange);
        props.room.on(RoomEvent.ParticipantDisconnected, onPartChange);
        updateViewers();
    }
    clockTimer = setInterval(() => { if (remaining.value > 0) remaining.value--; }, 1000);
    chatTimer = setInterval(loadChatHistory, 4000);
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    if (readyFallback) clearTimeout(readyFallback);
    if (clockTimer) clearInterval(clockTimer);
    if (chatTimer) clearInterval(chatTimer);
    // Paylaşılan odayı KAPATMA — sadece kendi <video>'muzdan track'i ayır ve dinleyicileri kaldır.
    if (props.room) {
        try { props.room.off(RoomEvent.TrackSubscribed, onTrackSub); } catch (e) {}
        try { props.room.off(RoomEvent.DataReceived, onRoomData); } catch (e) {}
        try { props.room.off(RoomEvent.ParticipantConnected, onPartChange); } catch (e) {}
        try { props.room.off(RoomEvent.ParticipantDisconnected, onPartChange); } catch (e) {}
    }
    const t = findVideoTrack();
    if (t && videoEl.value) { try { t.detach(videoEl.value); } catch (e) {} }
});

function close() { emit('close'); }
</script>

<template>
    <div class="mlr" data-testid="mobile-live-room">
        <video ref="videoEl" class="mlr-video" :class="{ ready: videoReady }" autoplay playsinline muted
               @click="toggleMute" @loadeddata="videoReady = true" @playing="videoReady = true" data-testid="mlr-video"></video>

        <div v-if="connState === 'checking'" class="mlr-state" data-testid="mlr-checking">
            <div class="mlr-spin"></div>
            <p>Yayına bağlanılıyor…</p>
        </div>
        <div v-else-if="connState === 'offline'" class="mlr-state" data-testid="mlr-offline">
            <i class="bi bi-camera-video-off"></i>
            <p>Satıcı henüz yayın başlatmadı</p>
        </div>

        <div class="mlr-top">
            <div class="mlr-top-left">
                <img class="mlr-ava" :src="a.seller?.profile_img" :alt="a.seller?.name">
                <div class="mlr-seller">
                    <div class="mlr-seller-name">{{ a.seller?.name }}</div>
                    <div class="mlr-viewers"><i class="bi bi-people-fill"></i> {{ viewers }}</div>
                </div>
                <span v-if="connState === 'live'" class="mlr-live" data-testid="mlr-live-pill"><span class="dot"></span> CANLI</span>
            </div>
            <div class="mlr-top-right">
                <button class="mlr-icon" @click="toggleMute" :title="muted ? 'Sesi aç' : 'Sesi kapat'" data-testid="mlr-mute">
                    <i class="bi" :class="muted ? 'bi-volume-mute' : 'bi-volume-up'"></i>
                </button>
                <button class="mlr-icon" @click="close" title="Kapat" data-testid="mlr-close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        <div class="mlr-price">
            <div class="mlr-price-item">
                <span class="lbl">Güncel</span>
                <span class="val" data-testid="mlr-top-price">{{ topPrice }}</span>
            </div>
            <div class="mlr-price-item">
                <span class="lbl"><i class="bi bi-clock"></i></span>
                <span class="val" :class="{ crit: remaining > 0 && remaining <= 60 }" data-testid="mlr-remaining">{{ remainingText }}</span>
            </div>
            <div class="mlr-price-item">
                <span class="lbl">Teklif</span>
                <span class="val">{{ bidCount }}</span>
            </div>
        </div>

        <div ref="feedBox" class="mlr-feed" data-testid="mlr-feed">
            <div v-for="it in feed" :key="it._k"
                 class="mlr-row" :class="it.kind === 'bid' ? 'is-bid' : 'is-chat'"
                 :data-testid="it.kind === 'bid' ? 'mlr-feed-bid' : 'mlr-feed-chat'">
                <template v-if="it.kind === 'bid'">
                    <i class="bi bi-hammer"></i>
                    <span class="who">{{ it.name }}</span>
                    <span class="act">teklif verdi</span>
                    <span class="amt">{{ it.amount }}</span>
                </template>
                <template v-else>
                    <span class="who" :class="{ seller: it.seller }">{{ it.name }}<span v-if="it.seller" class="badge-seller">SATICI</span></span>
                    <span class="txt">{{ it.text }}</span>
                </template>
            </div>
        </div>

        <div class="mlr-bottom">
            <template v-if="isAuth && a.is_active && !a.is_owner">
                <div v-if="pendingBid" class="mlr-confirm" data-testid="mlr-bid-confirm">
                    <span><b>{{ fmtTL(pendingBid) }}</b> teklif verilsin mi?</span>
                    <div class="mlr-confirm-btns">
                        <button class="mlr-btn ghost" @click="cancelBid" data-testid="mlr-bid-cancel">Vazgeç</button>
                        <button class="mlr-btn go" :disabled="sending" @click="confirmBidNow" data-testid="mlr-bid-confirm-yes">
                            <i class="bi bi-lightning-charge-fill"></i> Onayla
                        </button>
                    </div>
                </div>

                <div v-if="mode === 'bid' && !pendingBid" class="mlr-chips" data-testid="mlr-quick-chips">
                    <button v-for="(q, i) in quickBids" :key="i" class="mlr-chip" @click="quickPick(q.amount)" :data-testid="`mlr-quick-${i}`">{{ q.label }}</button>
                </div>

                <div v-if="errorMsg" class="mlr-err" data-testid="mlr-error">{{ errorMsg }}</div>

                <div v-if="!pendingBid" class="mlr-input-row">
                    <div class="mlr-toggle" data-testid="mlr-mode-toggle">
                        <button :class="{ on: mode === 'chat' }" @click="mode = 'chat'; errorMsg=''" data-testid="mlr-mode-chat">Sohbet</button>
                        <button :class="{ on: mode === 'bid' }" @click="mode = 'bid'; errorMsg=''" data-testid="mlr-mode-bid">Teklif</button>
                    </div>
                    <input v-if="mode === 'chat'" v-model="draft" class="mlr-input" type="text" maxlength="300"
                           placeholder="Mesaj yaz…" @keyup.enter="onSubmit" data-testid="mlr-chat-input">
                    <input v-else v-model="bidDraft" class="mlr-input" type="number" :min="minBid" :step="step"
                           :placeholder="`En az ${fmtTL(minBid)}`" @keyup.enter="onSubmit" data-testid="mlr-bid-input">
                    <button class="mlr-send" @click="onSubmit" :disabled="sending" data-testid="mlr-send">
                        <i class="bi" :class="mode === 'bid' ? 'bi-lightning-charge-fill' : 'bi-send-fill'"></i>
                    </button>
                </div>
            </template>
            <template v-else-if="a.is_owner">
                <div class="mlr-note">Bu sizin ilanınız — yayın yönetimi için satıcı panelini kullanın.</div>
            </template>
            <template v-else-if="!isAuth">
                <a :href="config.login_url" class="mlr-login" data-testid="mlr-login">Sohbet ve teklif için giriş yap</a>
            </template>
            <template v-else>
                <div class="mlr-note">Bu müzayede için teklif kapalı.</div>
            </template>
        </div>
    </div>
</template>

<style scoped src="../../css/components/mobile-live-room.css"></style>

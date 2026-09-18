// LiveKit (WebRTC SFU) yardımcı fonksiyonları.
// Yayıncı kamerayı/mikrofonu yayınlar; izleyiciler abone olup uzak video/ses track'lerini alır.
import { Room, RoomEvent, Track } from 'livekit-client';

// Backend'den kısa ömürlü katılım token'ı al. API secret ASLA istemciye gelmez.
export async function fetchToken(url, body, csrf) {
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf || '',
        },
        body: JSON.stringify(body || {}),
    });
    if (res.status === 503) {
        const j = await res.json().catch(() => ({}));
        const e = new Error(j.message || 'LiveKit yapılandırılmadı');
        e.code = 'not_configured';
        throw e;
    }
    if (!res.ok) throw new Error(`Token alınamadı (${res.status})`);
    return res.json();
}

export async function fetchLiveKitToken({ auctionSlug, role, csrf }) {
    return fetchToken('/livekit/token', { auction: auctionSlug, role }, csrf);
}

// Odaya bağlan. onVideo(el|track) ve onStatus geri çağrılarıyla UI güncellenir.
// tokenUrl + tokenBody verilirse o endpoint'ten token alınır (ör. DM odası); yoksa auction token'ı kullanılır.
// Reconnect: sekme arka plandan dönünce / ağ geri gelince / beklenmedik kopmada AYNI Room üzerinden
// otomatik yeniden bağlanır (caller referansı bozulmaz). room.disconnect() çağrılırsa (kasıtlı) durur.
export async function connectRoom({ auctionSlug, role, csrf, videoEl, onStatus, onParticipants, onData, tokenUrl, tokenBody, startAudioMuted = false }) {
    onStatus?.('connecting');

    const getCreds = () => (tokenUrl
        ? fetchToken(tokenUrl, tokenBody, csrf)
        : fetchLiveKitToken({ auctionSlug, role, csrf }));

    let creds = await getCreds();

    const room = new Room({ adaptiveStream: true, dynacast: true });

    // ── Ses kontrolü ──
    // Uzak ses track'leri kendi (gizli) <audio> elemanlarına bağlanır; mute bu elemanlar üzerinden yapılır.
    // (Eski davranışta ses #liveVideo'ya bağlı değildi; bu yüzden video.muted'ı değiştirmek sesi susturmuyordu.)
    const audioEls = [];
    room.__audioEls = audioEls;
    room.__streamMuted = !!startAudioMuted;
    room.setStreamMuted = (m) => {
        room.__streamMuted = !!m;
        audioEls.forEach((el) => { el.muted = !!m; if (!m) { try { el.play?.(); } catch (e) {} } });
    };
    room.isStreamMuted = () => room.__streamMuted;

    const attach = (track) => {
        if (track.kind === Track.Kind.Video) { if (videoEl) track.attach(videoEl); return; }
        if (track.kind === Track.Kind.Audio) {
            const el = track.attach();
            el.autoplay = true;
            el.muted = room.__streamMuted;
            el.style.display = 'none';
            document.body.appendChild(el);
            audioEls.push(el);
            if (!room.__streamMuted) { try { el.play?.(); } catch (e) {} }
        }
    };

    room.on(RoomEvent.TrackSubscribed, (track) => attach(track));
    room.on(RoomEvent.TrackUnsubscribed, (track) => {
        try {
            const detached = track.detach();
            if (Array.isArray(detached)) detached.forEach((el) => {
                const i = audioEls.indexOf(el); if (i >= 0) audioEls.splice(i, 1);
                try { el.remove(); } catch (e) {}
            });
        } catch (e) {}
    });
    room.on(RoomEvent.ParticipantConnected, () => onParticipants?.(room.numParticipants));
    room.on(RoomEvent.ParticipantDisconnected, () => onParticipants?.(room.numParticipants));

    // Gerçek-zamanlı veri paketleri (teklif / geri sayım / satış olayları)
    if (onData) {
        room.on(RoomEvent.DataReceived, (payload) => {
            try { onData(JSON.parse(new TextDecoder().decode(payload))); } catch (e) { /* yok say */ }
        });
    }

    // ── Otomatik yeniden bağlanma katmanı ──
    let closedByClient = false;
    let reconnecting = false;

    const reconnect = async () => {
        if (closedByClient || reconnecting) return;
        // Zaten bağlı/bağlanıyorsa dokunma (SDK'nın kendi reconnect'i sürüyor olabilir)
        if (room.state === 'connected' || room.state === 'connecting' || room.state === 'reconnecting') return;
        reconnecting = true;
        onStatus?.('connecting');
        try {
            creds = await getCreds(); // token süresi dolmuş olabilir → tazele
            await room.connect(creds.server_url, creds.participant_token, { autoSubscribe: true });
            onStatus?.('connected');
            onParticipants?.(room.numParticipants);
        } catch (e) {
            // başarısızsa tekrar dene (kapatılmadıysa)
            if (!closedByClient) setTimeout(() => { reconnecting = false; reconnect(); }, 2000);
            return;
        }
        reconnecting = false;
    };

    room.on(RoomEvent.Disconnected, () => {
        onStatus?.('disconnected');
        // Kasıtlı değilse kısa gecikmeyle yeniden bağlan
        if (!closedByClient) setTimeout(reconnect, 500);
    });

    const onVisible = () => { if (document.visibilityState === 'visible') reconnect(); };
    document.addEventListener('visibilitychange', onVisible);
    window.addEventListener('online', reconnect);
    window.addEventListener('focus', onVisible);

    // room.disconnect() = kasıtlı kapatma (route değişimi/unmount) → reconnect'i kapat, dinleyicileri temizle
    const origDisconnect = room.disconnect.bind(room);
    room.disconnect = (...args) => {
        closedByClient = true;
        document.removeEventListener('visibilitychange', onVisible);
        window.removeEventListener('online', reconnect);
        window.removeEventListener('focus', onVisible);
        return origDisconnect(...args);
    };

    await room.connect(creds.server_url, creds.participant_token, { autoSubscribe: true });
    onStatus?.('connected');
    onParticipants?.(room.numParticipants);

    // Bize katılmadan önce yayınlanmış track'leri de bağla
    room.remoteParticipants.forEach((p) => {
        p.trackPublications.forEach((pub) => { if (pub.isSubscribed && pub.track) attach(pub.track); });
    });

    return room;
}

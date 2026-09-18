// Şık, hafif konfeti efektleri (canvas-confetti). Teklifte küçük, satışta büyük patlama.
import confetti from 'canvas-confetti';

const BRAND = ['#155EEF', '#22c55e', '#F5A623', '#8b5cf6', '#ffffff'];

// Yeni teklif geldiğinde: zarif, küçük patlama. origin { x, y } (0-1) ekran oranı.
export function fireSmall(origin = { x: 0.5, y: 0.45 }) {
    confetti({
        particleCount: 46,
        spread: 62,
        startVelocity: 30,
        gravity: 0.9,
        scalar: 0.8,
        ticks: 110,
        origin,
        colors: BRAND,
        disableForReducedMotion: true,
    });
}

// Satış tamamlandığında: büyük, kutlama havai fişeği (~1.2 sn).
export function fireBig() {
    confetti({
        particleCount: 170,
        spread: 100,
        startVelocity: 46,
        gravity: 0.95,
        scalar: 1.1,
        origin: { x: 0.5, y: 0.42 },
        colors: BRAND,
        disableForReducedMotion: true,
    });

    const end = Date.now() + 1200;
    (function frame() {
        confetti({ particleCount: 7, angle: 60, spread: 72, startVelocity: 42, origin: { x: 0, y: 0.72 }, colors: BRAND, disableForReducedMotion: true });
        confetti({ particleCount: 7, angle: 120, spread: 72, startVelocity: 42, origin: { x: 1, y: 0.72 }, colors: BRAND, disableForReducedMotion: true });
        if (Date.now() < end) requestAnimationFrame(frame);
    })();
}

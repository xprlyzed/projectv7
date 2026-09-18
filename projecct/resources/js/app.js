import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'artirdim';

// Metronic (KeenThemes) bileşenlerini Inertia gezinmesinden sonra yeniden başlat
function initKT() {
    try { window.KTComponents && window.KTComponents.init(); } catch (e) {}
}
window.initKT = initKT;

function hideAppLoader() {
    const loader = document.getElementById('app-loader');
    if (!loader) return;
    loader.classList.add('app-loader-hide');
    setTimeout(() => { loader.remove(); }, 500);
}

createInertiaApp({
    title: (title) => (title ? `${appName} | ${title}` : appName),
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: { color: '#155eef' },
}).then(() => {
    setTimeout(initKT, 30);
    revealWhenReady();
});

// İkon fontları (bootstrap-icons / keenicons) hazır olana kadar yükleme
// katmanını tut → ikonlar "geç patlamaz", açılış yumuşak/profesyonel olur.
// En fazla 1200ms bekle (font gelmezse yine de aç).
function revealWhenReady() {
    const cap = new Promise((r) => setTimeout(r, 1200));
    const fonts = (document.fonts && document.fonts.ready) ? document.fonts.ready : Promise.resolve();
    Promise.race([fonts, cap]).then(() => requestAnimationFrame(hideAppLoader));
}

router.on('navigate', () => setTimeout(initKT, 60));

/**
 * Scroll yönetimi (SPA gezinme)
 * - Yeni sayfa (ileri gezinme)  → her zaman en üstten başla
 * - Tarayıcı geri/ileri (popstate) → önceki scroll pozisyonu korunur (Inertia geri yükler)
 * - preserveScroll:true olan istekler (filtre/teklif vb.) → dokunma
 * Desktop + mobil için aynı davranış.
 */
if ('scrollRestoration' in window.history) {
    window.history.scrollRestoration = 'manual';
}

// Bu Metronic layout'ta GERÇEK kaydırılan element <body id="kt_app_body">'dir
// (html+body'de `overflow: hidden auto`). window/documentElement hep 0 kalır,
// bu yüzden Inertia'nın dahili scroll geri-yükleme mekanizması bu app'te çalışmaz.
// Pozisyonu kendimiz body üzerinden, URL bazlı bir harita ile yönetiyoruz.

const scrollMap = {};
let popState = false;
let ticking = false;
let suppressSave = false; // programatik scroll'lar (reset/restore) kaydedilmesin

function currentScroll() {
    return document.body.scrollTop || document.scrollingElement?.scrollTop || window.scrollY || 0;
}

// Kullanıcı kaydırdıkça mevcut sayfanın pozisyonunu canlı olarak sakla.
// body kaydırıldığı için scroll olayını capture fazında document üzerinden yakalıyoruz.
document.addEventListener('scroll', () => {
    if (suppressSave || ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
        scrollMap[window.location.href] = currentScroll();
        ticking = false;
    });
}, true);

function hardScrollTop() {
    suppressSave = true;
    try { window.scrollTo(0, 0); } catch (e) {}
    try { if (document.scrollingElement) document.scrollingElement.scrollTop = 0; } catch (e) {}
    try { document.documentElement.scrollTop = 0; } catch (e) {}
    try { document.body.scrollTop = 0; } catch (e) {}
    ['kt_app_root', 'kt_app_page', 'kt_app_wrapper', 'kt_app_main', 'kt_app_content', 'kt_app_content_container']
        .forEach((id) => { const el = document.getElementById(id); if (el) { try { el.scrollTop = 0; } catch (e) {} } });
    setTimeout(() => { suppressSave = false; }, 120);
}

// Geri/ileri: kaydedilen pozisyonu, içerik yüklenene kadar ISRARLA (2sn) geri yükle.
// Inertia geri gezinmede sayfayı yeniden render ettiği için içerik geç büyüyebilir;
// bu yüzden hedefe ulaşana veya süre dolana kadar her 50ms'de bir uygularız.
let restoreTimer = null;
function restoreScroll(target) {
    if (restoreTimer) { clearInterval(restoreTimer); restoreTimer = null; }
    if (!target || target <= 0) return;
    suppressSave = true;
    const start = Date.now();
    restoreTimer = setInterval(() => {
        document.body.scrollTop = target;
        const reached = document.body.scrollTop >= target - 4;
        if (reached || Date.now() - start > 2000) {
            clearInterval(restoreTimer);
            restoreTimer = null;
            setTimeout(() => { suppressSave = false; }, 100);
        }
    }, 50);
}

// popstate → geri/ileri gezinme. location.href bu noktada zaten güncellenmiştir.
window.addEventListener('popstate', () => {
    popState = true;
    suppressSave = true; // takas sırasındaki clamp değeri kaydı ezmesin
    const target = scrollMap[window.location.href] || 0;
    restoreScroll(target);
    setTimeout(() => { popState = false; }, 2200); // güvenlik: takılı kalmasın
});

// Navigasyon BAŞLAR BAŞLAMAZ kaydetmeyi dondur: Inertia DOM'u takas ederken yeni
// (kısa) sayfa body scroll'u otomatik "clamp" olur ve bu değer yanlışlıkla ayrılan
// sayfanın pozisyonu olarak kaydedilirdi. 'before' ile bunu engelliyoruz.
router.on('before', () => { suppressSave = true; });

router.on('finish', (event) => {
    const visit = event && event.detail && event.detail.visit;
    // Geri/ileri → restore zaten popstate'te çalışıyor, dokunma
    if (popState) {
        return;
    }
    // Filtre/teklif gibi preserveScroll → pozisyonu koru, sadece kaydı tekrar aç
    if (visit && visit.preserveScroll) {
        setTimeout(() => { suppressSave = false; }, 120);
        return;
    }
    // Yeni sayfa (ileri gezinme) → en üste dön (Vue render + geç layout'u da ez)
    hardScrollTop();
    requestAnimationFrame(hardScrollTop);
    setTimeout(hardScrollTop, 60);
});

// app-loader safety: mount başarısız olsa bile overlay takılı kalmasın
window.addEventListener('load', () => setTimeout(hideAppLoader, 2500));

/**
 * BİRLEŞİK FLASH → TOAST KÖPRÜSÜ
 * Tüm başarı/hata mesajları backend flash üzerinden gelir ve tek bir toast
 * olarak (sağ-alt, otomatik kapanır) gösterilir.
 * - Partial reload'larda (router.reload({ only: [...] })) flash sunucudan
 *   yeniden gelmez; eski flash client'ta kalır. Bu yüzden partial ziyaretlerde
 *   toast GÖSTERİLMEZ (aksi halde "eklendi" sonra silmede tekrar çıkardı).
 * - Böylece "her sayfada çıkan eski toast" ve "iki toast birden" sorunları çözülür.
 */
const FLASH_KEYS = [
    ['success', 'success'], ['profile_success', 'success'], ['email_success', 'success'],
    ['password_success', 'success'], ['category_success', 'success'], ['settings_success', 'success'],
    ['status', 'success'], ['message', 'success'], ['contact_success', 'success'],
    ['error', 'error'], ['settings_error', 'error'],
];
const FLASH_FALLBACK = {
    contact_success: 'Mesajınız iletildi. En kısa sürede dönüş yapacağız.',
};

let __lastVisitPartial = false;
router.on('before', (event) => {
    const v = event && event.detail && event.detail.visit;
    __lastVisitPartial = !!(v && v.only && v.only.length);
});
router.on('success', (event) => {
    if (__lastVisitPartial) return; // partial reload → eski flash'ı tekrar gösterme
    const page = event && event.detail && event.detail.page;
    const flash = (page && page.props && page.props.flash) || {};
    for (const [key, type] of FLASH_KEYS) {
        const val = flash[key];
        if (!val) continue;
        const msg = (typeof val === 'string') ? val : (FLASH_FALLBACK[key] || null);
        if (msg && window.appToast) window.appToast(type, msg);
    }
});

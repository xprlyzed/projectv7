/**
 * Global uygulama toast'ı (birleşik).
 * Tüm başarı/hata bildirimleri sayfanın sağ-alt köşesinde çıkan .pf-toast
 * stiliyle gösterilir (profil kopyalama toast'ıyla aynı görünüm).
 * Kullanım: window.appToast('success' | 'error', 'Mesaj')
 */
(function () {
    'use strict';

    function appToast(type, msg) {
        if (!msg) return;
        type = type || 'success';
        var isErr = (type === 'error' || type === 'danger');
        var color = isErr ? '#ef4444' : '#22c55e';
        var icon = isErr ? 'bi-x-circle-fill' : 'bi-check-circle-fill';

        var host = document.getElementById('pf-toast-host');
        if (!host) {
            host = document.createElement('div');
            host.id = 'pf-toast-host';
            document.body.appendChild(host);
        }
        // Aynı anda birden fazla toast görünmesin: yeni gelince öncekini kaldır
        // (kullanıcı isteği: "eklendi" ve "silindi" birlikte görünmemeli)
        host.querySelectorAll('.pf-toast').forEach(function (el) { el.remove(); });

        var t = document.createElement('div');
        t.className = 'pf-toast';
        t.style.borderLeftColor = color;
        var i = document.createElement('i');
        i.className = 'bi ' + icon;
        i.style.color = color;
        var s = document.createElement('span');
        s.textContent = msg;
        t.appendChild(i);
        t.appendChild(s);
        host.appendChild(t);

        requestAnimationFrame(function () { t.classList.add('show'); });
        setTimeout(function () {
            t.classList.remove('show');
            setTimeout(function () { t.remove(); }, 300);
        }, 2600);
    }

    window.appToast = appToast;
    // Eski çağrılarla uyumluluk: ajaxToast(type, msg) → aynı toast
    window.ajaxToast = function (type, msg) { appToast(type, msg); };
})();

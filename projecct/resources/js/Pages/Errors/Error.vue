<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    status: { type: Number, default: 500 },
});

const MAP = {
    403: { title: 'Erişim reddedildi', message: 'Bu sayfayı görüntüleme yetkiniz bulunmuyor. Yanlış bir yere geldiyseniz ana sayfaya dönebilirsiniz.' },
    404: { title: 'Sayfa bulunamadı', message: 'Aradığınız sayfa taşınmış, kaldırılmış ya da hiç var olmamış olabilir.' },
    419: { title: 'Oturum süresi doldu', message: 'Güvenlik nedeniyle oturumunuz sona erdi. Lütfen sayfayı yenileyip yeniden deneyin.' },
    429: { title: 'Çok fazla istek', message: 'Kısa sürede çok fazla istek gönderildi. Lütfen bir an bekleyip yeniden deneyin.' },
    500: { title: 'Bir şeyler ters gitti', message: 'Sunucuda beklenmeyen bir hata oluştu. Ekibimiz durumdan haberdar edildi ve en kısa sürede çözecek.' },
    503: { title: 'Kısa bir bakımdayız', message: 'Sistemi iyileştiriyoruz. Çok kısa süre içinde yeniden hizmetinizdeyiz.' },
};

const info = computed(() => MAP[props.status] ?? {
    title: 'Beklenmeyen bir hata', message: 'İşleminiz sırasında bir sorun oluştu. Lütfen tekrar deneyin.',
});

const statusClass = computed(() => `is-${props.status}`);
const goBack = () => window.history.length > 1 ? window.history.back() : (window.location.href = '/');
</script>

<template>
    <Head :title="status + ' — ' + info.title" />

    <div class="stage" :class="statusClass" data-testid="error-page" :data-status="status">
        <div class="aurora aurora-a"></div>
        <div class="aurora aurora-b"></div>
        <div class="grid-veil"></div>

        <div class="content">
            <div class="numeral">
                <span class="numeral-ghost" aria-hidden="true">{{ status }}</span>
                <span class="numeral-main" data-testid="error-code">{{ status }}</span>
            </div>

            <h1 class="title" data-testid="error-title">{{ info.title }}</h1>
            <p class="message">{{ info.message }}</p>

            <div class="actions">
                <Link href="/" class="btn btn-primary" data-testid="error-home-button">
                    <i class="bi bi-house-door"></i> Ana sayfaya dön
                </Link>
                <button type="button" class="btn btn-ghost" @click="goBack" data-testid="error-back-button">
                    <i class="bi bi-arrow-left"></i> Geri dön
                </button>
            </div>

            <div class="links">
                <Link href="/browse/auctions" class="link"><i class="bi bi-hammer"></i> Müzayedeler</Link>
                <span class="dot"></span>
                <Link href="/browse/live" class="link"><i class="bi bi-broadcast"></i> Canlı</Link>
                <span class="dot"></span>
                <Link href="/support" class="link"><i class="bi bi-headset"></i> Yardım</Link>
            </div>
        </div>
    </div>
</template>

<style scoped src="../../../css/pages/error.css"></style>

<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';

const isDark = ref(true);

function applyTheme(mode) {
    document.documentElement.setAttribute('data-bs-theme', mode);
    localStorage.setItem('theme', mode);
    isDark.value = mode === 'dark';
}

function toggleTheme() {
    applyTheme(isDark.value ? 'light' : 'dark');
}

onMounted(() => {
    isDark.value = (localStorage.getItem('theme') || 'dark') === 'dark';
});
</script>

<template>
    <Head title="Yakında Canlıdayız" />

    <div class="mnt-page" data-testid="maintenance-page">
        <button class="mnt-theme-btn" @click="toggleTheme" data-testid="maintenance-theme-toggle" aria-label="Tema değiştir">
            <i class="bi" :class="isDark ? 'bi-moon-stars-fill' : 'bi-sun-fill'"></i>
        </button>

        <div class="mnt-card">
            <div class="mnt-logo">
                <div class="mnt-logo-bars">
                    <span class="mnt-bar"></span>
                    <span class="mnt-bar"></span>
                    <span class="mnt-bar"></span>
                </div>
                <h1 class="mnt-logo-name">artirdim<span>.com</span></h1>
            </div>

            <div class="mnt-badge">CANLI MÜZAYEDE SİSTEMİ</div>
            <h2 class="mnt-title">Çekici Deneyimler İçin Alanı Güncelliyoruz</h2>
            <p class="mnt-desc">Daha hızlı, güvenli ve kusursuz bir canlı açık artırma deneyimi için sistemimizi bakıma aldık. Kısa süre sonra çekiç yeniden vurulacak!</p>

            <div class="mnt-progress-wrap">
                <div class="mnt-progress-bar"></div>
            </div>
            <div class="mnt-progress-label">
                <span>İlerleme</span>
                <span>%75</span>
            </div>

            <div class="mnt-status-grid">
                <div class="mnt-status-item">
                    <span class="mnt-status-title">Durum</span>
                    <span class="mnt-status-value"><span class="mnt-dot"></span>Optimizasyon</span>
                </div>
                <div class="mnt-status-item">
                    <span class="mnt-status-title">Hedef</span>
                    <span class="mnt-status-value">%98 Hazır</span>
                </div>
            </div>

            <div class="mnt-footer">
                <p>&copy; 2026 artirdim.com. Tüm hakları saklıdır.</p>
            </div>
        </div>
    </div>
</template>

<style scoped src="../../../css/pages/errors-maintenance.css"></style>

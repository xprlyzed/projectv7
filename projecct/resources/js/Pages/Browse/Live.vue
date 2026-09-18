<script>
import AppLayout from '@/Layouts/AppLayout.vue';
export default { layout: AppLayout };
</script>

<script setup>
import { Head } from '@inertiajs/vue3';
import AuctionCard from '@/Components/AuctionCard.vue';
defineProps({ liveAuctions: Array, stats: Object, now: String });
</script>

<template>
    <Head title="Canlı Açık Artırma" />
    <div class="py-4">
        <div class="idx-section-head" data-testid="live-hero">
            <div class="idx-section-title"><i class="bi bi-broadcast"></i> Canlı Açık Artırma</div>
            <div class="idx-section-date">{{ now }} itibarıyla</div>
        </div>

        <div class="brz-live-statline">
            <span class="brz-live-pill is-live" data-testid="live-stat-streaming">
                <span class="brz-live-dot"></span> {{ stats.streaming }} Yayında
            </span>
            <span class="brz-live-pill">{{ stats.total }} Aktif Artırma</span>
            <span class="brz-live-pill">{{ stats.ending_soon }} Bitmek Üzere</span>
        </div>

        <div class="row g-3" data-testid="live-grid">
            <div v-for="auction in liveAuctions" :key="auction.id" class="col-xl-3 col-lg-4 col-md-6">
                <AuctionCard :auction="auction" />
            </div>
            <div v-if="!liveAuctions.length" class="brz-live-empty">
                <i class="bi bi-broadcast"></i>
                <p>Şu an canlı yayında olan bir açık artırma yok.</p>
            </div>
        </div>
    </div>
</template>

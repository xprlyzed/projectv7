<script>
import AppLayout from '@/Layouts/AppLayout.vue';
export default { layout: AppLayout };
</script>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    sellerName: String,
    stats: Object,
    walletBalance: String,
    walletPct: Number,
    chartData: Array,
    chartLabels: Array,
    liveAuctions: Array,
    broadcastableAuctions: Array,
    broadcastableCount: Number,
    topBidAuctions: Array,
    recentActivities: Array,
    latestAuctions: Array,
    links: Object,
});

const salesChart = ref(null);
let chartInstance = null;

function fmt(n) { return new Intl.NumberFormat('tr-TR').format(n || 0); }

const maxBid = () => (props.topBidAuctions.length ? props.topBidAuctions[0].bids : 0);
function bidPct(bids) {
    const m = maxBid();
    return m > 0 ? Math.round((bids / m) * 100) : 0;
}

function loadChartJs() {
    return new Promise((resolve) => {
        if (window.Chart) return resolve();
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/chart.js';
        s.onload = () => resolve();
        s.onerror = () => resolve();
        document.head.appendChild(s);
    });
}

async function renderChart() {
    await loadChartJs();
    const ctx = salesChart.value;
    if (!ctx || !window.Chart) return;
    if (chartInstance) { try { chartInstance.destroy(); } catch (e) {} }

    const data = Array.isArray(props.chartData) ? props.chartData : [];
    const labels = props.chartLabels?.length ? props.chartLabels : data.map((_, i) => i + 1 + '.');

    chartInstance = new window.Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Satış (₺)',
                data,
                backgroundColor: 'rgba(21,94,239,.2)',
                hoverBackgroundColor: 'rgba(21,94,239,.65)',
                borderRadius: 5,
                borderSkipped: false,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { padding: 10, callbacks: { label: (c) => ' ' + c.parsed.y.toLocaleString('tr-TR') + ' ₺' } },
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#94a3b8', maxRotation: 0, autoSkip: true, maxTicksLimit: 10 } },
                y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,.05)' }, ticks: { font: { size: 10 }, color: '#94a3b8', callback: (v) => (v >= 1000 ? (v / 1000).toFixed(1) + 'K' : v) } },
            },
        },
    });
}

onMounted(renderChart);
onBeforeUnmount(() => { if (chartInstance) { try { chartInstance.destroy(); } catch (e) {} } });
</script>

<template>
    <Head title="Satıcı Paneli" />
    <div class="pf-root container-fluid px-2 px-md-4 py-4">

        <div class="pf-toolbar mb-3">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <h1 class="pf-toolbar-title mb-1">Satıcı Paneli</h1>
                    <div class="pf-text-muted-sm">
                        Merhaba <strong class="il-aee76f91">{{ sellerName }}</strong>, performansını ve ilanlarını tek yerden yönet
                    </div>
                </div>
                <span class="pf-badge pf-badge-success d-inline-flex align-items-center gap-1 il-5748eac4">
                    <span class="pf-pulse-dot"></span> Canlı
                </span>
            </div>
        </div>

        <div v-if="liveAuctions.length" class="admin-card seller-live-card seller-live-card--on mb-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="seller-live-icon seller-live-icon--pulse"><i class="bi bi-broadcast-pin"></i></div>
                    <div>
                        <div class="fw-bold il-b3fc2c2a">Canlı Yayındasın · {{ liveAuctions.length }} ilan</div>
                        <div class="pf-text-muted-sm">Yayın paneline dön veya yeni yayın başlat</div>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a v-for="(la, i) in liveAuctions.slice(0, 3)" :key="i" :href="la.broadcast_url" class="pf-btn-save d-flex align-items-center gap-2 il-16e59a0f">
                        <span class="pf-pulse-dot il-0695ff9e"></span>{{ la.title }}
                    </a>
                </div>
            </div>
        </div>
        <div v-else-if="broadcastableAuctions.length" class="admin-card seller-live-card mb-3" id="canliya-basla">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="seller-live-icon"><i class="bi bi-broadcast"></i></div>
                    <div>
                        <div class="fw-bold il-b3fc2c2a">Canlı Yayına Başla</div>
                        <div class="pf-text-muted-sm">{{ broadcastableCount }} aktif ilanın kamera açmayı bekliyor</div>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <a v-if="broadcastableCount === 1" :href="broadcastableAuctions[0].broadcast_url" class="pf-btn-save d-flex align-items-center gap-2 il-3255787f" data-testid="seller-quick-broadcast-btn">
                        <i class="bi bi-camera-video"></i> "{{ broadcastableAuctions[0].title }}" için yayın aç
                    </a>
                    <div v-else class="seller-live-list d-flex gap-2 flex-wrap">
                        <a v-for="(ba, i) in broadcastableAuctions.slice(0, 4)" :key="i" :href="ba.broadcast_url" class="pf-btn-secondary d-flex align-items-center gap-2 il-821c14f3">
                            <i class="bi bi-camera-video il-035cc887"></i>{{ ba.title_short }}
                        </a>
                        <Link v-if="broadcastableCount> 4" :href="links.auctions_index" class="pf-btn-secondary d-flex align-items-center il-821c14f3">+{{ broadcastableCount - 4 }} daha</Link>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-6 col-xl-3">
                <div class="pf-stat-card il-ca0db282">
                    <div class="pf-stat-icon-wrapper il-173ac8af"><i class="bi bi-box-seam il-e040823b"></i></div>
                    <div>
                        <div class="pf-stat-number">{{ stats.auctions ?? 0 }}</div>
                        <div class="pf-stat-label">Toplam İlan</div>
                        <div class="pf-text-muted-sm il-8628e134">↑ {{ stats.auctions_this_month ?? 0 }} bu ay</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="pf-stat-card">
                    <div class="pf-stat-icon-wrapper il-a345efbb"><i class="bi bi-broadcast il-79433824"></i></div>
                    <div>
                        <div class="pf-stat-number">{{ stats.active ?? 0 }}</div>
                        <div class="pf-stat-label">Aktif İlan</div>
                        <div class="pf-text-muted-sm il-8628e134">↑ {{ stats.active_this_week ?? 0 }} bu hafta</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="pf-stat-card">
                    <div class="pf-stat-icon-wrapper il-7da3c027"><i class="bi bi-hand-index-thumb il-10773562"></i></div>
                    <div>
                        <div class="pf-stat-number">{{ stats.bids ?? 0 }}</div>
                        <div class="pf-stat-label">Toplam Teklif</div>
                        <div class="pf-text-muted-sm il-8628e134">↑ {{ stats.bids_today ?? 0 }} bugün</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="pf-stat-card">
                    <div class="pf-stat-icon-wrapper il-132ce282"><i class="bi bi-cash-coin il-3a3a6e6f"></i></div>
                    <div>
                        <div class="pf-stat-number">{{ stats.sales ?? 0 }}</div>
                        <div class="pf-stat-label">Satış</div>
                        <div class="pf-text-muted-sm il-9d19b9bf">{{ stats.sales_this_month ?? 0 }} bu ay</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-4">
                <div class="pf-stat-card flex-column text-center gap-1 il-ce792335">
                    <div class="pf-stat-number il-532445a3">{{ stats.completion_rate ?? 0 }}%</div>
                    <div class="pf-stat-label">Tamamlanma</div>
                    <div class="pf-text-muted-sm">{{ stats.sales ?? 0 }} satış</div>
                </div>
            </div>
            <div class="col-4">
                <div class="pf-stat-card flex-column text-center gap-1 il-ce792335">
                    <div class="pf-stat-number il-21f9b72d">{{ stats.seller_rating ?? '0.0' }} ★</div>
                    <div class="pf-stat-label">Satıcı Puanı</div>
                    <div class="pf-text-muted-sm">{{ stats.review_count ?? 0 }} değerlendirme</div>
                </div>
            </div>
            <div class="col-4">
                <div class="pf-stat-card flex-column text-center gap-1 il-ce792335">
                    <div class="pf-stat-number il-98cda618">₺{{ fmt(stats.avg_price) }}</div>
                    <div class="pf-stat-label">Ort. Fiyat</div>
                    <div class="pf-text-muted-sm">Aktif ilanlar</div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-lg-8">
                <div class="admin-card h-100">
                    <div class="admin-card-head">
                        <div class="admin-card-title"><i class="bi bi-graph-up-arrow il-035cc887"></i> Satış Performansı</div>
                        <span class="pf-text-muted-sm">Son 30 gün</span>
                    </div>
                    <div class="il-de84987f">
                        <div class="il-c2b06a2a">
                            <canvas ref="salesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="admin-card h-100 d-flex flex-column">
                    <div class="admin-card-head">
                        <div class="admin-card-title"><i class="bi bi-wallet2 il-035cc887"></i> Cüzdan</div>
                    </div>
                    <div class="il-39f11d58">
                        <div>
                            <div class="il-c9e92807">{{ walletBalance }}</div>
                            <div class="pf-text-muted-sm mb-3">Kullanılabilir bakiye</div>
                            <div class="il-a08115dd">
                                <div :style="{ height:'100%', borderRadius:'10px', background:'var(--primary)', width: walletPct + '%' }"></div>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <div class="s-info-item text-center">
                                        <div class="s-info-lbl il-f869f3a4">Bu ay kazanılan</div>
                                        <div class="s-info-val il-6c2410d5">₺{{ fmt(stats.earned_this_month) }}</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="s-info-item text-center">
                                        <div class="s-info-lbl il-f869f3a4">Bekleyen</div>
                                        <div class="s-info-val il-fdf0173c">₺{{ fmt(stats.pending_balance) }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-7">
                                <Link :href="links.withdraw" class="pf-btn-save w-100 d-flex align-items-center justify-content-center gap-1 il-416c260c"><i class="bi bi-arrow-down-circle"></i> Para Çek</Link>
                            </div>
                            <div class="col-5">
                                <Link :href="links.balance_index" class="pf-btn-secondary w-100 d-flex align-items-center justify-content-center gap-1 il-ef06f4c8"><i class="bi bi-clock-history"></i> Geçmiş</Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-lg-6">
                <div class="admin-card h-100">
                    <div class="admin-card-head">
                        <div class="admin-card-title"><i class="bi bi-trophy il-38288887"></i> En Çok Teklif Alan İlanlar</div>
                        <Link :href="links.auctions_index" class="pf-link-primary il-3de3af87">Tümü →</Link>
                    </div>
                    <div class="il-7318b8d4">
                        <template v-if="topBidAuctions.length">
                            <div v-for="(item, i) in topBidAuctions" :key="i" class="d-flex align-items-center gap-3 py-2 il-fedef8fa">
                                <span class="pf-text-muted-sm il-6f2cae09">{{ i + 1 }}</span>
                                <div class="il-89bd09bd">
                                    <div class="il-baebbf49">{{ item.title }}</div>
                                    <div class="il-bc8edb95">
                                        <div :style="{ height:'100%', borderRadius:'4px', background:'var(--primary)', width: bidPct(item.bids) + '%' }"></div>
                                    </div>
                                </div>
                                <span class="pf-badge pf-badge-success">{{ item.bids }}</span>
                            </div>
                        </template>
                        <div v-else class="pf-empty il-4f17f2a6">
                            <div class="pf-empty-icon"><i class="bi bi-trophy"></i></div>
                            <div class="pf-empty-title">Henüz teklif alan ilan yok</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="admin-card h-100">
                    <div class="admin-card-head">
                        <div class="admin-card-title"><i class="bi bi-activity il-035cc887"></i> Son Aktivite</div>
                    </div>
                    <div class="il-487d16c8">
                        <template v-if="recentActivities.length">
                            <div v-for="(act, i) in recentActivities" :key="i" class="d-flex align-items-start gap-3 py-2 il-fedef8fa">
                                <div :style="{ width:'8px', height:'8px', borderRadius:'50%', background: act.color, flexShrink:0, marginTop:'5px' }"></div>
                                <div class="il-da5cd676">
                                    <div class="il-81a61163" v-html="act.text"></div>
                                    <div class="pf-text-muted-sm il-f005b881">{{ act.time }}</div>
                                </div>
                            </div>
                        </template>
                        <div v-else class="pf-empty il-4f17f2a6">
                            <div class="pf-empty-icon"><i class="bi bi-activity"></i></div>
                            <div class="pf-empty-title">Henüz aktivite yok</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-8">
                <div class="admin-card h-100">
                    <div class="admin-card-head">
                        <div class="admin-card-title"><i class="bi bi-box-seam il-035cc887"></i> Son İlanlar</div>
                        <Link :href="links.auctions_index" class="pf-link-primary il-3de3af87">Tümünü Gör →</Link>
                    </div>

                    <div v-if="!latestAuctions.length" class="pf-empty">
                        <div class="pf-empty-icon"><i class="bi bi-box-seam"></i></div>
                        <div class="pf-empty-title">Henüz ilan yok</div>
                        <div class="pf-empty-sub">İlk ilanını oluşturmak için "Yeni İlan Oluştur" butonunu kullan.</div>
                    </div>
                    <template v-else>
                        <div class="d-none d-md-block">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>İlan</th>
                                        <th class="text-end">Fiyat</th>
                                        <th class="text-center">Durum</th>
                                        <th class="text-center">Teklif</th>
                                        <th class="text-center">Süre</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(a, i) in latestAuctions" :key="i">
                                        <td>
                                            <div class="pf-cat-info">
                                                <img :src="a.cover_url" class="pf-cat-img" :alt="a.title">
                                                <div>
                                                    <div class="pf-cat-name">{{ a.title }}</div>
                                                    <div class="pf-cat-slug">{{ a.created_ago }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end il-a07b2691">{{ a.display_price }}</td>
                                        <td class="text-center"><span class="pf-badge" :class="a.status_class">{{ a.status_label }}</span></td>
                                        <td class="text-center il-8e593262">{{ a.bid_count }}</td>
                                        <td class="text-center pf-text-muted-sm">{{ a.ends_ago }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex flex-column gap-2 d-md-none p-3">
                            <div v-for="(a, i) in latestAuctions" :key="i" class="d-flex align-items-center gap-3 p-2 il-e058ffae">
                                <img class="il-56aab183" :src="a.cover_url" :alt="a.title">
                                <div class="il-89bd09bd">
                                    <div class="pf-cat-name">{{ a.title_short }}</div>
                                    <div class="il-7725856e">{{ a.display_price }}</div>
                                </div>
                                <span class="pf-badge il-5cd105e1" :class="a.status_class">{{ a.status_label }}</span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="admin-card h-100">
                    <div class="admin-card-head">
                        <div class="admin-card-title"><i class="bi bi-lightning-charge il-38288887"></i> Hızlı İşlemler</div>
                    </div>
                    <div class="il-3a763330">
                        <Link :href="links.auctions_create" class="pf-btn-save w-100 d-flex align-items-center justify-content-center gap-2 il-176884ac"><i class="bi bi-plus-lg"></i> Yeni İlan Oluştur</Link>
                        <div class="s-action-grid il-f764e597">
                            <Link :href="links.auctions_index" class="s-action-btn text-decoration-none">
                                <i class="bi bi-list-ul il-e040823b"></i>
                                <div class="s-info-lbl mt-1 il-bcf88ef7">İlanlar</div>
                            </Link>
                            <Link :href="links.profile_edit" class="s-action-btn text-decoration-none">
                                <i class="bi bi-person il-e040823b"></i>
                                <div class="s-info-lbl mt-1 il-bcf88ef7">Profil</div>
                            </Link>
                            <Link :href="links.balance_index" class="s-action-btn text-decoration-none">
                                <i class="bi bi-graph-up il-79433824"></i>
                                <div class="s-info-lbl mt-1 il-bcf88ef7">Cüzdan</div>
                            </Link>
                            <Link href="/messages" class="s-action-btn text-decoration-none">
                                <i class="bi bi-chat-dots il-3a3a6e6f"></i>
                                <div class="s-info-lbl mt-1 il-bcf88ef7">Mesajlar</div>
                            </Link>
                            <Link :href="links.profile_edit" class="s-action-btn text-decoration-none">
                                <i class="bi bi-gear il-2ed67ed0"></i>
                                <div class="s-info-lbl mt-1 il-bcf88ef7">Ayarlar</div>
                            </Link>
                            <Link href="/support" class="s-action-btn text-decoration-none">
                                <i class="bi bi-question-circle il-2ed67ed0"></i>
                                <div class="s-info-lbl mt-1 il-bcf88ef7">Yardım</div>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped src="../../../css/pages/seller-dashboard.css"></style>

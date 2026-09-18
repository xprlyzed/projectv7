<script>
import AppLayout from '@/Layouts/AppLayout.vue';
export default { layout: AppLayout };
</script>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    admin_name: String, today: String, stats: Object, chart: Array,
    orderStatuses: Array, topSellers: Array, recentOrders: Array, activities: Array, links: Object,
});

const nf = (n) => new Intl.NumberFormat('tr-TR').format(n || 0);
const money = (n) => new Intl.NumberFormat('tr-TR').format(Math.round(n || 0)) + ' ₺';

const maxRev = computed(() => Math.max(1, ...props.chart.map((c) => c.revenue)));
const maxOrd = computed(() => Math.max(1, ...props.chart.map((c) => c.orders)));
const totalOrders = computed(() => props.orderStatuses.reduce((a, s) => a + s.count, 0));

function barHeight(c) {
    if (c.revenue > 0) return Math.max(6, Math.round((c.revenue / maxRev.value) * 150));
    if (c.orders > 0) return Math.max(6, Math.round((c.orders / maxOrd.value) * 60));
    return 3;
}
</script>

<template>
    <Head title="Admin Dashboard" />
    <div class="adm-wrap admin-fade">

        <div class="adm-hero">
            <div class="il-cc603287">
                <h1>Yönetim Paneli</h1>
                <p>Hoş geldin, {{ admin_name }} · {{ today }}</p>
            </div>
            <div class="adm-live"><span class="dot"></span> Sistem Aktif</div>
        </div>

        <div class="adm-grid4">
            <div class="adm-stat" data-testid="stat-users">
                <div class="adm-stat-ic il-c60db7ba"><i class="bi bi-people-fill"></i></div>
                <div>
                    <div class="adm-stat-num">{{ nf(stats.users) }}</div>
                    <div class="adm-stat-lbl">Toplam Kullanıcı</div>
                    <div class="adm-stat-sub il-29fb9df2">↑ {{ stats.new_users_week }} bu hafta</div>
                </div>
            </div>
            <div class="adm-stat" data-testid="stat-auctions">
                <div class="adm-stat-ic il-efa9eef9"><i class="bi bi-hammer"></i></div>
                <div>
                    <div class="adm-stat-num">{{ nf(stats.active) }}</div>
                    <div class="adm-stat-lbl">Aktif Müzayede</div>
                    <div class="adm-stat-sub il-87ddc0dc">Toplam {{ nf(stats.auctions) }}</div>
                </div>
            </div>
            <div class="adm-stat" data-testid="stat-orders">
                <div class="adm-stat-ic il-67fc830d"><i class="bi bi-box-seam"></i></div>
                <div>
                    <div class="adm-stat-num">{{ nf(stats.orders) }}</div>
                    <div class="adm-stat-lbl">Sipariş</div>
                    <div class="adm-stat-sub il-29fb9df2">{{ stats.completed }} tamamlandı</div>
                </div>
            </div>
            <div class="adm-stat" data-testid="stat-revenue">
                <div class="adm-stat-ic il-ef695451"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="adm-stat-num">{{ money(stats.revenue) }}</div>
                    <div class="adm-stat-lbl">Toplam Ciro</div>
                    <div class="adm-stat-sub il-38288887">Komisyon {{ money(stats.commission) }}</div>
                </div>
            </div>
        </div>

        <div class="adm-grid-mini">
            <div class="adm-mini"><div class="adm-mini-lbl"><i class="bi bi-graph-up-arrow il-d294e9a2"></i> Teklif</div><div class="adm-mini-num">{{ nf(stats.bids) }}</div></div>
            <div class="adm-mini"><div class="adm-mini-lbl"><i class="bi bi-shield-lock il-2a4cda41"></i> Emanetteki Tutar</div><div class="adm-mini-num">{{ money(stats.escrow_held) }}</div></div>
            <div class="adm-mini"><div class="adm-mini-lbl"><i class="bi bi-exclamation-octagon il-c9e34862"></i> Anlaşmazlık</div><div class="adm-mini-num">{{ stats.disputes }}</div></div>
            <div class="adm-mini"><div class="adm-mini-lbl"><i class="bi bi-person-check il-b171289b"></i> Onay Bekleyen</div><div class="adm-mini-num">{{ stats.pending }}</div></div>
        </div>

        <div class="adm-2col">
            <div class="adm-card" data-testid="admin-chart">
                <div class="adm-card-h">
                    <div class="adm-card-t"><i class="bi bi-bar-chart-line il-035cc887"></i> Son 14 Gün — Sipariş Hacmi</div>
                    <span class="il-667d43f0">Ciro (tamamlanan) çubuk yüksekliğine yansır</span>
                </div>
                <div class="adm-chart">
                    <div v-for="(c, i) in chart" :key="i" class="adm-bar-col"
                         :title="`${c.label} · ${c.orders} sipariş · ${money(c.revenue)}`">
                        <div class="adm-bar" :style="{ height: barHeight(c) + 'px' }"></div>
                        <div class="adm-bar-lbl">{{ c.label }}</div>
                    </div>
                </div>

                <div class="il-b89a7754">
                    <div class="adm-card-t il-91aeaa60"><i class="bi bi-pie-chart"></i> Sipariş Durum Dağılımı</div>
                    <template v-if="orderStatuses.length">
                        <div v-for="(st, i) in orderStatuses" :key="i" class="adm-break-row">
                            <span class="il-a2e3d071">{{ st.label }}</span>
                            <div class="adm-break-bar"><div class="adm-break-fill" :style="{ width: (totalOrders ? Math.round(st.count/totalOrders*100) : 0) + '%', background: st.color }"></div></div>
                            <span class="il-d0348f0a">{{ st.count }}</span>
                        </div>
                    </template>
                    <div class="il-0a0311fd" v-else>Henüz sipariş yok.</div>
                </div>
            </div>

            <div class="adm-card">
                <div class="adm-card-h"><div class="adm-card-t"><i class="bi bi-lightning-charge il-38288887"></i> Hızlı İşlemler</div></div>
                <Link :href="links.users" class="adm-qa"><i class="bi bi-people lead il-d294e9a2"></i><div class="il-da5cd676"><div class="adm-qa-t">Kullanıcılar</div><div class="adm-qa-s">Üye yönetimi &amp; roller</div></div><i class="bi bi-chevron-right il-87ddc0dc"></i></Link>
                <Link :href="links.auctions" class="adm-qa"><i class="bi bi-hammer lead il-29fb9df2"></i><div class="il-da5cd676"><div class="adm-qa-t">Müzayedeler</div><div class="adm-qa-s">İlan &amp; teklif yönetimi</div></div><i class="bi bi-chevron-right il-87ddc0dc"></i></Link>
                <Link :href="links.disputes" class="adm-qa" data-testid="qa-disputes"><i class="bi bi-exclamation-octagon lead il-c9e34862"></i><div class="il-da5cd676"><div class="adm-qa-t">Anlaşmazlıklar</div><div class="adm-qa-s">{{ stats.disputes }} bekleyen çözüm</div></div><i class="bi bi-chevron-right il-87ddc0dc"></i></Link>
                <Link :href="links.orders" class="adm-qa"><i class="bi bi-box-seam lead il-b171289b"></i><div class="il-da5cd676"><div class="adm-qa-t">Siparişler</div><div class="adm-qa-s">Tüm sipariş takibi</div></div><i class="bi bi-chevron-right il-87ddc0dc"></i></Link>
                <Link :href="links.categories" class="adm-qa"><i class="bi bi-diagram-3 lead il-13cb8ec1"></i><div class="il-da5cd676"><div class="adm-qa-t">Kategoriler</div><div class="adm-qa-s">İç içe kategori yönetimi</div></div><i class="bi bi-chevron-right il-87ddc0dc"></i></Link>
            </div>
        </div>

        <div class="adm-2col-b">
            <div class="adm-card" data-testid="admin-recent-orders">
                <div class="adm-card-h"><div class="adm-card-t"><i class="bi bi-clock-history il-035cc887"></i> Son Siparişler</div><Link class="il-377a7cbe" :href="links.orders">Tümü →</Link></div>
                <template v-if="recentOrders.length">
                    <div v-for="(o, i) in recentOrders" :key="i" class="adm-list-row">
                        <img class="adm-ava" :src="o.cover" alt="">
                        <div class="il-89bd09bd">
                            <div class="il-8ec3e450">{{ o.title }}</div>
                            <div class="il-457f4e66">{{ o.buyer }} · {{ money(o.amount) }}</div>
                        </div>
                        <span class="adm-badge" :style="{ background: o.status_color }">{{ o.status_label }}</span>
                    </div>
                </template>
                <div class="il-318a4a44" v-else>Henüz sipariş yok.</div>
            </div>

            <div>
                <div class="adm-card il-8e86974b" data-testid="admin-top-sellers">
                    <div class="adm-card-h"><div class="adm-card-t"><i class="bi bi-trophy il-38288887"></i> En İyi Satıcılar</div></div>
                    <template v-if="topSellers.length">
                        <div v-for="(s, i) in topSellers" :key="i" class="adm-list-row">
                            <span class="il-db60ec93">{{ i + 1 }}</span>
                            <img class="adm-ava il-8f633ef6" :src="s.avatar" alt="">
                            <div class="il-89bd09bd"><div class="il-492b7679">{{ s.name }}</div><div class="il-457f4e66">{{ s.sales }} satış</div></div>
                            <span class="il-ab27b8e9">{{ money(s.total) }}</span>
                        </div>
                    </template>
                    <div class="il-c86e43b2" v-else>Henüz tamamlanan satış yok.</div>
                </div>

                <div class="adm-card" data-testid="admin-activities">
                    <div class="adm-card-h"><div class="adm-card-t"><i class="bi bi-activity il-b171289b"></i> Son Aktiviteler</div></div>
                    <template v-if="activities.length">
                        <div v-for="(a, i) in activities" :key="i" class="adm-list-row">
                            <div class="adm-ava-c" :style="{ background: a.color }"><i class="bi" :class="a.icon"></i></div>
                            <div class="il-89bd09bd"><div class="il-34021f82">{{ a.title }}</div><div class="il-8a1efae9">{{ a.time_human }}</div></div>
                        </div>
                    </template>
                    <div class="il-c86e43b2" v-else>Aktivite bulunamadı.</div>
                </div>
            </div>
        </div>

    </div>
</template>

<style scoped src="../../../css/pages/admin-dashboard.css"></style>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
export default { layout: AppLayout };
</script>

<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({ auction: Object });
const a = props.auction;
const page = usePage();
const flash = computed(() => page.props.flash || {});

const mainImg = ref(a.cover);
const summary = [
    ['bi-currency-dollar', 'rgba(21,94,239,.12)', 'var(--primary)', a.display_price, 'Mevcut Fiyat'],
    ['bi-people', 'rgba(16,185,129,.12)', '#10b981', a.bid_count + ' teklif', 'Teklif'],
    ['bi-eye', 'rgba(251,191,36,.12)', '#fbbf24', a.view_count, 'Görüntülenme'],
    ['bi-clock', 'rgba(239,68,68,.1)', '#f87171', a.time_left, 'Kalan Süre'],
];

function approve() { router.post(a.approve_url, {}, { preserveScroll: true }); }
function reject() {
    if (typeof window.Swal !== 'undefined') {
        window.Swal.fire({ title: 'İlanı Reddet', input: 'textarea', inputPlaceholder: 'Gerekçe (isteğe bağlı)...', html: `<div class="swal-reject-note">"${a.title}" reddedilecek.</div>`, showCancelButton: true, confirmButtonText: 'Reddet', cancelButtonText: 'Vazgeç', reverseButtons: true, confirmButtonColor: '#ef4444' }).then((r) => { if (r.isConfirmed) router.post(a.reject_url, { reason: r.value || '' }, { preserveScroll: true }); });
    } else { const reason = prompt('Gerekçe:'); if (reason !== null) router.post(a.reject_url, { reason }, { preserveScroll: true }); }
}
function del() {
    const doDelete = () => {
        const token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        const fd = new FormData(); fd.append('_method', 'DELETE');
        fetch(a.destroy_url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, credentials: 'same-origin' })
            .then((res) => { if (!res.ok) return res.json().then((e) => { throw new Error(e.message || 'Silme başarısız'); }); return res.json().catch(() => ({})); })
            .then((data) => { if (window.ajaxToast) window.ajaxToast('success', (data && data.message) || 'Silindi'); router.visit(a.index_url); })
            .catch((e) => { if (window.ajaxToast) window.ajaxToast('error', e.message); else alert(e.message); });
    };
    if (typeof window.Swal !== 'undefined') {
        window.Swal.fire({ title: 'İlanı sil?', html: `<strong>${a.title}</strong> silinecek.`, icon: 'warning', showCancelButton: true, confirmButtonText: 'Evet, sil', cancelButtonText: 'Vazgeç', reverseButtons: true, confirmButtonColor: '#ef4444' }).then((r) => { if (r.isConfirmed) doDelete(); });
    } else if (confirm(a.title + ' silinecek?')) doDelete();
}
</script>

<template>
    <Head :title="a.title" />
    <div class="container-fluid py-3 px-4">
        <div class="admin-toolbar au-show-head mb-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="au-show-icon"><i class="bi bi-box-seam"></i></div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <div class="toolbar-title mb-0">{{ a.title_short }}</div>
                            <span class="a-badge" :class="a.status_type">{{ a.status_label }}</span>
                        </div>
                        <nav>
                            <ol class="breadcrumb mb-0 mt-1">
                                <li class="breadcrumb-item"><Link :href="a.index_url" class="pf-breadcrumb-link">İlanlar</Link></li>
                                <li class="breadcrumb-item active">Detay</li>
                            </ol>
                        </nav>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <template v-if="a.status === 'draft'">
                        <button type="button" @click="approve" class="btn-admin-pri il-0c39d920" data-testid="auction-approve"><i class="bi bi-check-lg"></i> Onayla</button>
                        <button type="button" @click="reject" class="btn-admin-danger" data-testid="auction-reject"><i class="bi bi-x-lg"></i> Reddet</button>
                    </template>
                    <Link :href="a.edit_url" class="btn-admin-pri" data-testid="auction-edit-btn"><i class="bi bi-pencil"></i> Düzenle</Link>
                    <button type="button" @click="del" class="btn-admin-danger" data-testid="auction-delete-btn"><i class="bi bi-trash"></i> Sil</button>
                </div>
            </div>
        </div>



        <div class="row g-3">
            <div class="col-lg-7">
                <div class="admin-card mb-3 p-2">
                    <img :src="mainImg" class="w-100 au-show-img">
                    <div v-if="a.images.length> 1" class="d-flex gap-2 p-3 il-df74e8d8">
                        <img v-for="(img, i) in a.images" :key="i" :src="img.url" @click="mainImg = img.url"
                             class="thumb-img" :class="{ 'thumb-active': mainImg === img.url }"
                             :style="{ width:'64px', height:'64px', flexShrink:0, objectFit:'cover', borderRadius:'8px', cursor:'pointer', border:'2px solid ' + (mainImg===img.url ? 'var(--primary)' : 'transparent'), opacity: mainImg===img.url ? 1 : .6, transition:'.15s' }">
                    </div>
                </div>
                <div class="admin-card">
                    <div class="admin-card-head"><div class="admin-card-title"><i class="bi bi-file-text"></i> Açıklama</div></div>
                    <div class="p-3"><p class="pf-desc-text mb-0 il-11305aad">{{ a.description }}</p></div>
                </div>
                <div v-if="a.bids.length" class="admin-card my-4">
                    <div class="admin-card-head"><div class="admin-card-title"><i class="bi bi-list-ol"></i> Son Teklifler</div><span class="a-badge info">{{ a.bid_count }} teklif</span></div>
                    <table class="admin-table">
                        <thead><tr><th>Kullanıcı</th><th>Tutar</th><th>Zaman</th></tr></thead>
                        <tbody>
                            <tr v-for="(b, i) in a.bids" :key="i">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img class="il-0442c83c" :src="b.avatar">
                                        <div><div class="il-48a238e0">{{ b.name }}</div><span v-if="b.is_auto" class="a-badge info il-bcf88ef7">Otomatik</span></div>
                                    </div>
                                </td>
                                <td class="pf-table-td-amount">{{ b.amount }}</td>
                                <td class="pf-text-muted-sm">{{ b.time_human }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="admin-card mb-3">
                    <div class="admin-card-head">
                        <div class="admin-card-title"><i class="bi bi-bar-chart"></i> Özet</div>
                        <span class="a-badge" :class="a.status_type">{{ a.status_label }}</span>
                    </div>
                    <div class="p-3">
                        <div class="row g-2">
                            <div v-for="(s, i) in summary" :key="i" class="col-6">
                                <div class="pf-stat-card">
                                    <div class="pf-stat-icon-wrapper" :style="{ background: s[1] }"><i class="bi" :class="s[0]" :style="{ color: s[2] }"></i></div>
                                    <div><div class="pf-stat-number il-74d4b081">{{ s[3] }}</div><div class="pf-stat-label">{{ s[4] }}</div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admin-card mb-3">
                    <div class="admin-card-head"><div class="admin-card-title"><i class="bi bi-person"></i> Satıcı</div></div>
                    <div class="p-3 d-flex align-items-center gap-3">
                        <div class="il-21012f0e">
                            <img class="il-621f54ce" v-if="a.seller.avatar" :src="a.seller.avatar">
                            <div v-else class="bg-primary text-white fw-bold d-flex align-items-center justify-content-center w-100 h-100">{{ a.seller.initial }}</div>
                        </div>
                        <div><div class="il-d8427f2c">{{ a.seller.name }}</div><div class="il-fd058b25">{{ a.seller.email }}</div></div>
                        <Link :href="a.seller.url" class="pf-btn-icon ms-auto" title="Kullanıcıya git"><i class="bi bi-arrow-right"></i></Link>
                    </div>
                </div>

                <div class="admin-card mb-3">
                    <div class="admin-card-head"><div class="admin-card-title"><i class="bi bi-info-circle"></i> Detaylar</div></div>
                    <div class="p-3">
                        <div v-for="(row, i) in a.details" :key="i" class="admin-info-row">
                            <div class="admin-info-icon"><i class="bi" :class="row[0]"></i></div>
                            <div class="flex-grow-1"><div class="admin-info-lbl">{{ row[1] }}</div><div class="admin-info-val">{{ row[2] }}</div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped src="../../../../css/pages/admin-auctions-show.css"></style>

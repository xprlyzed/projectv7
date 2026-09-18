<script>
import AppLayout from '@/Layouts/AppLayout.vue';
export default { layout: AppLayout };
</script>

<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({ requests: Object, stats: Object, filters: Object });
const page = usePage();
const flash = computed(() => page.props.flash || {});

const tabs = computed(() => [
    { key: 'pending',   label: `Onay Bekleyen (${props.stats.pending})` },
    { key: 'completed', label: `Onaylanan (${props.stats.completed})` },
    { key: 'rejected',  label: `Reddedilen (${props.stats.rejected})` },
]);

const cur = computed(() => props.filters.status || 'pending');
function tabHref(key) {
    return route('admin.topups.index', { status: key });
}

const busy = ref(null);

function approve(row) {
    const doApprove = () => {
        if (busy.value) return;
        busy.value = row.id;
        router.post(row.approve_url, {}, { preserveScroll: true, onFinish: () => (busy.value = null) });
    };
    if (typeof window.Swal !== 'undefined') {
        window.Swal.fire({
            title: 'Talebi onayla?',
            html: `<strong>${row.user_name}</strong> için <strong>${row.amount}</strong> bakiye eklenecek.`,
            icon: 'question', showCancelButton: true,
            confirmButtonText: 'Evet, onayla', cancelButtonText: 'Vazgeç',
            reverseButtons: true, confirmButtonColor: '#10b981', heightAuto: false,
        }).then((r) => { if (r.isConfirmed) doApprove(); });
    } else if (confirm('Talep onaylansın mı?')) {
        doApprove();
    }
}

function reject(row) {
    const doReject = (reason) => {
        if (busy.value) return;
        busy.value = row.id;
        router.post(row.reject_url, { reason }, { preserveScroll: true, onFinish: () => (busy.value = null) });
    };
    if (typeof window.Swal !== 'undefined') {
        window.Swal.fire({
            title: 'Talebi reddet',
            input: 'textarea',
            inputPlaceholder: 'Ret sebebi...',
            inputValidator: (v) => (!v || !v.trim() ? 'Ret sebebi zorunludur.' : undefined),
            icon: 'warning', showCancelButton: true,
            confirmButtonText: 'Reddet', cancelButtonText: 'Vazgeç',
            reverseButtons: true, confirmButtonColor: '#ef4444', heightAuto: false,
        }).then((r) => { if (r.isConfirmed) doReject(r.value.trim()); });
    } else {
        const reason = prompt('Ret sebebi:');
        if (reason && reason.trim()) doReject(reason.trim());
    }
}

function pageItems() { return props.requests.links.slice(1, -1); }
const prevLink = computed(() => props.requests.links[0]);
const nextLink = computed(() => props.requests.links[props.requests.links.length - 1]);
</script>

<template>
    <Head title="EFT/Havale Onayları" />
    <div class="dash-wrap py-4" data-testid="admin-topups-page">

        <div class="admin-toolbar dash-hero">
            <div>
                <div class="toolbar-title">EFT/Havale Bakiye Onayları</div>
                <div class="dash-hero-sub">Banka ekstrenizdeki tutar + referans kodunu eşleştirerek talebi onaylayın.</div>
            </div>
        </div>

        <div v-if="flash.success" class="alert alert-success" data-testid="topups-flash-success">{{ flash.success }}</div>
        <div v-if="flash.error" class="alert alert-danger" data-testid="topups-flash-error">{{ flash.error }}</div>

        <div class="admin-card orders-tabs" data-testid="admin-topups-tabs">
            <Link v-for="t in tabs" :key="t.key" :href="tabHref(t.key)"
                  :class="cur === t.key ? 'btn-admin-pri' : 'btn-admin-ghost'"
                  :data-testid="`admin-topups-tab-${t.key}`">{{ t.label }}</Link>
        </div>

        <div class="admin-card" data-testid="admin-topups-card">
            <template v-if="!requests.data.length">
                <div class="pf-empty" data-testid="topups-empty">
                    <div class="pf-empty-icon"><i class="bi bi-inbox"></i></div>
                    <div class="pf-empty-title">Kayıt yok</div>
                    <div class="pf-empty-sub">Bu durumda bekleyen EFT/havale talebi bulunmuyor.</div>
                </div>
            </template>
            <template v-else>
                <div class="pf-table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Kullanıcı</th>
                                <th>Tutar</th>
                                <th>Referans Kodu</th>
                                <th>Tarih</th>
                                <th>Durum</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in requests.data" :key="row.id" :data-testid="`topup-row-${row.id}`">
                                <td>
                                    <div class="fw-bold">{{ row.user_name }}</div>
                                    <div class="dash-muted il-6b9c179a">{{ row.user_email }}</div>
                                </td>
                                <td class="dash-amount">{{ row.amount }}</td>
                                <td class="dash-muted il-6b9c179a">{{ row.reference_code }}</td>
                                <td class="dash-muted">{{ row.created_at }}</td>
                                <td>
                                    <span class="ord-status-pill" :style="{ background: row.status_color }">
                                        <i class="bi" :class="row.status_icon"></i> {{ row.status_label }}
                                    </span>
                                    <div v-if="row.rejection_reason" class="dash-muted mt-1">{{ row.rejection_reason }}</div>
                                </td>
                                <td>
                                    <div v-if="row.status === 'pending'" class="d-flex gap-1 justify-content-end">
                                        <button type="button" class="btn-admin-success" :disabled="busy === row.id"
                                                @click="approve(row)" :data-testid="`topup-approve-${row.id}`">
                                            <i class="bi bi-check-lg"></i> Onayla
                                        </button>
                                        <button type="button" class="btn-admin-danger" :disabled="busy === row.id"
                                                @click="reject(row)" :data-testid="`topup-reject-${row.id}`">
                                            <i class="bi bi-x-lg"></i> Reddet
                                        </button>
                                    </div>
                                    <span v-else class="dash-muted">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="requests.has_pages" class="pf-pagination-wrapper">
                    <span class="pf-pagination-info">
                        <strong>{{ requests.from }}–{{ requests.to }}</strong> / {{ requests.total }} talep
                    </span>
                    <div class="d-flex gap-1">
                        <Link v-if="prevLink.url" :href="prevLink.url" class="pf-btn-icon pf-pagination-nav-btn"><i class="bi bi-chevron-left"></i></Link>
                        <Link v-for="(l, i) in pageItems()" :key="i" :href="l.url || '#'" class="pf-pagination-item" :class="{ active: l.active }" v-html="l.label" />
                        <Link v-if="nextLink.url" :href="nextLink.url" class="pf-btn-icon pf-pagination-nav-btn"><i class="bi bi-chevron-right"></i></Link>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<style scoped src="../../../../css/pages/admin-orders-index.css"></style>

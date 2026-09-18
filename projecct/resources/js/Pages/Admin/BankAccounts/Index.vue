<script>
import AppLayout from '@/Layouts/AppLayout.vue';
export default { layout: AppLayout };
</script>

<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ accounts: Array, stats: Object, create_url: String });
const page = usePage();
const flash = computed(() => page.props.flash || {});

const statCards = computed(() => [
    { lbl: 'Toplam', num: props.stats.total,   icon: 'bi-bank',            color: 'var(--primary)', bg: 'rgba(21,94,239,.1)',   col: 4 },
    { lbl: 'Aktif',  num: props.stats.active,  icon: 'bi-check-circle',    color: '#10b981',        bg: 'rgba(16,185,129,.1)',  col: 4 },
    { lbl: 'Pasif',  num: props.stats.passive, icon: 'bi-pause-circle',    color: '#fbbf24',        bg: 'rgba(251,191,36,.1)',  col: 4 },
]);

function deleteAccount(acc) {
    const doDelete = () => {
        router.delete(acc.destroy_url, { preserveScroll: true });
    };
    if (typeof window.Swal !== 'undefined') {
        window.Swal.fire({
            title: 'Hesabı sil?',
            html: `<strong>${acc.bank_name}</strong> banka hesabı silinecek.`,
            icon: 'warning', showCancelButton: true,
            confirmButtonText: 'Evet, sil', cancelButtonText: 'Vazgeç',
            reverseButtons: true, confirmButtonColor: '#ef4444', heightAuto: false,
        }).then((r) => { if (r.isConfirmed) doDelete(); });
    } else if (confirm(acc.bank_name + ' silinecek. Emin misiniz?')) {
        doDelete();
    }
}
</script>

<template>
    <Head title="Banka Hesapları" />
    <div class="pf-root container-fluid px-4 py-4" data-testid="admin-bank-accounts-page">

        <div class="pf-toolbar mb-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h1 class="pf-toolbar-title mb-1">Banka Hesapları</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 pf-breadcrumb-list">
                            <li class="breadcrumb-item"><Link :href="route('admin.dashboard')" class="pf-breadcrumb-link">Admin</Link></li>
                            <li class="breadcrumb-item active pf-breadcrumb-active">Banka Hesapları</li>
                        </ol>
                    </nav>
                </div>
                <Link :href="create_url" class="pf-btn-save pf-btn-with-icon" data-testid="bank-account-create-btn">
                    <i class="bi bi-plus-lg"></i> Yeni Hesap
                </Link>
            </div>

            <div class="row g-3 mt-2">
                <div v-for="card in statCards" :key="card.lbl" :class="`col-6 col-md-4 col-xl-${card.col}`">
                    <div class="pf-stat-card">
                        <div class="pf-stat-icon-wrapper" :style="{ background: card.bg }">
                            <i class="bi" :class="card.icon" :style="{ color: card.color }"></i>
                        </div>
                        <div>
                            <div class="pf-stat-number">{{ new Intl.NumberFormat('tr-TR').format(card.num) }}</div>
                            <div class="pf-stat-label">{{ card.lbl }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="flash.success" class="alert alert-success" data-testid="bank-flash-success">{{ flash.success }}</div>
        <div v-if="flash.error" class="alert alert-danger" data-testid="bank-flash-error">{{ flash.error }}</div>

        <div class="pf-main-card">
            <div class="table-responsive">
                <table class="pf-table">
                    <thead>
                        <tr>
                            <th class="text-start text-nowrap">Banka</th>
                            <th class="text-start text-nowrap">Hesap Sahibi</th>
                            <th class="text-start text-nowrap">IBAN</th>
                            <th class="text-start text-nowrap">Sıra</th>
                            <th class="text-start text-nowrap">Durum</th>
                            <th class="text-end text-nowrap">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-if="accounts.length">
                            <tr v-for="acc in accounts" :key="acc.id" class="pf-table-row" :data-testid="`bank-row-${acc.id}`">
                                <td>
                                    <div class="pf-cat-name">{{ acc.bank_name }}</div>
                                    <div v-if="acc.note" class="pf-cat-slug">{{ acc.note }}</div>
                                </td>
                                <td class="pf-table-count">{{ acc.account_holder }}</td>
                                <td><span class="pf-badge pf-badge-cyan">{{ acc.iban }}</span></td>
                                <td class="pf-table-order">{{ acc.sort_order }}</td>
                                <td>
                                    <span v-if="acc.is_active" class="pf-badge pf-badge-success"><i class="bi bi-check-circle-fill"></i> Aktif</span>
                                    <span v-else class="pf-badge pf-badge-warning"><i class="bi bi-pause-circle-fill"></i> Pasif</span>
                                </td>
                                <td>
                                    <div class="pf-actions-wrapper">
                                        <Link :href="acc.edit_url" class="pf-btn-save pf-action-btn pf-action-edit" title="Düzenle" :data-testid="`bank-edit-${acc.id}`"><i class="bi bi-pencil"></i></Link>
                                        <button type="button" class="delete-btn pf-action-btn-delete" title="Sil"
                                                @click="deleteAccount(acc)" :data-testid="`bank-delete-${acc.id}`">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-else>
                            <td colspan="6">
                                <div class="pf-empty pf-empty-container text-center" data-testid="bank-empty">
                                    <div class="pf-empty-icon"><i class="bi bi-bank"></i></div>
                                    <div class="pf-empty-title">Banka hesabı bulunamadı</div>
                                    <div class="pf-empty-sub">Havale ile yükleme için en az bir aktif hesap ekleyin.</div>
                                    <Link :href="create_url" class="pf-btn-save mt-3 pf-btn-with-icon d-inline-flex"><i class="bi bi-plus-lg"></i> Yeni Hesap</Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

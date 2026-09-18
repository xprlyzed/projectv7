<script>
import AppLayout from '@/Layouts/AppLayout.vue';
export default { layout: AppLayout };
</script>

<script setup>
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import Pagination from '@/Components/Pagination.vue';

defineProps({
    is_seller: Boolean,
    formatted_balance: String,
    transactions: Object,
});

const page = usePage();
const flash = computed(() => page.props.flash || {});

function goTo(url) {
    router.visit(url);
}
</script>

<template>
    <Head title="Bakiyem" />
    <div class="au-page-wrap">

        <div class="au-page-head mb-4 d-flex justify-content-between align-items-center gap-3 flex-wrap">
            <div class="au-head-left d-flex align-items-center gap-2">
                <Link :href="route('dashboard')" class="au-back-link">
                    <i class="bi bi-arrow-left"></i>
                </Link>
                <h1 class="au-page-title m-0">Bakiyem</h1>
            </div>
            <div class="au-head-right">
                <Link v-if="is_seller" :href="route('general.balance.withdraw.create')" class="btn-admin-pri w-100 justify-content-center" data-testid="withdraw-link">
                    <i class="bi bi-cash-coin me-1"></i> Para Çek
                </Link>
                <Link v-else :href="route('general.balance.create')" class="btn-admin-pri w-100 justify-content-center" data-testid="topup-link">
                    <i class="bi bi-plus-circle me-1"></i> Bakiye Yükle
                </Link>
            </div>
        </div>

        <div class="card mb-4 il-52231de4">
            <div class="card-body p-4">
                <div class="row align-items-center text-center text-sm-start">
                    <div class="col-sm-6 mb-3 mb-sm-0">
                        <div class="section-label m-0">Hesap Özeti</div>
                        <div class="text-muted small fw-semibold">Mevcut Kullanılabilir Bakiye</div>
                    </div>
                    <div class="col-sm-6 text-sm-end">
                        <div class="display-6 fw-bold il-cbbb73d4">
                            {{ formatted_balance }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-card">
            <div class="admin-card-head d-flex justify-content-between align-items-center">
                <div class="admin-card-title m-0">
                    <i class="bi bi-wallet2 il-cbbb73d4"></i> İşlem Geçmişi
                </div>
            </div>

            <div class="table-responsive d-none d-md-block p-0">
                <table class="admin-table mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Yön</th>
                            <th>Açıklama</th>
                            <th>Tarih / Saat</th>
                            <th>Durum</th>
                            <th class="pe-4 text-end">Tutar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-if="transactions.data.length">
                            <tr class="il-26590802" v-for="tx in transactions.data" :key="tx.id" @click="goTo(tx.url)">
                                <td class="ps-4">
                                    <span class="a-badge" :class="tx.is_credit ? 'success' : 'danger'">
                                        <i class="bi" :class="tx.is_credit ? 'bi-arrow-down-left' : 'bi-arrow-up-right'"></i>
                                    </span>
                                </td>
                                <td class="fw-semibold il-508369ae">
                                    {{ tx.description }}
                                    <div v-if="tx.reference_code" class="text-muted small mt-1" data-testid="tx-reference-code">
                                        <i class="bi bi-hash"></i> Ref: <code>{{ tx.reference_code }}</code>
                                    </div>
                                    <div v-if="tx.rejection_reason" class="text-danger small mt-1" data-testid="tx-rejection-reason">
                                        <i class="bi bi-x-circle"></i> {{ tx.rejection_reason }}
                                    </div>
                                </td>
                                <td class="text-muted small">{{ tx.date }}</td>
                                <td>
                                    <span class="a-badge" :class="{
                                        'success': tx.status === 'completed',
                                        'warning': tx.status === 'pending',
                                        'danger': tx.status === 'rejected' || tx.status === 'failed',
                                    }" :data-testid="`tx-status-${tx.id}`">
                                        {{ tx.status_label }}
                                    </span>
                                </td>
                                <td class="pe-4 text-end fw-bold">
                                    <span :class="tx.is_credit ? 'text-success' : 'text-danger'">
                                        {{ tx.formatted_amount }}
                                    </span>
                                </td>
                            </tr>
                        </template>
                        <tr v-else>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-inboxes display-6 d-block mb-3 opacity-25"></i>
                                Henüz kayıtlı bir hesap hareketi bulunmuyor.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-md-none">
                <div class="list-group list-group-flush bg-transparent">
                    <template v-if="transactions.data.length">
                        <div v-for="tx in transactions.data" :key="tx.id" class="list-group-item p-3 il-21e3461b" @click="goTo(tx.url)">

                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="a-badge p-2" :class="tx.is_credit ? 'success' : 'danger'">
                                        <i class="bi" :class="tx.is_credit ? 'bi-arrow-down-left' : 'bi-arrow-up-right'"></i>
                                    </span>
                                    <span class="fw-semibold text-truncate il-2c23fa34">
                                        {{ tx.description }}
                                    </span>
                                </div>
                                <div class="fw-bold fs-6">
                                    <span :class="tx.is_credit ? 'text-success' : 'text-danger'">
                                        {{ tx.formatted_amount }}
                                    </span>
                                </div>
                            </div>

                            <div class="d-flex align-items-center justify-content-between small text-muted">
                                <div>
                                    <i class="bi bi-clock me-1"></i>{{ tx.date }}
                                </div>
                                <div>
                                    <span class="a-badge px-2 py-1" :class="tx.status === 'completed' ? 'success' : 'warning'">
                                        {{ tx.status_label }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div v-else class="text-center py-5 text-muted">
                        <i class="bi bi-inboxes display-6 d-block mb-3 opacity-25"></i>
                        Henüz kayıtlı bir hesap hareketi bulunmuyor.
                    </div>
                </div>
            </div>

        </div>

        <div class="mt-4 d-flex justify-content-center justify-content-md-end">
            <Pagination v-if="transactions.has_pages" :links="transactions.links" />
        </div>

    </div>
</template>

<style scoped src="../../../../css/pages/general-balance-index.css"></style>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
export default { layout: AppLayout };
</script>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ account: Object, update_url: String, index_url: String });

const form = useForm({
    bank_name: props.account.bank_name,
    account_holder: props.account.account_holder,
    iban: props.account.iban,
    note: props.account.note || '',
    sort_order: props.account.sort_order,
    is_active: props.account.is_active,
});

const errList = computed(() => Object.values(form.errors));

function submit() {
    form.put(props.update_url);
}
</script>

<template>
    <Head title="Banka Hesabı Düzenle" />
    <div class="pf-root">
        <div class="pf-top pf-top-padding">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="pf-title-text">Banka Hesabı Düzenle</div>
                    <nav aria-label="breadcrumb" class="mt-1">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><Link :href="route('admin.dashboard')" class="pf-link-primary">Admin</Link></li>
                            <li class="breadcrumb-item"><Link :href="index_url" class="pf-link-primary">Banka Hesapları</Link></li>
                            <li class="breadcrumb-item active pf-text-muted">{{ account.bank_name }}</li>
                        </ol>
                    </nav>
                </div>
                <Link :href="index_url" class="pf-btn-reset pf-btn-back-custom"><i class="bi bi-arrow-left"></i> Geri</Link>
            </div>
        </div>

        <div class="pf-edit-drawer open">
            <div class="pf-epanel active">
            <form @submit.prevent="submit" data-testid="bank-account-edit-form">
                <div v-if="errList.length" class="pf-alert-success pf-alert-error-custom">
                    <i class="bi bi-exclamation-circle-fill pf-text-danger"></i>
                    <span class="pf-text-danger">{{ errList.join(' · ') }}</span>
                </div>

                <div class="pf-field">
                    <label class="pf-label">Banka Adı <span class="pf-req">*</span></label>
                    <input class="pf-input" type="text" v-model="form.bank_name" required data-testid="bank-name">
                    <div v-if="form.errors.bank_name" class="pf-error">{{ form.errors.bank_name }}</div>
                </div>

                <div class="pf-field">
                    <label class="pf-label">Hesap Sahibi <span class="pf-req">*</span></label>
                    <input class="pf-input" type="text" v-model="form.account_holder" required data-testid="bank-holder">
                    <div v-if="form.errors.account_holder" class="pf-error">{{ form.errors.account_holder }}</div>
                </div>

                <div class="pf-field">
                    <label class="pf-label">IBAN <span class="pf-req">*</span></label>
                    <input class="pf-input" type="text" v-model="form.iban" maxlength="40" required data-testid="bank-iban">
                    <div class="pf-hint">TR ile başlayan 26 karakter. Boşluklar otomatik temizlenir.</div>
                    <div v-if="form.errors.iban" class="pf-error">{{ form.errors.iban }}</div>
                </div>

                <div class="pf-field">
                    <label class="pf-label">Açıklama Notu</label>
                    <input class="pf-input" type="text" v-model="form.note" maxlength="255" data-testid="bank-note">
                    <div v-if="form.errors.note" class="pf-error">{{ form.errors.note }}</div>
                </div>

                <div class="pf-field">
                    <label class="pf-label">Sıralama</label>
                    <input class="pf-input" type="number" v-model="form.sort_order" min="0" max="9999" data-testid="bank-sort">
                    <div v-if="form.errors.sort_order" class="pf-error">{{ form.errors.sort_order }}</div>
                </div>

                <div class="pf-field">
                    <label class="pf-toggle">
                        <input type="checkbox" v-model="form.is_active" class="pf-toggle-input" data-testid="bank-active">
                        <span class="pf-toggle-slider"></span>
                        <span class="pf-label mb-0">Aktif (kullanıcılara gösterilsin)</span>
                    </label>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="pf-btn-save pf-btn-with-icon" :disabled="form.processing" data-testid="bank-submit">
                        <i class="bi bi-check-lg"></i> Güncelle
                    </button>
                    <Link :href="index_url" class="pf-btn-reset">Vazgeç</Link>
                </div>
            </form>
            </div>
        </div>
    </div>
</template>

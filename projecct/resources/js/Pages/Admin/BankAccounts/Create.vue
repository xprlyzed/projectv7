<script>
import AppLayout from '@/Layouts/AppLayout.vue';
export default { layout: AppLayout };
</script>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ store_url: String, index_url: String });

const form = useForm({
    bank_name: '',
    account_holder: '',
    iban: '',
    note: '',
    sort_order: 0,
    is_active: true,
});

const errList = computed(() => Object.values(form.errors));

function submit() {
    form.post(props.store_url);
}
</script>

<template>
    <Head title="Yeni Banka Hesabı" />
    <div class="pf-root">
        <div class="pf-top pf-top-padding">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="pf-title-text">Yeni Banka Hesabı</div>
                    <nav aria-label="breadcrumb" class="mt-1">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><Link :href="route('admin.dashboard')" class="pf-link-primary">Admin</Link></li>
                            <li class="breadcrumb-item"><Link :href="index_url" class="pf-link-primary">Banka Hesapları</Link></li>
                            <li class="breadcrumb-item active pf-text-muted">Yeni</li>
                        </ol>
                    </nav>
                </div>
                <Link :href="index_url" class="pf-btn-reset pf-btn-back-custom"><i class="bi bi-arrow-left"></i> Geri</Link>
            </div>
        </div>

        <div class="pf-edit-drawer open">
            <div class="pf-epanel active">
            <form @submit.prevent="submit" data-testid="bank-account-form">
                <div v-if="errList.length" class="pf-alert-success pf-alert-error-custom">
                    <i class="bi bi-exclamation-circle-fill pf-text-danger"></i>
                    <span class="pf-text-danger">{{ errList.join(' · ') }}</span>
                </div>

                <div class="pf-field">
                    <label class="pf-label">Banka Adı <span class="pf-req">*</span></label>
                    <input class="pf-input" type="text" v-model="form.bank_name" placeholder="Örn: Ziraat Bankası" required data-testid="bank-name">
                    <div v-if="form.errors.bank_name" class="pf-error">{{ form.errors.bank_name }}</div>
                </div>

                <div class="pf-field">
                    <label class="pf-label">Hesap Sahibi <span class="pf-req">*</span></label>
                    <input class="pf-input" type="text" v-model="form.account_holder" placeholder="Örn: Artırdım A.Ş." required data-testid="bank-holder">
                    <div v-if="form.errors.account_holder" class="pf-error">{{ form.errors.account_holder }}</div>
                </div>

                <div class="pf-field">
                    <label class="pf-label">IBAN <span class="pf-req">*</span></label>
                    <input class="pf-input" type="text" v-model="form.iban" placeholder="TR00 0000 0000 0000 0000 0000 00" maxlength="40" required data-testid="bank-iban">
                    <div class="pf-hint">TR ile başlayan 26 karakter. Boşluklar otomatik temizlenir.</div>
                    <div v-if="form.errors.iban" class="pf-error">{{ form.errors.iban }}</div>
                </div>

                <div class="pf-field">
                    <label class="pf-label">Açıklama Notu</label>
                    <input class="pf-input" type="text" v-model="form.note" maxlength="255" placeholder="Opsiyonel — kullanıcıya gösterilir" data-testid="bank-note">
                    <div v-if="form.errors.note" class="pf-error">{{ form.errors.note }}</div>
                </div>

                <div class="pf-field">
                    <label class="pf-label">Sıralama</label>
                    <input class="pf-input" type="number" v-model="form.sort_order" min="0" max="9999" placeholder="0" data-testid="bank-sort">
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
                        <i class="bi bi-check-lg"></i> Kaydet
                    </button>
                    <Link :href="index_url" class="pf-btn-reset">Vazgeç</Link>
                </div>
            </form>
            </div>
        </div>
    </div>
</template>

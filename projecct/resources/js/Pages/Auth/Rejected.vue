<script>
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { h } from 'vue';
export default {
    layout: (hh, page) => h(AuthLayout, { activeAuctions: page.props.activeAuctions || 0 }, () => page),
};
</script>

<script setup>
import { Head, useForm } from '@inertiajs/vue3';
defineProps({ reason: String, activeAuctions: Number });
const logoutForm = useForm({});
function logout() { logoutForm.post(route('logout')); }
</script>

<template>
    <Head title="Başvuru Reddedildi" />
    <div class="auth-form w-100 text-center">
        <div class="mb-10">
            <div class="auth-header text-center mb-8">
                <img src="/assets/media/logos/logo-light.svg" class="logo-light auth-logo" alt="Artirdim">
                <img src="/assets/media/logos/logo-dark.svg" class="logo-dark auth-logo" alt="Artirdim">
            </div>
            <h1 class="fw-bold fs-2 mb-3">Başvurun reddedildi</h1>
            <p class="text-muted fs-6">Satıcı başvurun admin ekibimiz tarafından incelendi ve maalesef onaylanmadı.</p>
        </div>

        <div class="alert alert-danger text-start mb-6" data-testid="rejected-reason">
            <div class="fw-bold mb-2">Ret Sebebi</div>
            <p class="mb-0">{{ reason || 'Belirtilmedi. Detay için destek ekibimizle iletişime geçebilirsin.' }}</p>
        </div>

        <div class="alert alert-info text-start mb-6">
            <div class="fw-bold mb-2">Ne yapabilirsin?</div>
            <ul class="mb-0">
                <li>Ret sebebini gidererek tekrar başvurabilirsin</li>
                <li>Destek ekibimizden ek bilgi isteyebilirsin</li>
            </ul>
        </div>

        <div class="d-grid mb-4">
            <a href="mailto:support@site.com" class="btn btn-auth-primary btn-lg" data-testid="rejected-support">Destek ile iletişime geç</a>
        </div>
        <form @submit.prevent="logout">
            <div class="d-grid">
                <button class="btn btn-auth-outline btn-lg" data-testid="rejected-logout">Çıkış Yap</button>
            </div>
        </form>
    </div>
</template>

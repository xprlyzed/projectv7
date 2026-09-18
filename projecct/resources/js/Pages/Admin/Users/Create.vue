<script>
import AppLayout from '@/Layouts/AppLayout.vue';
export default { layout: AppLayout };
</script>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({ roles: Array, store_url: String, index_url: String });
const tab = ref('genel');

const form = useForm({
    name: '',
    email: '',
    phone: '',
    username: '',
    role: props.roles[0]?.name || 'buyer',
    avatar: null,
    password: '',
    password_confirmation: '',
    is_verified: false,
});

const preview = ref('https://ui-avatars.com/api/?name=U&background=155eef&color=fff&size=256');
const avatarEl = ref(null);
function onAvatar(e) { const f = e.target.files[0]; if (!f) return; form.avatar = f; preview.value = URL.createObjectURL(f); }
const showPw1 = ref(false); const showPw2 = ref(false);
const pwStrength = computed(() => {
    const p = form.password || ''; let s = 0;
    if (p.length >= 8) s++; if (/[A-Z]/.test(p) && /[a-z]/.test(p)) s++; if (/[0-9]/.test(p)) s++; if (/[^A-Za-z0-9]/.test(p)) s++;
    return s;
});
const errList = computed(() => Object.values(form.errors));
function submit() { form.post(props.store_url, { forceFormData: true }); }
</script>

<template>
    <Head title="Yeni Kullanıcı" />
    <div class="pf-root">
        <div class="pf-top pf-top-padding">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <div class="pf-title-text">Yeni Kullanıcı</div>
                    <nav aria-label="breadcrumb" class="mt-1">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><Link :href="route('admin.dashboard')" class="pf-link-primary">Admin</Link></li>
                            <li class="breadcrumb-item"><Link :href="index_url" class="pf-link-primary">Kullanıcılar</Link></li>
                            <li class="breadcrumb-item active pf-text-muted">Yeni</li>
                        </ol>
                    </nav>
                </div>
                <Link :href="index_url" class="pf-btn-reset pf-btn-back-custom"><i class="bi bi-arrow-left"></i> Geri</Link>
            </div>
        </div>

        <div class="pf-edit-drawer open">
            <div class="pf-edit-tabs">
                <button class="pf-etab" :class="{ active: tab==='genel' }" @click="tab='genel'" type="button"><i class="bi bi-person me-1"></i> Genel</button>
                <button class="pf-etab" :class="{ active: tab==='rol' }" @click="tab='rol'" type="button"><i class="bi bi-person-badge me-1"></i> Rol &amp; Şifre</button>
            </div>

            <form @submit.prevent="submit" enctype="multipart/form-data">
                <div v-if="errList.length" class="pf-alert-success il-b721e611">
                    <i class="bi bi-exclamation-circle-fill il-124741b6"></i>
                    <span class="il-124741b6">{{ errList.join(' · ') }}</span>
                </div>

                <div class="pf-epanel" :class="{ active: tab==='genel' }">
                    <div class="pf-avatar-upload-row">
                        <label class="pf-upload-avatar il-b202c6d4" title="Fotoğraf seç">
                            <img class="il-a56723b6" :src="preview" alt="Önizleme">
                            <input ref="avatarEl" type="file" accept=".png,.jpg,.jpeg,.webp" class="d-none" @change="onAvatar" data-testid="user-avatar">
                        </label>
                        <div>
                            <div class="pf-upload-title">Profil fotoğrafı</div>
                            <div class="pf-upload-desc">PNG, JPG, WEBP · Maks. 2MB</div>
                            <label class="pf-btn-photo mt-2 d-inline-flex align-items-center gap-1 il-b202c6d4" @click="avatarEl?.click()"><i class="bi bi-upload"></i> Fotoğraf yükle</label>
                        </div>
                    </div>
                    <div class="pf-two-col">
                        <div class="pf-field">
                            <label class="pf-label">Ad Soyad <span class="pf-req">*</span></label>
                            <input class="pf-input" type="text" v-model="form.name" placeholder="Ad Soyad" required data-testid="user-name">
                            <div v-if="form.errors.name" class="pf-error">{{ form.errors.name }}</div>
                        </div>
                        <div class="pf-field">
                            <label class="pf-label">E-posta <span class="pf-req">*</span></label>
                            <input class="pf-input" type="email" v-model="form.email" placeholder="eposta@domain.com" required data-testid="user-email">
                            <div v-if="form.errors.email" class="pf-error">{{ form.errors.email }}</div>
                        </div>
                    </div>
                    <div class="pf-two-col">
                        <div class="pf-field">
                            <label class="pf-label">Telefon</label>
                            <div class="pf-input-pre"><span class="pf-pre-label">+90</span><input type="tel" v-model="form.phone" maxlength="15" placeholder="5xx xxx xx xx"></div>
                            <div v-if="form.errors.phone" class="pf-error">{{ form.errors.phone }}</div>
                        </div>
                        <div class="pf-field">
                            <label class="pf-label">Kullanıcı Adı</label>
                            <div class="pf-input-pre"><span class="pf-pre-label">@</span><input type="text" v-model="form.username" maxlength="30" placeholder="boş = otomatik" data-testid="user-username"></div>
                            <div v-if="form.errors.username" class="pf-error">{{ form.errors.username }}</div>
                        </div>
                    </div>
                    <div class="pf-footer">
                        <span class="pf-save-info"><i class="bi bi-person-plus"></i> Yeni hesap oluştur</span>
                        <div class="d-flex gap-2">
                            <Link :href="index_url" class="pf-btn-reset">İptal</Link>
                            <button type="submit" class="pf-btn-save" :disabled="form.processing" data-testid="user-save"><i class="bi bi-floppy me-1"></i> Kaydet</button>
                        </div>
                    </div>
                </div>

                <div class="pf-epanel" :class="{ active: tab==='rol' }">
                    <div class="pf-field">
                        <label class="pf-label">Rol <span class="pf-req">*</span></label>
                        <select v-model="form.role" class="pf-input" data-testid="user-role">
                            <option v-for="r in roles" :key="r.name" :value="r.name">{{ r.label }}</option>
                        </select>
                        <div v-if="form.errors.role" class="pf-error">{{ form.errors.role }}</div>
                    </div>
                    <div class="pf-two-col il-b0043ef4">
                        <div class="pf-field">
                            <label class="pf-label">Şifre <span class="pf-req">*</span></label>
                            <div class="il-c451fdb0">
                                <input class="pf-input il-8a63b251" :type="showPw1 ? 'text' : 'password'" v-model="form.password" placeholder="••••••••" data-testid="user-password">
                                <button class="il-bbea2ad4" type="button" @click="showPw1=!showPw1"><i class="bi" :class="showPw1 ? 'bi-eye' : 'bi-eye-slash'"></i></button>
                            </div>
                            <div class="pf-pass-bars">
                                <div v-for="n in 4" :key="n" class="pf-pbar" :style="{ background: pwStrength >= n ? (pwStrength>=3?'#10b981':'#fbbf24') : '' }"></div>
                            </div>
                            <div v-if="form.errors.password" class="pf-error">{{ form.errors.password }}</div>
                        </div>
                        <div class="pf-field">
                            <label class="pf-label">Şifre Tekrar <span class="pf-req">*</span></label>
                            <div class="il-c451fdb0">
                                <input class="pf-input il-8a63b251" :type="showPw2 ? 'text' : 'password'" v-model="form.password_confirmation" placeholder="••••••••">
                                <button class="il-bbea2ad4" type="button" @click="showPw2=!showPw2"><i class="bi" :class="showPw2 ? 'bi-eye' : 'bi-eye-slash'"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="pf-hint mb-4">En az 8 karakter, büyük/küçük harf ve sembol içermeli.</div>
                    <div class="pf-toggle-list">
                        <label class="pf-trow il-310b5d97">
                            <div class="pf-trow-info"><div class="pf-trow-title">Hesap Doğrulaması</div><div class="pf-trow-desc">Hesabı doğrulanmış olarak oluştur</div></div>
                            <input type="checkbox" v-model="form.is_verified" class="pf-tog-input" data-testid="user-verified">
                        </label>
                    </div>
                    <div class="pf-footer il-3ae1f62a">
                        <span class="pf-save-info"><i class="bi bi-shield-lock"></i> Rol anında atanır</span>
                        <button type="submit" class="pf-btn-save" :disabled="form.processing" data-testid="user-save-2"><i class="bi bi-floppy me-1"></i> Kaydet</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped src="../../../../css/pages/admin-users-create.css"></style>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
export default { layout: AppLayout };
</script>

<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({ profileData: Object });
const p = props.profileData;

const page = usePage();
const flash = computed(() => page.props.flash || {});

const statusMap = {
    approved: { label: '✓ Onaylı Satıcı', color: 'rgba(16,185,129,.15)', border: 'rgba(16,185,129,.3)', text: '#10b981' },
    pending:  { label: '⏳ İncelemede',    color: 'rgba(245,158,11,.15)', border: 'rgba(245,158,11,.3)', text: '#f59e0b' },
    rejected: { label: '✗ Reddedildi',     color: 'rgba(239,68,68,.15)',  border: 'rgba(239,68,68,.3)',  text: '#ef4444' },
};
const st = computed(() => statusMap[p.verification_status] || statusMap.pending);
const docStatusLabel = { pending: 'İncelemede', approved: 'Onaylı', rejected: 'Reddedildi' };
const docBadgeClass = computed(() => p.verification_status === 'approved' ? 'success' : (p.verification_status === 'rejected' ? 'danger' : 'warning'));

const activeTab = ref(flash.value.profile_section || 'kisisel');
function switchTab(key) { activeTab.value = key; }

const kisiselForm = useForm({ name: p.name, phone: p.phone });
const sirketForm  = useForm({ company_name: p.company_name, tax_number: p.tax_number });
const odemeForm   = useForm({ iban: '' });
const docForm     = useForm({ id_document: null });

const ibanDigits = ref(p.iban_input || '');
const ibanDisplay = computed(() => ibanDigits.value.replace(/(.{4})/g, '$1 ').trim());
function onIbanInput(e) {
    ibanDigits.value = e.target.value.replace(/\D/g, '').slice(0, 24);
    // DOM'u zorla senkronla — 24 haneye ulaşıldıktan sonra girilen fazla/hatalı
    // karakterlerin input'ta takılı kalmasını önler (computed değişmediğinde Vue DOM'u atlıyor).
    e.target.value = ibanDisplay.value;
}

function saveKisisel() { kisiselForm.put(route('seller.profile.update', 'kisisel'), { preserveScroll: true }); }
function saveSirket()  { sirketForm.put(route('seller.profile.update', 'sirket'), { preserveScroll: true }); }
function saveOdeme() {
    odemeForm.iban = ibanDigits.value ? 'TR' + ibanDigits.value : '';
    odemeForm.put(route('seller.profile.update', 'odeme'), { preserveScroll: true });
}

const docName = ref('');
function onDocChange(e) {
    const f = e.target.files?.[0];
    if (!f) return;
    docForm.id_document = f;
    docName.value = f.name;
}
function uploadDoc() {
    docForm.post(route('seller.profile.document.upload'), { preserveScroll: true, forceFormData: true });
}

function saveActiveTab() {
    if (activeTab.value === 'kisisel') saveKisisel();
    else if (activeTab.value === 'sirket') saveSirket();
    else if (activeTab.value === 'odeme') saveOdeme();
    else if (activeTab.value === 'belge') uploadDoc();
}
</script>

<template>
    <Head title="Satıcı Profilim" />
    <div class="pf-root">

        <div class="pf-top">
            <div class="pf-cover"></div>
            <div class="pf-identity">
                <div class="pf-avatar-wrap">
                    <div class="pf-avatar-outer il-15aa321b">
                        <i class="bi bi-shop il-56d0cd61"></i>
                    </div>
                </div>
                <div class="pf-identity-right">
                    <div class="pf-uname-row">
                        <span class="pf-uname">{{ p.name }}</span>
                        <span class="pf-role-badge" :style="{ background: st.color, borderColor: st.border, color: st.text }">{{ st.label }}</span>
                    </div>
                    <div class="pf-bio">Satıcı profilinizi buradan yönetebilirsiniz.</div>
                </div>
            </div>

            <div class="pf-stats-row">
                <div class="pf-stat"><div class="pf-stat-num">{{ p.auctions_count }}</div><div class="pf-stat-label">İLAN</div></div>
                <div class="pf-stat"><div class="pf-stat-num">{{ p.active_count }}</div><div class="pf-stat-label">AKTİF</div></div>
                <div class="pf-stat"><div class="pf-stat-num">{{ p.verification_status === 'approved' ? '✓' : '—' }}</div><div class="pf-stat-label">DOĞRULAMA</div></div>
                <div class="pf-stat">
                    <div class="pf-stat-num" :style="{ fontSize: 'var(--fs-sm)', color: p.iban_masked ? '#10b981' : 'var(--muted)' }">{{ p.iban_masked ? 'Tanımlı' : 'Eksik' }}</div>
                    <div class="pf-stat-label">IBAN</div>
                </div>
            </div>

            <div class="pf-action-row breadcrumb-action-row">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 pf-breadcrumb-list">
                        <li class="breadcrumb-item"><Link :href="route('index')" class="pf-link-primary">Ana Sayfa</Link></li>
                        <li class="breadcrumb-item active pf-text-muted">Satıcı Profilim</li>
                    </ol>
                </nav>
                <div class="pf-action-buttons">
                    <button type="button" class="pf-btn-save" @click="saveActiveTab"><i class="bi bi-floppy me-1"></i> Kaydet</button>
                </div>
            </div>
        </div>

        <div class="il-c4bb895e" v-if="p.verification_status === 'rejected' && p.rejection_reason">
            <i class="bi bi-x-circle-fill il-f0246f3c"></i>
            <div><strong>Başvurunuz reddedildi:</strong> {{ p.rejection_reason }}</div>
        </div>

        <div class="pf-content-area il-ddfe94d4">

            <div class="pf-tab-bar wraping">
                <button class="pf-ptab bar-item" :class="{ active: activeTab === 'kisisel' }" @click="switchTab('kisisel')"><i class="bi bi-person me-1"></i> Kişisel</button>
                <button class="pf-ptab bar-item" :class="{ active: activeTab === 'sirket' }" @click="switchTab('sirket')"><i class="bi bi-building me-1"></i> Şirket</button>
                <button class="pf-ptab bar-item" :class="{ active: activeTab === 'odeme' }" @click="switchTab('odeme')"><i class="bi bi-credit-card me-1"></i> Ödeme</button>
                <button class="pf-ptab bar-item" :class="{ active: activeTab === 'belge' }" @click="switchTab('belge')"><i class="bi bi-file-earmark-person me-1"></i> Belge</button>
            </div>

            <div class="s-panel" :class="{ 's-active': activeTab === 'kisisel' }">
                <form @submit.prevent="saveKisisel" class="s-form il-bb15b30f">
                    <div class="s-2col">
                        <div class="s-field">
                            <label class="s-lbl">Ad Soyad <span class="pf-req">*</span></label>
                            <input class="pf-input" type="text" v-model="kisiselForm.name" placeholder="Ad Soyad">
                            <div v-if="kisiselForm.errors.name" class="pf-error">{{ kisiselForm.errors.name }}</div>
                        </div>
                        <div class="s-field">
                            <label class="s-lbl">E-posta</label>
                            <input class="pf-input" type="email" :value="p.email" disabled>
                            <div class="s-hint">E-posta değiştirilemez.</div>
                        </div>
                    </div>
                    <div class="s-field">
                        <label class="s-lbl">Telefon</label>
                        <div class="pf-input-pre">
                            <span class="pf-pre-label">+90</span>
                            <input type="tel" v-model="kisiselForm.phone" placeholder="5xx xxx xx xx">
                        </div>
                        <div v-if="kisiselForm.errors.phone" class="pf-error">{{ kisiselForm.errors.phone }}</div>
                    </div>
                    <div class="s-foot">
                        <button type="submit" class="pf-btn-save" :disabled="kisiselForm.processing"><i class="bi bi-floppy me-1"></i> Kaydet</button>
                    </div>
                </form>
            </div>

            <div class="s-panel" :class="{ 's-active': activeTab === 'sirket' }">
                <form @submit.prevent="saveSirket" class="s-form il-bb15b30f">
                    <div class="s-hint mb-3 il-57f9af3e">
                        <i class="bi bi-info-circle me-1"></i> Şirket ve vergi bilgileri doğrulama sürecinde kullanılır. Kurumsal satıcılar için zorunludur.
                    </div>
                    <div class="s-2col">
                        <div class="s-field">
                            <label class="s-lbl">Şirket / Marka Adı</label>
                            <input class="pf-input" type="text" v-model="sirketForm.company_name" placeholder="Artirdim A.Ş.">
                            <div v-if="sirketForm.errors.company_name" class="pf-error">{{ sirketForm.errors.company_name }}</div>
                        </div>
                        <div class="s-field">
                            <label class="s-lbl">Vergi / TC Kimlik No</label>
                            <input class="pf-input" type="text" v-model="sirketForm.tax_number" placeholder="10 veya 11 hane" maxlength="11">
                            <div v-if="sirketForm.errors.tax_number" class="pf-error">{{ sirketForm.errors.tax_number }}</div>
                        </div>
                    </div>
                    <div class="s-field">
                        <label class="s-lbl">Doğrulama Durumu</label>
                        <div class="il-c38a388e">
                            <span :style="{ width:'9px',height:'9px',borderRadius:'50%',flexShrink:0,background: st.text }"></span>
                            <span :style="{ fontSize:'var(--fs-sm)',fontWeight:600,color: st.text }">{{ st.label }}</span>
                            <span class="il-0b3689bf" v-if="p.verified_at">{{ p.verified_at }}</span>
                        </div>
                    </div>
                    <div class="s-foot">
                        <button type="submit" class="pf-btn-save" :disabled="sirketForm.processing"><i class="bi bi-floppy me-1"></i> Kaydet</button>
                    </div>
                </form>
            </div>

            <div class="s-panel" :class="{ 's-active': activeTab === 'odeme' }">
                <form @submit.prevent="saveOdeme" class="s-form il-bb15b30f">
                    <div class="s-hint mb-3 il-bafb279d">
                        <i class="bi bi-shield-lock me-1"></i> IBAN bilginiz şifreli olarak saklanır ve yalnızca ödeme transferlerinde kullanılır.
                    </div>
                    <div class="s-field">
                        <label class="s-lbl">IBAN <span class="pf-req">*</span></label>
                        <div class="pf-input-pre">
                            <span class="pf-pre-label">TR</span>
                            <input type="text" :value="ibanDisplay" @input="onIbanInput" placeholder="00 0000 0000 0000 0000 0000 00" maxlength="30">
                        </div>
                        <div v-if="odemeForm.errors.iban" class="pf-error">{{ odemeForm.errors.iban }}</div>
                        <div class="s-hint">TR ile başlayan 26 haneli IBAN. Örn: TR33 0006 1005 1978 6457 8413 26</div>
                    </div>
                    <div class="il-2487240b" v-if="p.iban_masked">
                        <i class="bi bi-check-circle il-29fb9df2"></i>
                        <span class="il-e050fd88">Kayıtlı:</span>
                        <span class="il-d1423350">{{ p.iban_masked }}</span>
                    </div>
                    <div class="s-foot">
                        <button type="submit" class="pf-btn-save" :disabled="odemeForm.processing"><i class="bi bi-floppy me-1"></i> Kaydet</button>
                    </div>
                </form>
            </div>

            <div class="s-panel" :class="{ 's-active': activeTab === 'belge' }">
                <div class="s-form il-bb15b30f">
                    <div class="s-hint mb-3 il-4a1b5ec4">
                        <i class="bi bi-exclamation-triangle me-1 il-2a4cda41"></i> Kimlik belgesi yüklemeniz satıcı hesabınızın onaylanması için zorunludur. Belge 48 saat içinde incelenir.
                    </div>

                    <div class="il-4d20a524" v-if="p.has_document">
                        <i class="bi bi-file-earmark-check il-83c4ea0c"></i>
                        <div>
                            <div class="il-93e62ea6">Belge yüklendi</div>
                            <div class="il-a2a47dab">{{ p.document_updated }} tarihinde güncellendi</div>
                        </div>
                        <span class="a-badge il-83456371" :class="docBadgeClass">{{ docStatusLabel[p.verification_status] }}</span>
                    </div>

                    <form @submit.prevent="uploadDoc">
                        <div class="s-field">
                            <label class="s-lbl">{{ p.has_document ? 'Belgeyi Güncelle' : 'Kimlik Belgesi Yükle' }}</label>
                            <label class="il-4c77a2e3" for="id_document" :style="{ borderColor: docName ? 'rgba(16,185,129,.4)' : 'var(--border)' }">
                                <i class="bi" :class="docName ? 'bi-file-earmark-check' : 'bi-cloud-upload'" :style="{ fontSize:'1.6rem', color: docName ? '#10b981' : 'var(--muted)', flexShrink:0 }"></i>
                                <div>
                                    <div class="il-93e62ea6">{{ docName || 'Dosya seç veya sürükle' }}</div>
                                    <div class="il-a2a47dab">JPG, PNG, PDF · Maks. 5MB · Nüfus cüzdanı veya pasaport</div>
                                </div>
                            </label>
                            <input type="file" id="id_document" accept=".jpg,.jpeg,.png,.pdf" class="d-none" @change="onDocChange">
                            <div v-if="docForm.errors.id_document" class="pf-error">{{ docForm.errors.id_document }}</div>
                        </div>
                        <div class="s-foot">
                            <button type="submit" class="pf-btn-save" :disabled="docForm.processing"><i class="bi bi-cloud-upload me-1"></i> Yükle</button>
                        </div>
                    </form>

                    <div class="il-86b36941">
                        <div class="il-ca993aaf" v-for="(t, i) in ['Belge tüm köşeleri görünür şekilde net çekilmiş olmalı','Ad, soyad ve TC/vergi numarası okunabilir olmalı','Belgeler yalnızca kimlik doğrulama için kullanılır']" :key="i">
                            <i class="bi bi-check2 il-7ae3917d"></i> {{ t }}
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<style scoped src="../../../../css/pages/seller-profile-edit.css"></style>

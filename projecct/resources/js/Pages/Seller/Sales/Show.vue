<script>
import AppLayout from '@/Layouts/AppLayout.vue';
export default { layout: AppLayout };
</script>

<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import OrderProgress from '@/Components/OrderProgress.vue';
import OrderTimeline from '@/Components/OrderTimeline.vue';

const props = defineProps({ order: Object, carriers: Array });

const page = usePage();
const flash = computed(() => page.props.flash || {});

const shipForm = useForm({
    carrier: '',
    tracking_number: '',
    tracking_url: '',
});

function submitShip() {
    shipForm.post(props.order.ship_url, { preserveScroll: true });
}

const showShipBox = computed(() => props.order.status === 'paid' && props.order.has_shipping_address);
const showTrackingBox = computed(() => ['shipped', 'delivered', 'completed'].includes(props.order.status) && props.order.tracking_number);
</script>

<template>
    <Head :title="`Satış ${order.order_number}`" />
    <div class="dash-wrap py-4">

        <div class="admin-toolbar dash-hero">
            <div>
                <div class="toolbar-title">Satış {{ order.order_number }}</div>
                <div class="dash-hero-sub">{{ order.auction_title }}</div>
            </div>
            <Link :href="route('seller.sales.index')" class="btn-admin-ghost"><i class="bi bi-arrow-left"></i> Satışlarım</Link>
        </div>

        <div class="admin-card il-8e86974b">
            <OrderProgress :steps="order.progress_steps" :cancelled="order.is_cancelled"
                           :status-color="order.status_color" :status-icon="order.status_icon" :status-label="order.status_label" />
        </div>

        <div class="ord-grid">
            <div>
                <div class="ord-box">
                    <div class="ord-box-title"><i class="bi bi-shield-check"></i> Ödeme Durumu</div>
                    <div v-if="order.escrow_status === 'held'" class="ord-track-badge il-e80813c4"><i class="bi bi-shield-lock"></i> Tutar emanette güvende — kargo + teslimat sonrası hesabınıza geçer</div>
                    <div v-else-if="order.escrow_status === 'released'" class="ord-track-badge il-ebcd40ad"><i class="bi bi-cash-stack"></i> Ödeme hesabınıza aktarıldı</div>
                    <div v-else class="ord-track-badge il-b8e76b41"><i class="bi bi-hourglass-split"></i> Alıcının ödemesi bekleniyor</div>
                </div>

                <div class="ord-box" data-testid="seller-address-box">
                    <div class="ord-box-title"><i class="bi bi-geo-alt"></i> Teslimat Adresi</div>
                    <template v-if="order.has_shipping_address">
                        <div class="ord-info-row"><span class="k">Alıcı</span><span class="v">{{ order.recipient_name }}</span></div>
                        <div class="ord-info-row"><span class="k">Telefon</span><span class="v">{{ order.recipient_phone }}</span></div>
                        <div class="ord-info-row"><span class="k">Adres</span><span class="v il-f66b1f3f">{{ order.shipping_address }}</span></div>
                        <div class="ord-info-row"><span class="k">İl / İlçe</span><span class="v">{{ order.address_city }}{{ order.address_district ? ' / ' + order.address_district : '' }}</span></div>
                    </template>
                    <p v-else class="pf-text-muted-sm">Alıcı henüz teslimat adresini girmedi. Adres girildiğinde kargolayabilirsiniz.</p>
                </div>

                <div v-if="showShipBox" class="ord-box" data-testid="seller-ship-box">
                    <div class="ord-box-title"><i class="bi bi-truck"></i> Kargoya Ver</div>
                    <form @submit.prevent="submitShip">
                        <div class="ord-field">
                            <label>Kargo Firması</label>
                            <select v-model="shipForm.carrier" required data-testid="ship-carrier">
                                <option value="" disabled>Kargo firması seçin</option>
                                <option v-for="c in carriers" :key="c" :value="c">{{ c }}</option>
                            </select>
                            <div v-if="shipForm.errors.carrier" class="text-danger il-6b9c179a">{{ shipForm.errors.carrier }}</div>
                        </div>
                        <div class="ord-field">
                            <label>Takip Numarası</label>
                            <input v-model="shipForm.tracking_number" placeholder="Örn: 1234567890123" required data-testid="ship-tracking">
                            <div v-if="shipForm.errors.tracking_number" class="text-danger il-6b9c179a">{{ shipForm.errors.tracking_number }}</div>
                        </div>
                        <div class="ord-field">
                            <label>Takip Linki (isteğe bağlı)</label>
                            <input v-model="shipForm.tracking_url" type="url" placeholder="https://...">
                            <div v-if="shipForm.errors.tracking_url" class="text-danger il-6b9c179a">{{ shipForm.errors.tracking_url }}</div>
                        </div>
                        <button type="submit" class="btn-admin-pri il-6d567387" :disabled="shipForm.processing" data-testid="ship-submit-btn"><i class="bi bi-send me-1"></i> Kargoya Verdim</button>
                    </form>
                </div>

                <div v-if="showTrackingBox" class="ord-box">
                    <div class="ord-box-title"><i class="bi bi-truck"></i> Kargo Bilgisi</div>
                    <div class="ord-info-row"><span class="k">Firma</span><span class="v">{{ order.carrier }}</span></div>
                    <div class="ord-info-row"><span class="k">Takip No</span><span class="v">{{ order.tracking_number }}</span></div>
                </div>

                <div v-if="order.status === 'disputed'" class="ord-box il-4afd2f09">
                    <div class="ord-box-title il-124741b6"><i class="bi bi-exclamation-octagon"></i> Anlaşmazlık</div>
                    <div class="ord-info-row"><span class="k">Alıcı sebebi</span><span class="v">{{ order.dispute_reason }}</span></div>
                    <p class="pf-text-muted-sm il-d4316970">Yönetici inceliyor. Sonuca göre ödeme size aktarılacak veya alıcıya iade edilecek.</p>
                </div>

                <div class="ord-box">
                    <div class="ord-box-title"><i class="bi bi-clock-history"></i> Sipariş Geçmişi</div>
                    <OrderTimeline :events="order.events" />
                </div>
            </div>

            <div>
                <div class="ord-box">
                    <img class="il-6c86cc5e" :src="order.cover_url" alt="">
                    <div class="il-5b025a61">{{ order.auction_title }}</div>
                    <div class="pf-text-muted-sm il-26d5662a">Alıcı: {{ order.buyer_name }}</div>
                    <div class="ord-info-row"><span class="k">Sipariş No</span><span class="v">{{ order.order_number }}</span></div>
                    <div class="ord-info-row"><span class="k">Satış Tutarı</span><span class="v">{{ order.amount }}</span></div>
                    <div class="ord-info-row"><span class="k">Komisyon</span><span class="v il-124741b6">- {{ order.commission_amount }}</span></div>
                    <div class="ord-total"><span>Net Kazanç</span><span class="il-29fb9df2">{{ order.net_amount }}</span></div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped src="../../../../css/pages/seller-sales-show.css"></style>

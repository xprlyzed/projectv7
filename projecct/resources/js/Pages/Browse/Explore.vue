<script>
import AppLayout from '@/Layouts/AppLayout.vue';
export default { layout: AppLayout };
</script>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuctionCard from '@/Components/AuctionCard.vue';
import { useInfiniteScroll } from '@/composables/useInfiniteScroll';

const props = defineProps({ categoryTree: Array, featuredAuctions: Array, newAuctions: Object, now: String });

const { items, hasMore, loading, sentinel } = useInfiniteScroll(
    () => route('browse.explore.feed'),
    props.newAuctions
);
</script>

<template>
    <Head title="Keşfet" />

    <div class="py-4">
        <div class="idx-section-head">
            <div class="idx-section-title"><i class="bi bi-compass"></i> Keşfet</div>
            <div class="idx-section-date">{{ now }} itibarıyla</div>
        </div>

        <div class="expl-cat-grid" data-testid="explore-category-tree">
            <Link v-for="root in categoryTree" :key="root.slug" :href="root.browse_url"
                  class="expl-cat-card" :data-testid="`explore-cat-${root.slug}`">
                <div class="expl-cat-media">
                    <img :src="root.image_url" :alt="root.name" class="expl-cat-thumb" loading="lazy">
                    <span class="expl-cat-badge">{{ root.auctions_count }}</span>
                </div>
                <div class="expl-cat-body">
                    <div class="expl-cat-name">{{ root.name }}</div>
                    <div class="expl-cat-count">{{ root.auctions_count }} ilan · Keşfet</div>
                    <div v-if="root.children.length" class="expl-subcats">
                        <span v-for="child in root.children.slice(0, 4)" :key="child.slug" class="expl-subchip">
                            {{ child.name }}
                        </span>
                    </div>
                </div>
                <i class="bi bi-arrow-right expl-cat-arrow"></i>
            </Link>
            <div v-if="!categoryTree.length" class="idx-empty">
                <i class="bi bi-tags"></i>
                <p>Henüz kategori eklenmemiş.</p>
            </div>
        </div>

        <template v-if="featuredAuctions.length">
            <div class="idx-section-head mt-5">
                <div class="idx-section-title"><i class="bi bi-star"></i> Öne Çıkanlar</div>
            </div>
            <div class="row g-3">
                <div v-for="auction in featuredAuctions" :key="auction.id" class="col-xl-3 col-lg-4 col-md-6">
                    <AuctionCard :auction="auction" />
                </div>
            </div>
        </template>

        <div class="idx-section-head mt-5">
            <div class="idx-section-title"><i class="bi bi-clock-history"></i> Yeni Eklenenler</div>
        </div>

        <div class="row g-3" data-testid="explore-new-grid">
            <div v-for="auction in items" :key="auction.id" class="col-xl-3 col-lg-4 col-md-6">
                <AuctionCard :auction="auction" />
            </div>
            <div v-if="!items.length" class="idx-empty">
                <i class="bi bi-inbox"></i>
                <p>Henüz ilan eklenmemiş.</p>
            </div>
        </div>

        <div ref="sentinel" class="brz-infinite" data-testid="explore-sentinel">
            <div v-if="loading" class="brz-loader" data-testid="explore-loading">
                <span class="brz-spinner"></span> Yükleniyor…
            </div>
            <div v-else-if="!hasMore && items.length" class="brz-end">Tüm ilanlar gösterildi</div>
        </div>
    </div>
</template>

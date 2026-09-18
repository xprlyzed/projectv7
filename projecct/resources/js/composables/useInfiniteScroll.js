import { ref, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';

export function useInfiniteScroll(resolveUrl, initial, resolveParams = () => ({})) {
    const items = ref([...(initial?.data ?? [])]);
    const page = ref(initial?.current_page ?? 1);
    const hasMore = ref(!!(initial?.has_more));
    const loading = ref(false);
    const sentinel = ref(null);
    let observer = null;

    async function loadMore() {
        if (loading.value || !hasMore.value) return;
        loading.value = true;
        try {
            const { data } = await axios.get(resolveUrl(), {
                params: { ...resolveParams(), page: page.value + 1 },
            });
            const seen = new Set(items.value.map((it) => it.id));
            const fresh = (data.data ?? []).filter((it) => !seen.has(it.id));
            items.value.push(...fresh);
            page.value = data.current_page;
            hasMore.value = data.has_more;
        } catch (e) {
            hasMore.value = false;
        } finally {
            loading.value = false;
        }
    }

    function reset(fresh) {
        items.value = [...(fresh?.data ?? [])];
        page.value = fresh?.current_page ?? 1;
        hasMore.value = !!(fresh?.has_more);
    }

    onMounted(() => {
        observer = new IntersectionObserver(
            (entries) => { if (entries[0].isIntersecting) loadMore(); },
            { rootMargin: '400px' }
        );
        if (sentinel.value) observer.observe(sentinel.value);
    });

    onBeforeUnmount(() => { if (observer) observer.disconnect(); });

    return { items, page, hasMore, loading, sentinel, loadMore, reset };
}

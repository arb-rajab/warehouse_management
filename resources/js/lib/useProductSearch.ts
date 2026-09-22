import { useHttp } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';
import { search as searchProducts } from '@/actions/App/Http/Controllers/Admin/ProductController';
import { debounce } from '@/lib/filters';
import { productName } from '@/lib/productName';
import type { Paginated, ProductFilterOption } from '@/types/admin';

/**
 * The debounced-search + infinite-scroll-pagination + name-memoization
 * mechanics shared by ProductSelect.vue (single-select) and
 * FilterProductSelect.vue (multi-select) — byte-identical between the two
 * before this extraction, differing only in how each renders/selects a
 * result. `namesById` only ever grows: a selected id must keep resolving to
 * its name even once it scrolls out of `results` (a new search replaces the
 * visible page, but the selection itself doesn't change).
 */
export function useProductSearch() {
    const query = ref('');
    const results = ref<ProductFilterOption[]>([]);
    const page = ref<Paginated<ProductFilterOption> | null>(null);
    const loading = ref(false);
    let requestSeq = 0;

    const namesById = reactive(new Map<number, string>());

    function rememberNames(products: ProductFilterOption[]): void {
        for (const product of products) {
            namesById.set(
                product.id,
                productName(product.name, product.ar_name),
            );
        }
    }

    const http = useHttp({});

    function fetchPage(pageNumber: number, replace: boolean): void {
        const mySeq = ++requestSeq;
        loading.value = true;

        http.get(
            searchProducts.url({ query: { q: query.value, page: pageNumber } }),
            {
                onSuccess: (response: unknown) => {
                    if (mySeq !== requestSeq) {
                        return;
                    }

                    const body = response as Paginated<ProductFilterOption>;

                    results.value = replace
                        ? body.data
                        : [...results.value, ...body.data];
                    page.value = body;
                    rememberNames(body.data);
                },
                onFinish: () => {
                    if (mySeq === requestSeq) {
                        loading.value = false;
                    }
                },
            },
        );
    }

    const debouncedSearch = debounce(() => fetchPage(1, true), 300);

    watch(query, debouncedSearch);

    function fetchFirstPageIfEmpty(): void {
        if (results.value.length === 0 && !loading.value) {
            fetchPage(1, true);
        }
    }

    function onOptionsScroll(el: HTMLElement | null): void {
        const currentPage = page.value;

        if (!el || !currentPage || loading.value) {
            return;
        }

        if (currentPage.meta.current_page >= currentPage.meta.last_page) {
            return;
        }

        if (el.scrollHeight - el.scrollTop - el.clientHeight > 48) {
            return;
        }

        fetchPage(currentPage.meta.current_page + 1, false);
    }

    return {
        query,
        results,
        page,
        loading,
        namesById,
        rememberNames,
        fetchFirstPageIfEmpty,
        onOptionsScroll,
    };
}

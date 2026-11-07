import { reactive } from 'vue';
import { search as searchProducts } from '@/actions/App/Http/Controllers/Admin/ProductController';
import { productName } from '@/lib/productName';
import { useSearchOptions } from '@/lib/useSearchOptions';
import type { ProductFilterOption } from '@/types/admin';

/**
 * The warehouse product pickers' search, ProductSelect.vue (single-select) and
 * FilterProductSelect.vue (multi-select): the shared `useSearchOptions`
 * mechanics (debounced search, infinite-scroll paging, stale-response guard)
 * against `admin/products/search`, plus the name memoization both need.
 * `namesById` only ever grows: a selected id must keep resolving to its name
 * even once it scrolls out of `results` (a new search replaces the visible
 * page, but the selection itself doesn't change).
 */
export function useProductSearch(options: { includeInactive?: boolean } = {}) {
    const namesById = reactive(new Map<number, string>());

    function rememberNames(products: ProductFilterOption[]): void {
        for (const product of products) {
            namesById.set(
                product.id,
                productName(product.name, product.ar_name),
            );
        }
    }

    const search = useSearchOptions<ProductFilterOption>({
        endpoint: searchProducts,
        params: () => (options.includeInactive ? { include_inactive: 1 } : {}),
        onResults: rememberNames,
    });

    return {
        query: search.query,
        results: search.results,
        page: search.page,
        loading: search.loading,
        namesById,
        rememberNames,
        fetchFirstPageIfEmpty: search.fetchFirstPageIfEmpty,
        onOptionsScroll: search.onOptionsScroll,
    };
}

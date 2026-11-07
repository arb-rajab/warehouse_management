import { useHttp } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type { Ref } from 'vue';
import { debounce } from '@/lib/filters';
import type { SearchPage, SearchParams } from '@/types/searchSelect';
import type { RouteQueryOptions } from '@/wayfinder';

/** A Wayfinder action: pass the imported route (`search`, `states`, ...), never a URL string. */
export type SearchEndpoint = { url: (options?: RouteQueryOptions) => string };

export interface SearchOptionsConfig<T> {
    endpoint: SearchEndpoint;
    /** Extra query parameters, read on every request (a dependent select's `state_id`). */
    params?: () => SearchParams;
    /** A query shorter than this is not sent (0 sends from the first open). */
    minChars?: () => number;
    debounceMs?: number;
    /** Called with each page as it arrives; `replace` is true for a first page. */
    onResults?: (items: T[], replace: boolean) => void;
}

/**
 * The debounced search, stale-response guard, infinite-scroll paging and
 * failure state behind every searchable dropdown: the generalisation of what
 * `useProductSearch` did for the warehouse product pickers (which is now a thin
 * wrapper over this), shared with StoreSearchSelect. A request never lands
 * after a newer one was sent, and a failed request keeps the results already
 * shown so the dropdown can offer a retry instead of emptying.
 */
export function useSearchOptions<T>(config: SearchOptionsConfig<T>) {
    const query = ref('');
    const results = ref([]) as Ref<T[]>;
    const page = ref(null) as Ref<SearchPage<T> | null>;
    const loading = ref(false);
    const failed = ref(false);
    let requestSeq = 0;
    let unmounted = false;

    const http = useHttp({});

    const minimum = computed(() => config.minChars?.() ?? 0);
    const belowMinimum = computed(
        () => minimum.value > 0 && query.value.trim().length < minimum.value,
    );
    const hasMore = computed(
        () =>
            page.value !== null &&
            page.value.meta.current_page < page.value.meta.last_page,
    );
    const total = computed(
        () => page.value?.meta.total ?? results.value.length,
    );

    function fail(sequence: number): void {
        if (sequence === requestSeq && !unmounted) {
            failed.value = true;
        }
    }

    function fetchPage(pageNumber: number, replace: boolean): void {
        const sequence = ++requestSeq;

        if (belowMinimum.value) {
            results.value = [];
            page.value = null;
            loading.value = false;
            failed.value = false;

            return;
        }

        loading.value = true;
        failed.value = false;

        // A rejected request (HTTP error, network drop) is reported through
        // the callbacks below; the rejection itself is deliberately swallowed.
        void Promise.resolve(
            http.get(
                config.endpoint.url({
                    query: {
                        q: query.value,
                        page: pageNumber,
                        ...config.params?.(),
                    },
                }),
                {
                    onSuccess: (response: unknown) => {
                        if (sequence !== requestSeq || unmounted) {
                            return;
                        }

                        const body = response as SearchPage<T>;

                        results.value = replace
                            ? body.data
                            : [...results.value, ...body.data];
                        page.value = body;
                        config.onResults?.(body.data, replace);
                    },
                    onError: () => fail(sequence),
                    onHttpException: () => fail(sequence),
                    onNetworkError: () => fail(sequence),
                    onFinish: () => {
                        if (sequence === requestSeq) {
                            loading.value = false;
                        }
                    },
                },
            ),
        ).catch(() => undefined);
    }

    const debouncedSearch = debounce(
        () => fetchPage(1, true),
        config.debounceMs ?? 300,
    );

    watch(query, debouncedSearch);

    /** A first page now, whatever the query (the retry button, a changed parameter). */
    function refresh(): void {
        fetchPage(1, true);
    }

    function fetchFirstPageIfEmpty(): void {
        if (results.value.length === 0 && !loading.value) {
            fetchPage(1, true);
        }
    }

    function fetchNextPage(): void {
        const current = page.value;

        if (!current || loading.value || !hasMore.value) {
            return;
        }

        fetchPage(current.meta.current_page + 1, false);
    }

    function onOptionsScroll(el: HTMLElement | null): void {
        if (!el || loading.value || !hasMore.value) {
            return;
        }

        if (el.scrollHeight - el.scrollTop - el.clientHeight > 48) {
            return;
        }

        fetchNextPage();
    }

    /** Forgets what was loaded (a changed parameter makes the old rows wrong). */
    function reset(): void {
        requestSeq++;
        results.value = [];
        page.value = null;
        loading.value = false;
        failed.value = false;
    }

    onBeforeUnmount(() => {
        unmounted = true;
        requestSeq++;
    });

    return {
        query,
        results,
        page,
        loading,
        failed,
        belowMinimum,
        hasMore,
        total,
        refresh,
        reset,
        fetchFirstPageIfEmpty,
        fetchNextPage,
        onOptionsScroll,
    };
}

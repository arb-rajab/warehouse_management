import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, nextTick, ref } from 'vue';
import type { SearchPage } from '@/types/searchSelect';
import { useSearchOptions } from './useSearchOptions';
import type { SearchEndpoint } from './useSearchOptions';

type Row = { id: number };
type GetOptions = {
    onSuccess?: (response: unknown) => void;
    onError?: () => void;
    onHttpException?: () => void;
    onNetworkError?: () => void;
    onFinish?: () => void;
};

const mocks = vi.hoisted(() => ({
    get: vi.fn<(url: string, options?: GetOptions) => unknown>(),
}));

vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => ({ get: mocks.get }),
}));

const endpoint: SearchEndpoint = {
    url: (options) => `/x?${JSON.stringify(options?.query)}`,
};

function page(ids: number[], current = 1, last = 1): SearchPage<Row> {
    return {
        data: ids.map((id) => ({ id })),
        meta: { current_page: current, last_page: last, total: ids.length },
    };
}

function mountSearch(
    config: Partial<Parameters<typeof useSearchOptions<Row>>[0]> = {},
) {
    return mount(
        defineComponent({
            setup() {
                return {
                    ...useSearchOptions<Row>({ endpoint, ...config }),
                };
            },
            template: '<div></div>',
        }),
    );
}

function land(call: number, body: SearchPage<Row>) {
    mocks.get.mock.calls[call][1]?.onSuccess?.(body);
    mocks.get.mock.calls[call][1]?.onFinish?.();
}

beforeEach(() => {
    mocks.get.mockReset();
});

afterEach(() => {
    vi.useRealTimers();
});

describe('useSearchOptions', () => {
    it('sends q, the page and the extra params from the endpoint', () => {
        const wrapper = mountSearch({ params: () => ({ kind: 'products' }) });

        wrapper.vm.fetchFirstPageIfEmpty();

        expect(mocks.get.mock.calls[0][0]).toBe(
            '/x?{"q":"","page":1,"kind":"products"}',
        );
        expect(wrapper.vm.loading).toBe(true);
    });

    it('swallows a rejected request and reports it through the callbacks', async () => {
        mocks.get.mockRejectedValue(new Error('network'));
        const wrapper = mountSearch();

        wrapper.vm.fetchFirstPageIfEmpty();
        mocks.get.mock.calls[0][1]?.onNetworkError?.();
        mocks.get.mock.calls[0][1]?.onFinish?.();
        await nextTick();

        expect(wrapper.vm.failed).toBe(true);
        expect(wrapper.vm.loading).toBe(false);
    });

    it.each(['onError', 'onHttpException', 'onNetworkError'] as const)(
        'marks the search failed on %s and clears it on the next request',
        (callback) => {
            const wrapper = mountSearch();

            wrapper.vm.fetchFirstPageIfEmpty();
            mocks.get.mock.calls[0][1]?.[callback]?.();
            mocks.get.mock.calls[0][1]?.onFinish?.();
            expect(wrapper.vm.failed).toBe(true);

            wrapper.vm.refresh();
            expect(wrapper.vm.failed).toBe(false);
        },
    );

    it('replaces rows on a first page, appends on the next and reports each page', () => {
        const onResults = vi.fn();
        const wrapper = mountSearch({ onResults });

        wrapper.vm.fetchFirstPageIfEmpty();
        land(0, page([1, 2], 1, 2));
        expect(wrapper.vm.hasMore).toBe(true);

        wrapper.vm.fetchNextPage();
        expect(mocks.get.mock.calls[1][0]).toContain('"page":2');
        land(1, page([3], 2, 2));

        expect(wrapper.vm.results.map((row: Row) => row.id)).toEqual([1, 2, 3]);
        expect(wrapper.vm.hasMore).toBe(false);
        expect(onResults).toHaveBeenNthCalledWith(
            1,
            [{ id: 1 }, { id: 2 }],
            true,
        );
        expect(onResults).toHaveBeenNthCalledWith(2, [{ id: 3 }], false);
    });

    it('does not request a next page while loading or on the last page', () => {
        const wrapper = mountSearch();

        wrapper.vm.fetchFirstPageIfEmpty();
        wrapper.vm.fetchNextPage();
        expect(mocks.get).toHaveBeenCalledTimes(1);

        land(0, page([1]));
        wrapper.vm.fetchNextPage();
        expect(mocks.get).toHaveBeenCalledTimes(1);
    });

    it('loads the next page when the list is scrolled near its end only', () => {
        const wrapper = mountSearch();
        wrapper.vm.fetchFirstPageIfEmpty();
        land(0, page([1], 1, 3));
        const list = document.createElement('div');
        Object.defineProperty(list, 'scrollHeight', { value: 400 });
        Object.defineProperty(list, 'clientHeight', { value: 200 });
        Object.defineProperty(list, 'scrollTop', {
            value: 100,
            writable: true,
        });

        wrapper.vm.onOptionsScroll(list);
        expect(mocks.get).toHaveBeenCalledTimes(1);

        list.scrollTop = 160;
        wrapper.vm.onOptionsScroll(list);
        expect(mocks.get).toHaveBeenCalledTimes(2);
        wrapper.vm.onOptionsScroll(null);
        expect(mocks.get).toHaveBeenCalledTimes(2);
    });

    it('debounces a changed query and drops the response of an older request', async () => {
        vi.useFakeTimers();
        const wrapper = mountSearch({ debounceMs: 200 });

        wrapper.vm.query = 'a';
        await nextTick();
        wrapper.vm.query = 'ab';
        await nextTick();
        await vi.advanceTimersByTimeAsync(199);
        expect(mocks.get).not.toHaveBeenCalled();
        await vi.advanceTimersByTimeAsync(1);
        expect(mocks.get).toHaveBeenCalledTimes(1);
        expect(mocks.get.mock.calls[0][0]).toContain('"q":"ab"');

        wrapper.vm.refresh();
        land(1, page([2]));
        land(0, page([1]));

        expect(wrapper.vm.results.map((row: Row) => row.id)).toEqual([2]);
    });

    it('sends nothing for a query below the minimum and empties the rows', () => {
        const min = ref(3);
        const wrapper = mountSearch({ minChars: () => min.value });

        wrapper.vm.fetchFirstPageIfEmpty();
        expect(mocks.get).not.toHaveBeenCalled();
        expect(wrapper.vm.belowMinimum).toBe(true);

        min.value = 0;
        wrapper.vm.refresh();
        land(0, page([1]));
        expect(wrapper.vm.results).toHaveLength(1);

        min.value = 3;
        wrapper.vm.query = 'ab';
        wrapper.vm.refresh();
        expect(wrapper.vm.results).toHaveLength(0);
        expect(mocks.get).toHaveBeenCalledTimes(1);
    });

    it('forgets everything on reset and ignores a response that arrives after it', () => {
        const wrapper = mountSearch();

        wrapper.vm.fetchFirstPageIfEmpty();
        wrapper.vm.reset();
        land(0, page([1]));

        expect(wrapper.vm.results).toEqual([]);
        expect(wrapper.vm.loading).toBe(false);
    });

    it('ignores a response that lands after the component is gone', () => {
        const wrapper = mountSearch();
        wrapper.vm.fetchFirstPageIfEmpty();

        wrapper.unmount();
        mocks.get.mock.calls[0][1]?.onSuccess?.(page([1]));

        expect(mocks.get).toHaveBeenCalledTimes(1);
    });
});

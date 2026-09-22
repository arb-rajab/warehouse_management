import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent } from 'vue';
import type { Paginated, ProductFilterOption } from '@/types/admin';
import { i18n } from './i18n';
import { useProductSearch } from './useProductSearch';

interface HttpGetOptions {
    onSuccess?: (response: Paginated<ProductFilterOption>) => void;
    onFinish?: () => void;
}

const { getMock } = vi.hoisted(() => ({
    getMock: vi.fn<(url: string, options?: HttpGetOptions) => void>(),
}));

vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => ({ get: getMock }),
}));

function page(
    data: ProductFilterOption[],
    { currentPage = 1, lastPage = 1 } = {},
): Paginated<ProductFilterOption> {
    return {
        data,
        meta: {
            current_page: currentPage,
            last_page: lastPage,
            per_page: 20,
            total: data.length,
            from: data.length ? 1 : null,
            to: data.length,
            links: [],
        },
    };
}

function resolveCall(index: number, response: Paginated<ProductFilterOption>) {
    // A real request always calls onFinish after onSuccess (or after
    // onError), regardless of outcome.
    getMock.mock.calls[index][1]?.onSuccess?.(response);
    getMock.mock.calls[index][1]?.onFinish?.();
}

function finishCall(index: number) {
    getMock.mock.calls[index][1]?.onFinish?.();
}

function scrollElement({
    scrollHeight = 100,
    clientHeight = 50,
    scrollTop = 60,
} = {}): HTMLElement {
    const el = document.createElement('div');
    Object.defineProperty(el, 'scrollHeight', { value: scrollHeight });
    Object.defineProperty(el, 'clientHeight', { value: clientHeight });
    Object.defineProperty(el, 'scrollTop', { value: scrollTop });

    return el;
}

function mountSearch() {
    return mount(
        defineComponent({
            setup() {
                return { ...useProductSearch() };
            },
            template: '<div></div>',
        }),
    );
}

describe('useProductSearch', () => {
    beforeEach(() => {
        getMock.mockClear();
    });

    afterEach(() => {
        vi.useRealTimers();
        i18n.global.locale.value = 'en';
    });

    it('starts empty, idle, with no results', () => {
        const wrapper = mountSearch();

        expect(wrapper.vm.query).toBe('');
        expect(wrapper.vm.results).toEqual([]);
        expect(wrapper.vm.loading).toBe(false);
        expect(getMock).not.toHaveBeenCalled();
    });

    it('fetchFirstPageIfEmpty fetches page 1 when results are empty', () => {
        const wrapper = mountSearch();

        wrapper.vm.fetchFirstPageIfEmpty();

        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock.mock.calls[0][0]).toContain('page=1');
    });

    it('fetchFirstPageIfEmpty does nothing once results are already loaded', async () => {
        const wrapper = mountSearch();

        wrapper.vm.fetchFirstPageIfEmpty();
        resolveCall(0, page([{ id: 1, name: 'Widgets', ar_name: 'ودجات' }]));
        await wrapper.vm.$nextTick();

        wrapper.vm.fetchFirstPageIfEmpty();

        expect(getMock).toHaveBeenCalledTimes(1);
    });

    it('clears loading once a failed request finishes, without ever calling onSuccess', async () => {
        const wrapper = mountSearch();

        wrapper.vm.fetchFirstPageIfEmpty();
        expect(wrapper.vm.loading).toBe(true);

        // A failed request (session expiry, 500, network blip) never calls
        // onSuccess, only onFinish — loading must still clear so the
        // dropdown can recover instead of spinning forever.
        finishCall(0);
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.loading).toBe(false);

        // The recovery paths gated on `loading` work again afterward.
        wrapper.vm.fetchFirstPageIfEmpty();
        expect(getMock).toHaveBeenCalledTimes(2);
    });

    it('replaces results and remembers names on a fetched page', async () => {
        const wrapper = mountSearch();

        wrapper.vm.fetchFirstPageIfEmpty();
        resolveCall(
            0,
            page([
                { id: 1, name: 'Widgets', ar_name: 'ودجات' },
                { id: 2, name: 'Gadgets', ar_name: 'أدوات' },
            ]),
        );
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.results).toEqual([
            { id: 1, name: 'Widgets', ar_name: 'ودجات' },
            { id: 2, name: 'Gadgets', ar_name: 'أدوات' },
        ]);
        expect(wrapper.vm.namesById.get(1)).toBe('Widgets');
        expect(wrapper.vm.namesById.get(2)).toBe('Gadgets');
    });

    it('rememberNames records a name without fetching', () => {
        const wrapper = mountSearch();

        wrapper.vm.rememberNames([
            { id: 5, name: 'Preselected', ar_name: 'محدد مسبقا' },
        ]);

        expect(wrapper.vm.namesById.get(5)).toBe('Preselected');
        expect(getMock).not.toHaveBeenCalled();
    });

    it('debounces query changes into a single fetch after 300ms', async () => {
        vi.useFakeTimers();
        const wrapper = mountSearch();

        wrapper.vm.query = 'w';
        wrapper.vm.query = 'wi';
        wrapper.vm.query = 'wid';

        expect(getMock).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(300);

        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock.mock.calls[0][0]).toContain('q=wid');
    });

    it('discards a stale response that resolves after a newer search', async () => {
        vi.useFakeTimers();
        const wrapper = mountSearch();

        wrapper.vm.query = 'old';
        await vi.advanceTimersByTimeAsync(300);

        wrapper.vm.query = 'new';
        await vi.advanceTimersByTimeAsync(300);

        expect(getMock).toHaveBeenCalledTimes(2);

        // Resolve the newer ("new") request first, then the stale ("old") one.
        resolveCall(
            1,
            page([{ id: 2, name: 'New product', ar_name: 'منتج جديد' }]),
        );
        resolveCall(
            0,
            page([{ id: 1, name: 'Old product', ar_name: 'منتج قديم' }]),
        );

        expect(wrapper.vm.results).toEqual([
            { id: 2, name: 'New product', ar_name: 'منتج جديد' },
        ]);
    });

    it('onOptionsScroll fetches the next page when scrolled near the bottom', async () => {
        const wrapper = mountSearch();

        wrapper.vm.fetchFirstPageIfEmpty();
        resolveCall(
            0,
            page([{ id: 1, name: 'Widgets', ar_name: 'ودجات' }], {
                currentPage: 1,
                lastPage: 2,
            }),
        );
        await wrapper.vm.$nextTick();

        wrapper.vm.onOptionsScroll(scrollElement());

        expect(getMock).toHaveBeenCalledTimes(2);
        expect(getMock.mock.calls[1][0]).toContain('page=2');
    });

    it('onOptionsScroll appends (not replaces) the next page', async () => {
        const wrapper = mountSearch();

        wrapper.vm.fetchFirstPageIfEmpty();
        resolveCall(
            0,
            page([{ id: 1, name: 'Widgets', ar_name: 'ودجات' }], {
                currentPage: 1,
                lastPage: 2,
            }),
        );
        await wrapper.vm.$nextTick();

        wrapper.vm.onOptionsScroll(scrollElement());
        resolveCall(
            1,
            page([{ id: 2, name: 'Gadgets', ar_name: 'أدوات' }], {
                currentPage: 2,
                lastPage: 2,
            }),
        );
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.results).toEqual([
            { id: 1, name: 'Widgets', ar_name: 'ودجات' },
            { id: 2, name: 'Gadgets', ar_name: 'أدوات' },
        ]);
    });

    it('onOptionsScroll does nothing once the last page is already loaded', async () => {
        const wrapper = mountSearch();

        wrapper.vm.fetchFirstPageIfEmpty();
        resolveCall(
            0,
            page([{ id: 1, name: 'Widgets', ar_name: 'ودجات' }], {
                currentPage: 1,
                lastPage: 1,
            }),
        );
        await wrapper.vm.$nextTick();

        wrapper.vm.onOptionsScroll(scrollElement());

        expect(getMock).toHaveBeenCalledTimes(1);
    });

    it('onOptionsScroll does nothing when not scrolled near the bottom', async () => {
        const wrapper = mountSearch();

        wrapper.vm.fetchFirstPageIfEmpty();
        resolveCall(
            0,
            page([{ id: 1, name: 'Widgets', ar_name: 'ودجات' }], {
                currentPage: 1,
                lastPage: 2,
            }),
        );
        await wrapper.vm.$nextTick();

        wrapper.vm.onOptionsScroll(
            scrollElement({
                scrollHeight: 500,
                clientHeight: 50,
                scrollTop: 0,
            }),
        );

        expect(getMock).toHaveBeenCalledTimes(1);
    });

    it('onOptionsScroll does nothing while a request is already in flight', async () => {
        const wrapper = mountSearch();

        wrapper.vm.fetchFirstPageIfEmpty();
        resolveCall(
            0,
            page([{ id: 1, name: 'Widgets', ar_name: 'ودجات' }], {
                currentPage: 1,
                lastPage: 2,
            }),
        );
        await wrapper.vm.$nextTick();

        wrapper.vm.onOptionsScroll(scrollElement());
        expect(getMock).toHaveBeenCalledTimes(2);

        // The second page's request is still pending (not resolved) when a
        // further scroll event fires — must not fire a third request.
        wrapper.vm.onOptionsScroll(scrollElement());
        expect(getMock).toHaveBeenCalledTimes(2);
    });

    it('memoizes the label resolved for the active locale, not the raw name', async () => {
        i18n.global.locale.value = 'ar';
        const wrapper = mountSearch();

        wrapper.vm.fetchFirstPageIfEmpty();
        resolveCall(
            0,
            page([
                { id: 1, name: 'Widgets', ar_name: 'ودجات' },
                { id: 2, name: 'Gadgets', ar_name: '' },
            ]),
        );
        await wrapper.vm.$nextTick();

        // The memo outlives the result page it came from (a selected id keeps
        // resolving once it scrolls away), so it has to hold the rendered
        // label — an untranslated product still falling back to its base name.
        expect(wrapper.vm.namesById.get(1)).toBe('ودجات');
        expect(wrapper.vm.namesById.get(2)).toBe('Gadgets');
    });

    it('rememberNames resolves the label for the active locale too', () => {
        i18n.global.locale.value = 'ar';
        const wrapper = mountSearch();

        wrapper.vm.rememberNames([
            { id: 5, name: 'Preselected', ar_name: 'محدد مسبقا' },
        ]);

        expect(wrapper.vm.namesById.get(5)).toBe('محدد مسبقا');
    });
});

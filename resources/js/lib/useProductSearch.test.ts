import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent } from 'vue';
import type { Paginated, ProductFilterOption } from '@/types/admin';
import { useProductSearch } from './useProductSearch';

interface HttpGetOptions {
    onSuccess?: (response: Paginated<ProductFilterOption>) => void;
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
    getMock.mock.calls[index][1]?.onSuccess?.(response);
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
        resolveCall(0, page([{ id: 1, name: 'Widgets' }]));
        await wrapper.vm.$nextTick();

        wrapper.vm.fetchFirstPageIfEmpty();

        expect(getMock).toHaveBeenCalledTimes(1);
    });

    it('replaces results and remembers names on a fetched page', async () => {
        const wrapper = mountSearch();

        wrapper.vm.fetchFirstPageIfEmpty();
        resolveCall(
            0,
            page([
                { id: 1, name: 'Widgets' },
                { id: 2, name: 'Gadgets' },
            ]),
        );
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.results).toEqual([
            { id: 1, name: 'Widgets' },
            { id: 2, name: 'Gadgets' },
        ]);
        expect(wrapper.vm.namesById.get(1)).toBe('Widgets');
        expect(wrapper.vm.namesById.get(2)).toBe('Gadgets');
    });

    it('rememberNames records a name without fetching', () => {
        const wrapper = mountSearch();

        wrapper.vm.rememberNames([{ id: 5, name: 'Preselected' }]);

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
        resolveCall(1, page([{ id: 2, name: 'New product' }]));
        resolveCall(0, page([{ id: 1, name: 'Old product' }]));

        expect(wrapper.vm.results).toEqual([{ id: 2, name: 'New product' }]);
    });

    it('onOptionsScroll fetches the next page when scrolled near the bottom', async () => {
        const wrapper = mountSearch();

        wrapper.vm.fetchFirstPageIfEmpty();
        resolveCall(
            0,
            page([{ id: 1, name: 'Widgets' }], { currentPage: 1, lastPage: 2 }),
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
            page([{ id: 1, name: 'Widgets' }], { currentPage: 1, lastPage: 2 }),
        );
        await wrapper.vm.$nextTick();

        wrapper.vm.onOptionsScroll(scrollElement());
        resolveCall(
            1,
            page([{ id: 2, name: 'Gadgets' }], { currentPage: 2, lastPage: 2 }),
        );
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.results).toEqual([
            { id: 1, name: 'Widgets' },
            { id: 2, name: 'Gadgets' },
        ]);
    });

    it('onOptionsScroll does nothing once the last page is already loaded', async () => {
        const wrapper = mountSearch();

        wrapper.vm.fetchFirstPageIfEmpty();
        resolveCall(
            0,
            page([{ id: 1, name: 'Widgets' }], { currentPage: 1, lastPage: 1 }),
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
            page([{ id: 1, name: 'Widgets' }], { currentPage: 1, lastPage: 2 }),
        );
        await wrapper.vm.$nextTick();

        wrapper.vm.onOptionsScroll(
            scrollElement({ scrollHeight: 500, clientHeight: 50, scrollTop: 0 }),
        );

        expect(getMock).toHaveBeenCalledTimes(1);
    });

    it('onOptionsScroll does nothing while a request is already in flight', async () => {
        const wrapper = mountSearch();

        wrapper.vm.fetchFirstPageIfEmpty();
        resolveCall(
            0,
            page([{ id: 1, name: 'Widgets' }], { currentPage: 1, lastPage: 2 }),
        );
        await wrapper.vm.$nextTick();

        wrapper.vm.onOptionsScroll(scrollElement());
        expect(getMock).toHaveBeenCalledTimes(2);

        // The second page's request is still pending (not resolved) when a
        // further scroll event fires — must not fire a third request.
        wrapper.vm.onOptionsScroll(scrollElement());
        expect(getMock).toHaveBeenCalledTimes(2);
    });
});

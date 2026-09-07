import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { t } from '@/lib/i18n';
import type { Paginated, ProductFilterOption } from '@/types/admin';
import ProductSelect from './ProductSelect.vue';

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

function mountSelect(
    props: Partial<{ modelValue: ProductFilterOption | null }> = {},
) {
    let modelValue = props.modelValue ?? null;

    return mount(ProductSelect, {
        props: {
            id: 'pallet-action-product',
            label: 'Product',
            placeholder: 'Choose a product',
            modelValue,
            'onUpdate:modelValue': (value: ProductFilterOption | null) => {
                modelValue = value;
            },
        },
    });
}

describe('ProductSelect', () => {
    beforeEach(() => {
        getMock.mockClear();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('shows the placeholder when nothing is selected', () => {
        const wrapper = mountSelect();

        expect(wrapper.get('button').text()).toBe('Choose a product');
    });

    it("resolves the selected product's label from the model without fetching", () => {
        const wrapper = mountSelect({
            modelValue: { id: 99, name: 'Widget' },
        });

        expect(wrapper.get('button').text()).toBe('Widget');
        expect(getMock).not.toHaveBeenCalled();
    });

    it('renders the search input and fetches the first page when opened', async () => {
        const wrapper = mountSelect();

        await wrapper.get('button').trigger('click');

        expect(
            wrapper.get('input[type="search"]').attributes('placeholder'),
        ).toBe(t('cellLog.filters.searchPlaceholder'));
        expect(getMock).toHaveBeenCalledTimes(1);
    });

    it('shows a loading message while the first page is in flight', async () => {
        const wrapper = mountSelect();

        await wrapper.get('button').trigger('click');

        expect(wrapper.text()).toContain(t('cellLog.filters.searching'));
    });

    it('renders fetched options and emits the selection on click', async () => {
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');

        resolveCall(
            0,
            page([
                { id: 1, name: 'Widgets' },
                { id: 2, name: 'Gadgets' },
            ]),
        );
        await wrapper.vm.$nextTick();

        const options = wrapper.findAll('[role="option"]');
        expect(options).toHaveLength(2);

        await options[1].trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([
            [{ id: 2, name: 'Gadgets' }],
        ]);
    });

    it('closes the panel after selecting an option', async () => {
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');

        resolveCall(0, page([{ id: 1, name: 'Widgets' }]));
        await wrapper.vm.$nextTick();

        await wrapper.get('[role="option"]').trigger('click');

        expect(wrapper.find('[role="listbox"]').exists()).toBe(false);
    });

    it('shows "no products found" when a search resolves empty', async () => {
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');

        resolveCall(0, page([]));
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain(t('cellLog.filters.noProductsFound'));
    });

    it('debounces search-as-you-type into a single fetch after 300ms', async () => {
        vi.useFakeTimers();
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');
        getMock.mockClear();

        const input = wrapper.get('input[type="search"]');
        await input.setValue('w');
        await input.setValue('wi');
        await input.setValue('wid');

        expect(getMock).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(300);

        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock.mock.calls[0][0]).toContain('q=wid');
    });

    it('appends (not replaces) results when scrolling loads the next page', async () => {
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');

        resolveCall(
            0,
            page([{ id: 1, name: 'Widgets' }], { currentPage: 1, lastPage: 2 }),
        );
        await wrapper.vm.$nextTick();

        const list = wrapper.get('[role="listbox"]').element as HTMLElement;
        Object.defineProperty(list, 'scrollHeight', {
            value: 100,
            configurable: true,
        });
        Object.defineProperty(list, 'clientHeight', {
            value: 50,
            configurable: true,
        });
        Object.defineProperty(list, 'scrollTop', {
            value: 60,
            configurable: true,
        });

        await wrapper.get('[role="listbox"]').trigger('scroll');

        expect(getMock).toHaveBeenCalledTimes(2);
        expect(getMock.mock.calls[1][0]).toContain('page=2');

        resolveCall(
            1,
            page([{ id: 2, name: 'Gadgets' }], { currentPage: 2, lastPage: 2 }),
        );
        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('[role="option"]')).toHaveLength(2);
    });

    it('shows a loading indicator alongside already-rendered options while fetching the next page', async () => {
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');

        resolveCall(
            0,
            page([{ id: 1, name: 'Widgets' }], { currentPage: 1, lastPage: 2 }),
        );
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).not.toContain(t('cellLog.filters.searching'));

        const list = wrapper.get('[role="listbox"]').element as HTMLElement;
        Object.defineProperty(list, 'scrollHeight', {
            value: 100,
            configurable: true,
        });
        Object.defineProperty(list, 'clientHeight', {
            value: 50,
            configurable: true,
        });
        Object.defineProperty(list, 'scrollTop', {
            value: 60,
            configurable: true,
        });

        await wrapper.get('[role="listbox"]').trigger('scroll');

        expect(wrapper.findAll('[role="option"]')).toHaveLength(1);
        expect(wrapper.text()).toContain(t('cellLog.filters.searching'));

        resolveCall(
            1,
            page([{ id: 2, name: 'Gadgets' }], { currentPage: 2, lastPage: 2 }),
        );
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).not.toContain(t('cellLog.filters.searching'));
    });

    it('does not fetch another page once the last page is loaded', async () => {
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');

        resolveCall(
            0,
            page([{ id: 1, name: 'Widgets' }], { currentPage: 1, lastPage: 1 }),
        );
        await wrapper.vm.$nextTick();

        const list = wrapper.get('[role="listbox"]').element as HTMLElement;
        Object.defineProperty(list, 'scrollHeight', {
            value: 100,
            configurable: true,
        });
        Object.defineProperty(list, 'clientHeight', {
            value: 50,
            configurable: true,
        });
        Object.defineProperty(list, 'scrollTop', {
            value: 60,
            configurable: true,
        });

        await wrapper.get('[role="listbox"]').trigger('scroll');

        expect(getMock).toHaveBeenCalledTimes(1);
    });

    it('discards a stale response that resolves after a newer search', async () => {
        vi.useFakeTimers();
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');

        const input = wrapper.get('input[type="search"]');
        await input.setValue('old');
        await vi.advanceTimersByTimeAsync(300);

        await input.setValue('new');
        await vi.advanceTimersByTimeAsync(300);

        expect(getMock).toHaveBeenCalledTimes(3);

        // Resolve the newer ("new") request first, then the stale ("old") one.
        resolveCall(2, page([{ id: 2, name: 'New product' }]));
        await wrapper.vm.$nextTick();
        resolveCall(1, page([{ id: 1, name: 'Old product' }]));
        await wrapper.vm.$nextTick();

        const labels = wrapper
            .findAll('[role="option"] span')
            .map((el) => el.text());
        expect(labels).toEqual(['New product']);
    });

    it('keeps resolving the selected product label once learned from a search page', async () => {
        const wrapper = mountSelect({ modelValue: { id: 1, name: 'Widgets' } });

        expect(wrapper.get('button').text()).toBe('Widgets');

        await wrapper.get('button').trigger('click');
        resolveCall(0, page([{ id: 1, name: 'Widgets (renamed)' }]));
        await wrapper.vm.$nextTick();

        expect(wrapper.get('button').text()).toBe('Widgets (renamed)');
    });

    it('closes the panel when Escape is pressed', async () => {
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');
        expect(wrapper.find('[role="listbox"]').exists()).toBe(true);

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[role="listbox"]').exists()).toBe(false);
    });
});

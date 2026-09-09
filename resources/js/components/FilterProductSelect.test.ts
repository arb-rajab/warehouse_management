import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n, t } from '@/lib/i18n';
import type { Paginated, ProductFilterOption } from '@/types/admin';
import FilterProductSelect from './FilterProductSelect.vue';

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
    props: Partial<{
        selected: ProductFilterOption[];
        modelValue: string[];
    }> = {},
) {
    let modelValue = props.modelValue ?? [];

    return mount(FilterProductSelect, {
        props: {
            id: 'filter-product',
            label: 'Product',
            allLabel: 'All',
            selectedCountLabel: (count: number) => `${count} selected`,
            selected: props.selected ?? [],
            modelValue,
            'onUpdate:modelValue': (value: string[]) => {
                modelValue = value;
            },
        },
    });
}

describe('FilterProductSelect', () => {
    beforeEach(() => {
        getMock.mockClear();
    });

    afterEach(() => {
        vi.useRealTimers();
        i18n.global.locale.value = 'en';
    });

    it('shows the all label when nothing is selected', () => {
        const wrapper = mountSelect();

        expect(wrapper.get('button').text()).toBe('All');
    });

    it("resolves a single selection's label from the `selected` prop without fetching", async () => {
        const wrapper = mountSelect({
            selected: [{ id: 99, name: 'Widget', ar_name: 'ودجة' }],
            modelValue: ['99'],
        });

        expect(wrapper.get('button').text()).toBe('Widget');
        expect(getMock).not.toHaveBeenCalled();
    });

    it('shows the selected count label when more than one is selected', () => {
        const wrapper = mountSelect({
            selected: [
                { id: 1, name: 'Widget', ar_name: 'ودجة' },
                { id: 2, name: 'Gadget', ar_name: 'أداة' },
            ],
            modelValue: ['1', '2'],
        });

        expect(wrapper.get('button').text()).toBe('2 selected');
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

    it('renders fetched options and emits the selection on checkbox toggle', async () => {
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');

        resolveCall(
            0,
            page([
                { id: 1, name: 'Widgets', ar_name: 'ودجات' },
                { id: 2, name: 'Gadgets', ar_name: 'أدوات' },
            ]),
        );
        await wrapper.vm.$nextTick();

        const checkboxes = wrapper.findAll('input[type="checkbox"]');
        expect(checkboxes).toHaveLength(2);

        await checkboxes[1].setValue(true);
        expect(wrapper.emitted('update:modelValue')).toEqual([[['2']]]);
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
            page([{ id: 1, name: 'Widgets', ar_name: 'ودجات' }], {
                currentPage: 1,
                lastPage: 2,
            }),
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
            page([{ id: 2, name: 'Gadgets', ar_name: 'أدوات' }], {
                currentPage: 2,
                lastPage: 2,
            }),
        );
        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(2);
    });

    it('shows a loading indicator alongside already-rendered options while fetching the next page', async () => {
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');

        resolveCall(
            0,
            page([{ id: 1, name: 'Widgets', ar_name: 'ودجات' }], {
                currentPage: 1,
                lastPage: 2,
            }),
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

        expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(1);
        expect(wrapper.text()).toContain(t('cellLog.filters.searching'));

        resolveCall(
            1,
            page([{ id: 2, name: 'Gadgets', ar_name: 'أدوات' }], {
                currentPage: 2,
                lastPage: 2,
            }),
        );
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).not.toContain(t('cellLog.filters.searching'));
    });

    it('does not fetch another page once the last page is loaded', async () => {
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');

        resolveCall(
            0,
            page([{ id: 1, name: 'Widgets', ar_name: 'ودجات' }], {
                currentPage: 1,
                lastPage: 1,
            }),
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
        resolveCall(
            2,
            page([{ id: 2, name: 'New product', ar_name: 'منتج جديد' }]),
        );
        await wrapper.vm.$nextTick();
        resolveCall(
            1,
            page([{ id: 1, name: 'Old product', ar_name: 'منتج قديم' }]),
        );
        await wrapper.vm.$nextTick();

        const labels = wrapper
            .findAll('[role="option"] span')
            .map((el) => el.text());
        expect(labels).toEqual(['New product']);
    });

    it('keeps resolving a selected id label once learned from a search page', async () => {
        const wrapper = mountSelect({ modelValue: ['1'] });
        await wrapper.get('button').trigger('click');

        expect(wrapper.get('button').text()).toBe('All');

        resolveCall(0, page([{ id: 1, name: 'Widgets', ar_name: 'ودجات' }]));
        await wrapper.vm.$nextTick();

        expect(wrapper.get('button').text()).toBe('Widgets');
    });

    it('closes the panel when Escape is pressed', async () => {
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');
        expect(wrapper.find('[role="listbox"]').exists()).toBe(true);

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[role="listbox"]').exists()).toBe(false);
    });

    it("renders each option's Arabic name when the locale is Arabic", async () => {
        i18n.global.locale.value = 'ar';
        const wrapper = mountSelect();

        await wrapper.get('button').trigger('click');
        resolveCall(
            0,
            page([
                { id: 1, name: 'Widgets', ar_name: 'ودجات' },
                { id: 2, name: 'Gadgets', ar_name: '' },
            ]),
        );
        await wrapper.vm.$nextTick();

        const options = wrapper.findAll('[role="option"]');

        expect(options[0].text()).toBe('ودجات');
        // The store never translated this one, so it falls back to the base
        // name rather than rendering the empty `ar_name`.
        expect(options[1].text()).toBe('Gadgets');
    });

    it("renders each option's base name when the locale is English", async () => {
        const wrapper = mountSelect();

        await wrapper.get('button').trigger('click');
        resolveCall(
            0,
            page([
                { id: 1, name: 'Widgets', ar_name: 'ودجات' },
                { id: 2, name: 'Gadgets', ar_name: '' },
            ]),
        );
        await wrapper.vm.$nextTick();

        const options = wrapper.findAll('[role="option"]');

        expect(options[0].text()).toBe('Widgets');
        expect(options[1].text()).toBe('Gadgets');
    });

    it("resolves a single selection's button label for the Arabic locale", () => {
        i18n.global.locale.value = 'ar';

        const translated = mountSelect({
            selected: [{ id: 99, name: 'Widget', ar_name: 'ودجة' }],
            modelValue: ['99'],
        });
        const untranslated = mountSelect({
            selected: [{ id: 98, name: 'Gadget', ar_name: '' }],
            modelValue: ['98'],
        });

        expect(translated.get('button').text()).toBe('ودجة');
        expect(untranslated.get('button').text()).toBe('Gadget');
    });
});

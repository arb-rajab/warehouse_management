import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, ref } from 'vue';
import type { PropType } from 'vue';
import { i18n, t } from '@/lib/i18n';
import { paginated } from '@/testing/factories';
import type { Paginated, ProductFilterOption } from '@/types/admin';
import ProductOptionLabel from './ProductOptionLabel.vue';
import ProductSelect from './ProductSelect.vue';

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

function resolveCall(index: number, response: Paginated<ProductFilterOption>) {
    // A real request always calls onFinish after onSuccess (or after
    // onError), regardless of outcome.
    getMock.mock.calls[index][1]?.onSuccess?.(response);
    getMock.mock.calls[index][1]?.onFinish?.();
}

function mountSelect(
    props: Partial<{
        modelValue: ProductFilterOption | null;
        required: boolean;
    }> = {},
) {
    let modelValue = props.modelValue ?? null;

    return mount(ProductSelect, {
        props: {
            id: 'pallet-action-product',
            label: 'Product',
            placeholder: 'Choose a product',
            required: props.required,
            modelValue,
            'onUpdate:modelValue': (value: ProductFilterOption | null) => {
                modelValue = value;
            },
        },
    });
}

describe('ProductSelect', () => {
    it.each([true, false])(
        'keeps the selected product status visible on the closed trigger (active=%s)',
        async (active) => {
            const product = { id: 99, name: 'Widget', ar_name: 'ودجة', active };
            const wrapper = mountSelect({ modelValue: product });
            const trigger = wrapper.get('button');

            expect(
                trigger.get('[data-testid="product-active-badge"]').text(),
            ).toBe(
                t(
                    active
                        ? 'products.status.active'
                        : 'products.status.inactive',
                ),
            );
            expect(trigger.attributes('aria-expanded')).toBe('false');
        },
    );

    beforeEach(() => {
        getMock.mockClear();
    });

    afterEach(() => {
        vi.useRealTimers();
        i18n.global.locale.value = 'en';
    });

    it('shows the placeholder when nothing is selected', () => {
        const wrapper = mountSelect();

        expect(wrapper.get('button').text()).toBe('Choose a product');
    });

    it('shows a pointer cursor on the trigger button', () => {
        const wrapper = mountSelect();

        expect(wrapper.get('button').classes()).toContain('cursor-pointer');
    });

    it('uses a logical text-align utility on the trigger so RTL locales flip alignment', () => {
        const wrapper = mountSelect();

        expect(wrapper.get('button').classes()).toContain('text-start');
    });

    it('renders no required guard input by default', () => {
        const wrapper = mountSelect();

        expect(wrapper.find('#pallet-action-product-guard').exists()).toBe(
            false,
        );
    });

    it('renders a required guard input mirroring the selected product id when required is set', () => {
        const wrapper = mountSelect({ required: true });

        const guard = wrapper.get('#pallet-action-product-guard')
            .element as HTMLInputElement;
        expect(guard.required).toBe(true);
        expect(guard.value).toBe('');
    });

    it('keeps the required guard input in sync with the selected product', () => {
        const wrapper = mountSelect({
            required: true,
            modelValue: {
                id: 5,
                name: 'Widgets',
                ar_name: 'ودجات',
                active: true,
            },
        });

        const guard = wrapper.get('#pallet-action-product-guard')
            .element as HTMLInputElement;
        expect(guard.value).toBe('5');
    });

    function mountSelectInForm(
        props: Partial<{
            modelValue: ProductFilterOption | null;
            required: boolean;
        }> = {},
    ) {
        const Host = defineComponent({
            components: { ProductSelect },
            props: {
                modelValue: {
                    type: Object as PropType<ProductFilterOption | null>,
                    default: null,
                },
                required: { type: Boolean, default: false },
            },
            setup(hostProps) {
                const selected = ref(hostProps.modelValue);

                return { selected };
            },
            template: `<form>
                <ProductSelect
                    id="pallet-action-product"
                    label="Product"
                    placeholder="Choose a product"
                    :required="required"
                    v-model="selected"
                />
            </form>`,
        });

        return mount(Host, { props });
    }

    it('fails native form validation when required and no product is selected', () => {
        const wrapper = mountSelectInForm({ required: true });

        const form = wrapper.get('form').element as HTMLFormElement;
        expect(form.checkValidity()).toBe(false);
    });

    it('passes native form validation when required and a product is already selected', () => {
        const wrapper = mountSelectInForm({
            required: true,
            modelValue: {
                id: 5,
                name: 'Widgets',
                ar_name: 'ودجات',
                active: true,
            },
        });

        const form = wrapper.get('form').element as HTMLFormElement;
        expect(form.checkValidity()).toBe(true);
    });

    it("resolves the selected product's label from the model without fetching", () => {
        const wrapper = mountSelect({
            modelValue: {
                id: 99,
                name: 'Widget',
                ar_name: 'ودجة',
                active: true,
            },
        });

        expect(wrapper.get('button > span:first-child').text()).toBe('Widget');
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
            paginated([
                { id: 1, name: 'Widgets', ar_name: 'ودجات', active: true },
                { id: 2, name: 'Gadgets', ar_name: 'أدوات', active: true },
            ]),
        );
        await wrapper.vm.$nextTick();

        const options = wrapper.findAll('[role="option"]');
        expect(options).toHaveLength(2);

        await options[1].trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([
            [{ id: 2, name: 'Gadgets', ar_name: 'أدوات', active: true }],
        ]);
    });

    it('separates each option row from the next with a divider', async () => {
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');

        resolveCall(
            0,
            paginated([
                { id: 1, name: 'Widgets', ar_name: 'ودجات', active: true },
                { id: 2, name: 'Gadgets', ar_name: 'أدوات', active: true },
            ]),
        );
        await wrapper.vm.$nextTick();

        const options = wrapper.findAll('[role="option"]');
        expect(options[0].element.parentElement?.className).toContain(
            'divide-y',
        );
    });

    it('closes the panel after selecting an option', async () => {
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');

        resolveCall(
            0,
            paginated([
                { id: 1, name: 'Widgets', ar_name: 'ودجات', active: true },
            ]),
        );
        await wrapper.vm.$nextTick();

        await wrapper.get('[role="option"]').trigger('click');

        expect(wrapper.find('[role="listbox"]').exists()).toBe(false);
    });

    it('shows "no products found" when a search resolves empty', async () => {
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');

        resolveCall(0, paginated([]));
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
            paginated(
                [{ id: 1, name: 'Widgets', ar_name: 'ودجات', active: true }],
                20,
                {
                    currentPage: 1,
                    lastPage: 2,
                },
            ),
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
            paginated(
                [{ id: 2, name: 'Gadgets', ar_name: 'أدوات', active: true }],
                20,
                {
                    currentPage: 2,
                    lastPage: 2,
                },
            ),
        );
        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('[role="option"]')).toHaveLength(2);
    });

    it('shows a loading indicator alongside already-rendered options while fetching the next page', async () => {
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');

        resolveCall(
            0,
            paginated(
                [{ id: 1, name: 'Widgets', ar_name: 'ودجات', active: true }],
                20,
                {
                    currentPage: 1,
                    lastPage: 2,
                },
            ),
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
            paginated(
                [{ id: 2, name: 'Gadgets', ar_name: 'أدوات', active: true }],
                20,
                {
                    currentPage: 2,
                    lastPage: 2,
                },
            ),
        );
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).not.toContain(t('cellLog.filters.searching'));
    });

    it('does not fetch another page once the last page is loaded', async () => {
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');

        resolveCall(
            0,
            paginated(
                [{ id: 1, name: 'Widgets', ar_name: 'ودجات', active: true }],
                20,
                {
                    currentPage: 1,
                    lastPage: 1,
                },
            ),
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
            paginated([
                {
                    id: 2,
                    name: 'New product',
                    ar_name: 'منتج جديد',
                    active: true,
                },
            ]),
        );
        await wrapper.vm.$nextTick();
        resolveCall(
            1,
            paginated([
                {
                    id: 1,
                    name: 'Old product',
                    ar_name: 'منتج قديم',
                    active: true,
                },
            ]),
        );
        await wrapper.vm.$nextTick();

        const labels = wrapper
            .findAll('[data-testid="product-option-name"]')
            .map((el) => el.text());
        expect(labels).toEqual(['New product']);
    });

    it('keeps resolving the selected product label once learned from a search page', async () => {
        const wrapper = mountSelect({
            modelValue: {
                id: 1,
                name: 'Widgets',
                ar_name: 'ودجات',
                active: true,
            },
        });

        expect(wrapper.get('button > span:first-child').text()).toBe('Widgets');

        await wrapper.get('button').trigger('click');
        resolveCall(
            0,
            paginated([
                {
                    id: 1,
                    name: 'Widgets (renamed)',
                    ar_name: 'ودجات (معاد تسميتها)',
                    active: true,
                },
            ]),
        );
        await wrapper.vm.$nextTick();

        expect(wrapper.get('button > span:first-child').text()).toBe(
            'Widgets (renamed)',
        );
    });

    it('closes the panel when Escape is pressed', async () => {
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');
        expect(wrapper.find('[role="listbox"]').exists()).toBe(true);

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[role="listbox"]').exists()).toBe(false);
    });

    it('leads each option with its Arabic name when the locale is Arabic', async () => {
        i18n.global.locale.value = 'ar';
        const wrapper = mountSelect();

        await wrapper.get('button').trigger('click');
        resolveCall(
            0,
            paginated([
                { id: 1, name: 'Widgets', ar_name: 'ودجات', active: true },
                { id: 2, name: 'Gadgets', ar_name: '', active: true },
            ]),
        );
        await wrapper.vm.$nextTick();

        const names = wrapper
            .findAll('[data-testid="product-option-name"]')
            .map((el) => el.text());
        const alternates = wrapper
            .findAll('[data-testid="product-option-alternate-name"]')
            .map((el) => el.text());

        expect(names).toEqual(['ودجات', 'Gadgets']);
        // The search matches either column whatever the locale, so the base
        // name stays visible to explain a result matched through it. The
        // untranslated product has no second name to show.
        expect(alternates).toEqual(['Widgets']);

        const options = wrapper.findAll('[role="option"]');

        expect(options[0].text()).toContain('ودجات');
        // The store never translated this one, so it falls back to the base
        // name rather than rendering the empty `ar_name`.
        expect(options[1].text()).toContain('Gadgets');
        expect(options[1].text()).not.toContain('ودجات');
    });

    it('leads each option with its base name when the locale is English', async () => {
        const wrapper = mountSelect();

        await wrapper.get('button').trigger('click');
        resolveCall(
            0,
            paginated([
                { id: 1, name: 'Widgets', ar_name: 'ودجات', active: true },
                { id: 2, name: 'Gadgets', ar_name: '', active: true },
            ]),
        );
        await wrapper.vm.$nextTick();

        const names = wrapper
            .findAll('[data-testid="product-option-name"]')
            .map((el) => el.text());
        const alternates = wrapper
            .findAll('[data-testid="product-option-alternate-name"]')
            .map((el) => el.text());

        expect(names).toEqual(['Widgets', 'Gadgets']);
        expect(alternates).toEqual(['ودجات']);
    });

    it("resolves the selected product's button label for the Arabic locale", () => {
        i18n.global.locale.value = 'ar';

        const translated = mountSelect({
            modelValue: {
                id: 99,
                name: 'Widget',
                ar_name: 'ودجة',
                active: true,
            },
        });
        const untranslated = mountSelect({
            modelValue: { id: 98, name: 'Gadget', ar_name: '', active: true },
        });

        expect(translated.get('button > span:first-child').text()).toBe('ودجة');
        expect(untranslated.get('button > span:first-child').text()).toBe(
            'Gadget',
        );
    });

    it('hands each result to ProductOptionLabel with both raw name columns', async () => {
        const wrapper = mountSelect();

        await wrapper.get('button').trigger('click');
        resolveCall(
            0,
            paginated([
                { id: 1, name: 'Widgets', ar_name: 'ودجات', active: true },
                { id: 2, name: 'Gadgets', ar_name: '', active: true },
            ]),
        );
        await wrapper.vm.$nextTick();

        expect(
            wrapper
                .findAllComponents(ProductOptionLabel)
                .map((label) => label.props('product')),
        ).toEqual([
            { id: 1, name: 'Widgets', ar_name: 'ودجات', active: true },
            { id: 2, name: 'Gadgets', ar_name: '', active: true },
        ]);
    });
});

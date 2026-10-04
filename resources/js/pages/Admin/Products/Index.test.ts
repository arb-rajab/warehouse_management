import { Form, Head, Link } from '@inertiajs/vue3';
import { Barcode, Check, Filter, X } from '@lucide/vue';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { index as cellsIndex } from '@/actions/App/Http/Controllers/Admin/CellController';
import { show as helpShow } from '@/actions/App/Http/Controllers/Admin/HelpController';
import {
    index as productsIndex,
    updateBoxCount,
    updateMinimumPallets,
    storeBarcode,
    destroyBarcode,
    exportQr as productExportQr,
} from '@/actions/App/Http/Controllers/Admin/ProductController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { i18n, t } from '@/lib/i18n';
import { rowCells } from '@/testing/dom';
import { paginated } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type {
    ProductFilters,
    ProductIndexFilterOptions,
    ProductSummary,
} from '@/types/admin';
import Index from './Index.vue';

const { usePageMock, routerGetMock, formSlotPropsMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
    formSlotPropsMock: vi.fn(() => ({
        errors: {} as Record<string, string>,
        processing: false,
    })),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { get: routerGetMock },
        useHttp: () => ({
            get: (
                _url: string,
                options?: { onSuccess?: (response: unknown) => void },
            ) => options?.onSuccess?.(paginated(filterOptions.products, 20)),
        }),
    };
});

function product(overrides: Partial<ProductSummary> = {}): ProductSummary {
    return {
        id: 10,
        name: 'Widgets',
        ar_name: 'ودجات',
        image_url: null,
        active: true,
        boxes_count: 12,
        minimum_pallets: null,
        pallets_count: 3,
        full_cells_count: 2,
        opened_cells_count: 1,
        expired_cells_count: 0,
        expiring_soon_count: 0,
        barcodes: [],
        ...overrides,
    };
}

const filterOptions: ProductIndexFilterOptions = {
    rows: [
        { id: 1, letter: 'A' },
        { id: 2, letter: 'B' },
    ],
    maxColumnNumber: 3,
    products: [{ id: 10, name: 'Widgets', ar_name: 'ودجات' }],
    users: [{ id: 7, name: 'Jane Doe' }],
    actions: [
        'stored',
        'opened',
        'emptied',
        'transferred_out',
        'transferred_in',
    ],
};

function mountPage(
    products: ProductSummary[],
    filters: ProductFilters = {},
    overrides: {
        expiringSoonDays?: number;
    } = {},
) {
    usePageMock.mockReturnValue({
        url: productsIndex.url(),
        props: defaultAuthProps(),
    });

    return mount(Index, {
        props: {
            products: paginated(products),
            expiringSoonDays: overrides.expiringSoonDays ?? 45,
            filters,
            filterOptions,
        },
    });
}

async function openFilters(
    wrapper: ReturnType<typeof mountPage>,
): Promise<void> {
    const trigger = wrapper
        .findAll('button')
        .find((button) => button.text().includes(t('products.filters.title')));
    await trigger?.trigger('click');
}

describe('Products Index', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, routerGetMock, formSlotPropsMock });
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });

    afterEach(() => {
        i18n.global.locale.value = 'en';
    });

    it('renders the page title in the Head and the PageHeader', () => {
        const wrapper = mountPage([]);

        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('products.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('products.title'));
    });

    it('renders every column header', () => {
        const wrapper = mountPage([]);

        const headers = wrapper.findAll('thead th').map((th) => th.text());
        expect(headers).toEqual([
            t('products.columns.product'),
            t('products.columns.boxesPerPallet'),
            t('products.columns.minimumPallets'),
            t('products.columns.barcodes'),
            t('products.columns.full'),
            t('products.columns.opened'),
            t('products.columns.expired'),
            t('products.columns.expiringSoon', { days: 45 }),
            t('products.columns.qr'),
        ]);
    });

    it('shows the empty message when there are no products', () => {
        const wrapper = mountPage([]);

        expect(wrapper.text()).toContain(t('products.empty'));
    });

    it('links the help icon to the products help page', () => {
        const wrapper = mountPage([]);

        const helpLink = wrapper
            .findAll('a')
            .find((a) => a.attributes('aria-label') === t('help.viewHelp'));
        expect(helpLink?.attributes('href')).toBe(
            helpShow.url({ topic: 'products' }),
        );
    });

    it('renders the quick product search in the toolbar, without opening the filter dialog', () => {
        const wrapper = mountPage([]);

        expect(wrapper.get('label[for="products-quick-search"]').text()).toBe(
            t('products.quickSearch.label'),
        );
        expect(wrapper.get('#products-quick-search').text()).toBe(
            t('products.quickSearch.placeholder'),
        );
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('renders the quick inactive checkbox in the toolbar, without opening the filter dialog', () => {
        const wrapper = mountPage([]);

        expect(wrapper.get('label[for="products-quick-inactive"]').text()).toBe(
            t('products.filters.inactive'),
        );
        expect(
            (
                wrapper.get('#products-quick-inactive')
                    .element as HTMLInputElement
            ).checked,
        ).toBe(false);
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('checks the quick inactive checkbox when the inactive filter is already applied', () => {
        const wrapper = mountPage([], { inactive: true });

        expect(
            (
                wrapper.get('#products-quick-inactive')
                    .element as HTMLInputElement
            ).checked,
        ).toBe(true);
    });

    it('immediately requests inactive products when the toolbar checkbox is checked, without opening the filter dialog', async () => {
        const wrapper = mountPage([]);

        await wrapper.get('#products-quick-inactive').setValue(true);

        expect(routerGetMock).toHaveBeenCalledWith(
            productsIndex.url(),
            expect.objectContaining({ inactive: true }),
            { preserveState: true, replace: true },
        );
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('immediately omits inactive when the toolbar checkbox is unchecked again', async () => {
        const wrapper = mountPage([], { inactive: true });

        await wrapper.get('#products-quick-inactive').setValue(false);

        const [, sentFilters] = routerGetMock.mock.calls[0];
        expect(sentFilters).not.toHaveProperty('inactive');
    });

    it('renders the quick low-stock checkbox in the toolbar, without opening the filter dialog', () => {
        const wrapper = mountPage([]);

        expect(
            wrapper.get('label[for="products-quick-low-stock"]').text(),
        ).toBe(t('products.filters.lowStock'));
        expect(
            (
                wrapper.get('#products-quick-low-stock')
                    .element as HTMLInputElement
            ).checked,
        ).toBe(false);
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('checks the quick low-stock checkbox when the low_stock filter is already applied', () => {
        const wrapper = mountPage([], { low_stock: true });

        expect(
            (
                wrapper.get('#products-quick-low-stock')
                    .element as HTMLInputElement
            ).checked,
        ).toBe(true);
    });

    it('immediately requests low-stock products when the toolbar checkbox is checked, without opening the filter dialog', async () => {
        const wrapper = mountPage([]);

        await wrapper.get('#products-quick-low-stock').setValue(true);

        expect(routerGetMock).toHaveBeenCalledWith(
            productsIndex.url(),
            expect.objectContaining({ low_stock: true }),
            { preserveState: true, replace: true },
        );
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('immediately omits low_stock when the toolbar checkbox is unchecked again', async () => {
        const wrapper = mountPage([], { low_stock: true });

        await wrapper.get('#products-quick-low-stock').setValue(false);

        const [, sentFilters] = routerGetMock.mock.calls[0];
        expect(sentFilters).not.toHaveProperty('low_stock');
    });

    it('narrows the table to the picked product and requests it via product_id when a quick search result is selected', async () => {
        const wrapper = mountPage([]);

        await wrapper.get('#products-quick-search').trigger('click');
        await wrapper
            .get('[role="listbox"]')
            .get('[role="option"]')
            .trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith(
            productsIndex.url(),
            expect.objectContaining({ product_id: ['10'] }),
            { preserveState: true, replace: true },
        );
    });

    it('resets the quick search box back to its placeholder after a selection is submitted', async () => {
        const wrapper = mountPage([]);

        await wrapper.get('#products-quick-search').trigger('click');
        await wrapper
            .get('[role="listbox"]')
            .get('[role="option"]')
            .trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.get('#products-quick-search').text()).toBe(
            t('products.quickSearch.placeholder'),
        );
    });

    it('hides the filter fields until the Filters button is clicked', () => {
        const wrapper = mountPage([]);

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(wrapper.find('#filter-product').exists()).toBe(false);
    });

    it('opens the filter dialog when the Filters button is clicked', async () => {
        const wrapper = mountPage([]);

        await openFilters(wrapper);

        expect(wrapper.get('[role="dialog"]').text()).toContain(
            t('products.filters.title'),
        );
        expect(wrapper.find('#filter-product').exists()).toBe(true);
    });

    it('groups the filter fields under section headings', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        const dialog = wrapper.get('[role="dialog"]');
        expect(dialog.text()).toContain(
            t('products.filters.sections.occupancy'),
        );
        expect(dialog.text()).toContain(t('cellLog.filters.sections.activity'));
    });

    it('closes the filter dialog after Apply is clicked', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        const applyButton = wrapper
            .findAll('button')
            .find((button) =>
                button.text().includes(t('cellLog.filters.apply')),
            );
        expect(applyButton?.findComponent(Check).exists()).toBe(true);

        await wrapper.get('form').trigger('submit');

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('does not show a filter count badge when no filters are active', () => {
        const wrapper = mountPage([]);

        const trigger = wrapper
            .findAll('button')
            .find((button) =>
                button.text().includes(t('products.filters.title')),
            );
        expect(trigger?.find('span').exists()).toBe(false);
    });

    it('shows a filter count badge for each distinct active filter', () => {
        const wrapper = mountPage([], {
            product_id: [10],
            action: ['opened'],
            date_from: '2026-08-01',
        });

        const trigger = wrapper
            .findAll('button')
            .find((button) =>
                button.text().includes(t('products.filters.title')),
            );
        expect(trigger?.get('span').text()).toBe('3');
    });

    it("populates the state filter select's options, without an empty option since an empty cell can't hold a product", async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        expect(
            wrapper
                .get('#filter-state')
                .findAll('option')
                .map((o) => o.text()),
        ).toEqual([
            t('cellLog.filters.all'),
            t('cellLog.states.full'),
            t('cellLog.states.opened'),
        ]);
    });

    async function checkboxLabels(
        wrapper: ReturnType<typeof mountPage>,
        toggleId: string,
    ): Promise<string[]> {
        await wrapper.get(toggleId).trigger('click');

        return wrapper
            .get('[role="listbox"]')
            .findAll('label')
            .map((label) => label.text());
    }

    it("populates the product filter's checkboxes from filterOptions", async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);
        await wrapper.get('#filter-product').trigger('click');

        // Unlike the other filters, a product option renders two lines — the
        // locale's label and the store's other name, since the search matches
        // either column (see .ai/rules/shared-database.md). Assert the primary
        // line rather than the option's whole text.
        expect(
            wrapper
                .get('[role="listbox"]')
                .findAll('[data-testid="product-option-name"]')
                .map((name) => name.text()),
        ).toEqual(['Widgets']);
        expect(
            wrapper
                .get('[role="listbox"]')
                .findAll('[data-testid="product-option-alternate-name"]')
                .map((name) => name.text()),
        ).toEqual(['ودجات']);
    });

    it("populates the user filter's checkboxes from filterOptions", async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        expect(await checkboxLabels(wrapper, '#filter-user')).toEqual([
            'Jane Doe',
        ]);
    });

    it("populates the status filter's checkboxes from filterOptions", async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        expect(await checkboxLabels(wrapper, '#filter-action')).toEqual([
            t('cellLog.actions.stored'),
            t('cellLog.actions.opened'),
            t('cellLog.actions.emptied'),
            t('cellLog.actions.transferred_out'),
            t('cellLog.actions.transferred_in'),
        ]);
    });

    it('renders the product name and image', () => {
        const wrapper = mountPage([
            product({
                name: 'Widgets',
                ar_name: 'ودجات',
                image_url: '/img/widgets.png',
            }),
        ]);

        const productCell = rowCells(wrapper)[0];
        expect(productCell.text()).toContain('Widgets');
        const img = productCell.get('img');
        expect(img.attributes('src')).toBe('/img/widgets.png');
        expect(img.attributes('alt')).toBe('Widgets');
    });

    it('does not render an image when the product has none', () => {
        const wrapper = mountPage([product({ image_url: null })]);

        expect(rowCells(wrapper)[0].find('img').exists()).toBe(false);
    });

    it("renders the product's stored box count as a clickable trigger button", () => {
        const wrapper = mountPage([
            product({ name: 'Widgets', ar_name: 'ودجات', boxes_count: 24 }),
        ]);

        const button = rowCells(wrapper)[1].get('button');
        expect(button.text()).toBe('24');
        expect(button.attributes('aria-label')).toBe(
            t('products.boxesPerPalletLabel', { product: 'Widgets' }),
        );
        // No bare number input in the cell — changes must go through the dialog.
        expect(rowCells(wrapper)[1].find('input').exists()).toBe(false);
    });

    it('offers no column filter on the box count, unlike every other column', () => {
        const wrapper = mountPage([]);

        const header = wrapper.findAll('thead th')[1];
        expect(header.text()).toBe(t('products.columns.boxesPerPallet'));
        expect(header.find('button[title]').exists()).toBe(false);
    });

    it('offers no column filter on the minimum-pallets threshold either', () => {
        const wrapper = mountPage([]);

        const header = wrapper.findAll('thead th')[2];
        expect(header.text()).toBe(t('products.columns.minimumPallets'));
        expect(header.find('button[title]').exists()).toBe(false);
    });

    it("opens the box-count dialog pre-seeded with the product's stored count", async () => {
        const wrapper = mountPage([product({ boxes_count: 24 })]);

        await rowCells(wrapper)[1].get('button').trigger('click');

        expect(
            (wrapper.get('#products-box-count').element as HTMLInputElement)
                .value,
        ).toBe('24');
    });

    it('posts the box count to the product’s route, preserving the current page and filters', async () => {
        window.history.pushState(
            {},
            '',
            productsIndex.url({ query: { state: 'full', per_page: '20' } }),
        );

        const wrapper = mountPage([product({ id: 42, boxes_count: 12 })]);

        await rowCells(wrapper)[1].get('button').trigger('click');

        const form = wrapper.getComponent(Form);
        expect(form.props('action')).toEqual({
            url: updateBoxCount.url(
                { product: 42 },
                { query: { state: 'full', per_page: '20' } },
            ),
            method: 'put',
        });
        expect(form.props('options')).toEqual({
            preserveScroll: true,
            preserveState: true,
        });

        window.history.pushState({}, '', '/');
    });

    it('names and constrains the box-count field so the form submits and validates it', async () => {
        const wrapper = mountPage([product({ boxes_count: 12 })]);

        await rowCells(wrapper)[1].get('button').trigger('click');

        const input = wrapper.get('#products-box-count');
        expect(input.attributes('name')).toBe('boxes_count');
        expect(input.attributes('required')).toBeDefined();
        expect(input.attributes('min')).toBe('1');
        expect(input.attributes('step')).toBe('1');
    });

    it("renders the product's stored minimum-pallets threshold as a clickable trigger button, or a dash when unconfigured", () => {
        const wrapper = mountPage([
            product({ minimum_pallets: 10 }),
            product({ id: 11, minimum_pallets: null }),
        ]);

        expect(rowCells(wrapper, 0)[2].get('button').text()).toBe('10');
        expect(rowCells(wrapper, 1)[2].get('button').text()).toBe('—');
    });

    it('flags the minimum-pallets trigger button when the current pallet count is below the threshold', () => {
        const wrapper = mountPage([
            product({ id: 42, minimum_pallets: 5, pallets_count: 2 }),
            product({ id: 43, minimum_pallets: 5, pallets_count: 5 }),
        ]);

        expect(rowCells(wrapper, 0)[2].get('button').classes()).toContain(
            'text-red-600',
        );
        expect(rowCells(wrapper, 1)[2].get('button').classes()).not.toContain(
            'text-red-600',
        );
    });

    it("opens the minimum-pallets dialog pre-seeded with the product's stored threshold, or empty when unconfigured", async () => {
        const wrapper = mountPage([product({ minimum_pallets: 10 })]);

        await rowCells(wrapper)[2].get('button').trigger('click');

        expect(
            (
                wrapper.get('#products-minimum-pallets')
                    .element as HTMLInputElement
            ).value,
        ).toBe('10');
    });

    it('posts the minimum-pallets threshold to the product’s route, preserving the current page and filters', async () => {
        window.history.pushState(
            {},
            '',
            productsIndex.url({ query: { state: 'full', per_page: '20' } }),
        );

        const wrapper = mountPage([product({ id: 42, minimum_pallets: 5 })]);

        await rowCells(wrapper)[2].get('button').trigger('click');

        const form = wrapper.getComponent(Form);
        expect(form.props('action')).toEqual({
            url: updateMinimumPallets.url(
                { product: 42 },
                { query: { state: 'full', per_page: '20' } },
            ),
            method: 'put',
        });
        expect(form.props('options')).toEqual({
            preserveScroll: true,
            preserveState: true,
        });

        window.history.pushState({}, '', '/');
    });

    it('names the minimum-pallets field, and leaves it optional so an emptied field clears the threshold', async () => {
        const wrapper = mountPage([product({ minimum_pallets: 10 })]);

        await rowCells(wrapper)[2].get('button').trigger('click');

        const input = wrapper.get('#products-minimum-pallets');
        expect(input.attributes('name')).toBe('minimum_pallets');
        expect(input.attributes('required')).toBeUndefined();
        expect(input.attributes('min')).toBe('1');
    });

    it('renders the barcode count as a clickable trigger button', () => {
        const wrapper = mountPage([
            product({
                name: 'Widgets',
                ar_name: 'ودجات',
                barcodes: [{ id: 1, barcode: '1234567890123' }],
            }),
        ]);

        const button = rowCells(wrapper)[3].get('button');
        expect(button.text()).toBe('1');
        expect(button.attributes('aria-label')).toBe(
            t('products.barcodesLabel', { product: 'Widgets' }),
        );
    });

    it('offers no column filter on the barcodes column either', () => {
        const wrapper = mountPage([]);

        const header = wrapper.findAll('thead th')[3];
        expect(header.text()).toBe(t('products.columns.barcodes'));
        expect(header.find('button[title]').exists()).toBe(false);
    });

    it('opens the barcodes dialog listing every barcode attached to the product', async () => {
        const wrapper = mountPage([
            product({
                barcodes: [
                    { id: 1, barcode: '1111111111111' },
                    { id: 2, barcode: '2222222222222' },
                ],
            }),
        ]);

        await rowCells(wrapper)[3].get('button').trigger('click');

        const items = wrapper.findAll('li');
        expect(items).toHaveLength(2);
        expect(items[0].text()).toContain('1111111111111');
        expect(items[1].text()).toContain('2222222222222');
    });

    it('shows an empty-state message in the barcodes dialog when the product has none', async () => {
        const wrapper = mountPage([product({ barcodes: [] })]);

        await rowCells(wrapper)[3].get('button').trigger('click');

        expect(wrapper.text()).toContain(t('products.noBarcodes'));
    });

    it('posts a new barcode to the product’s route, preserving the current page and filters', async () => {
        window.history.pushState(
            {},
            '',
            productsIndex.url({ query: { state: 'full', per_page: '20' } }),
        );

        const wrapper = mountPage([product({ id: 42, barcodes: [] })]);

        await rowCells(wrapper)[3].get('button').trigger('click');

        const form = wrapper.getComponent(Form);
        expect(form.props('action')).toEqual({
            url: storeBarcode.url(
                { product: 42 },
                { query: { state: 'full', per_page: '20' } },
            ),
            method: 'post',
        });
        expect(form.props('options')).toEqual({
            preserveScroll: true,
            preserveState: true,
        });

        window.history.pushState({}, '', '/');
    });

    it('names and requires the barcode field, and clears it once the barcode is added', async () => {
        const wrapper = mountPage([product({ id: 42, barcodes: [] })]);

        await rowCells(wrapper)[3].get('button').trigger('click');

        const input = wrapper.get('#products-new-barcode');
        expect(input.attributes('name')).toBe('barcode');
        expect(input.attributes('required')).toBeDefined();
        expect(wrapper.getComponent(Form).props('resetOnSuccess')).toBe(true);
    });

    it('re-points the barcodes dialog at the updated product once adding succeeds', async () => {
        const wrapper = mountPage([product({ id: 42, barcodes: [] })]);

        await rowCells(wrapper)[3].get('button').trigger('click');
        expect(wrapper.text()).toContain(t('products.noBarcodes'));

        await wrapper.setProps({
            products: paginated([
                product({
                    id: 42,
                    barcodes: [{ id: 9, barcode: '9999999999999' }],
                }),
            ]),
        });
        wrapper.getComponent(Form).vm.$emit('success');
        await flushPromises();

        const items = wrapper.findAll('li');
        expect(items).toHaveLength(1);
        expect(items[0].text()).toContain('9999999999999');
    });

    describe('removing a barcode', () => {
        const removeLabel = t('products.removeBarcode', {
            barcode: '1234567890123',
        });

        function mountWithBarcode() {
            return mountPage([
                product({
                    id: 42,
                    barcodes: [{ id: 7, barcode: '1234567890123' }],
                }),
            ]);
        }

        function removeLink(wrapper: ReturnType<typeof mountPage>) {
            return wrapper
                .findAllComponents(Link)
                .find((link) => link.attributes('aria-label') === removeLabel)!;
        }

        afterEach(() => {
            vi.unstubAllGlobals();
        });

        it('deletes through the barcode’s route as a button, preserving the current page and filters', async () => {
            window.history.pushState(
                {},
                '',
                productsIndex.url({ query: { state: 'full', per_page: '20' } }),
            );
            const wrapper = mountWithBarcode();

            await rowCells(wrapper)[3].get('button').trigger('click');

            const link = removeLink(wrapper);
            expect(link.props('href')).toEqual({
                url: destroyBarcode.url(
                    { product: 42, barcode: 7 },
                    { query: { state: 'full', per_page: '20' } },
                ),
                method: 'delete',
            });
            expect(link.props('as')).toBe('button');
            expect(link.props('preserveScroll')).toBe(true);
            expect(link.props('preserveState')).toBe(true);

            window.history.pushState({}, '', '/');
        });

        it('asks for the shared delete confirmation before removing', async () => {
            const confirmMock = vi.fn(() => false);
            vi.stubGlobal('confirm', confirmMock);
            const wrapper = mountWithBarcode();

            await rowCells(wrapper)[3].get('button').trigger('click');

            const onBefore = removeLink(wrapper).props(
                'onBefore',
            ) as () => boolean;
            expect(onBefore()).toBe(false);
            expect(confirmMock).toHaveBeenCalledWith(
                t('common.confirmDelete', { label: '1234567890123' }),
            );

            confirmMock.mockReturnValue(true);
            expect(onBefore()).toBe(true);
        });

        it('re-points the dialog at the updated product once removal succeeds', async () => {
            const wrapper = mountWithBarcode();

            await rowCells(wrapper)[3].get('button').trigger('click');
            await wrapper.setProps({
                products: paginated([product({ id: 42, barcodes: [] })]),
            });
            (removeLink(wrapper).props('onSuccess') as () => void)();
            await flushPromises();

            expect(wrapper.findAll('li')).toHaveLength(0);
            expect(wrapper.text()).toContain(t('products.noBarcodes'));
        });
    });

    describe('save feedback', () => {
        it('closes the box-count dialog only once the form succeeds', async () => {
            const wrapper = mountPage([product({ boxes_count: 12 })]);

            await rowCells(wrapper)[1].get('button').trigger('click');
            expect(wrapper.find('#products-box-count').exists()).toBe(true);

            wrapper.getComponent(Form).vm.$emit('success');
            await flushPromises();

            expect(wrapper.find('#products-box-count').exists()).toBe(false);
        });

        it('closes the minimum-pallets dialog only once the form succeeds', async () => {
            const wrapper = mountPage([product({ minimum_pallets: 5 })]);

            await rowCells(wrapper)[2].get('button').trigger('click');
            expect(wrapper.find('#products-minimum-pallets').exists()).toBe(
                true,
            );

            wrapper.getComponent(Form).vm.$emit('success');
            await flushPromises();

            expect(wrapper.find('#products-minimum-pallets').exists()).toBe(
                false,
            );
        });

        it('passes the form’s processing state to the box-count submit button', async () => {
            formSlotPropsMock.mockReturnValue({ errors: {}, processing: true });
            const wrapper = mountPage([product({ boxes_count: 12 })]);

            await rowCells(wrapper)[1].get('button').trigger('click');

            const submit = wrapper.getComponent(SubmitButton);
            expect(submit.props('processing')).toBe(true);
            expect(submit.props('processingLabel')).toBe(t('products.saving'));
        });

        it('passes the form’s processing state to the minimum-pallets submit button', async () => {
            formSlotPropsMock.mockReturnValue({ errors: {}, processing: true });
            const wrapper = mountPage([product({ minimum_pallets: 5 })]);

            await rowCells(wrapper)[2].get('button').trigger('click');

            expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(
                true,
            );
        });

        it('disables the add-barcode button while the form is processing', async () => {
            formSlotPropsMock.mockReturnValue({ errors: {}, processing: true });
            const wrapper = mountPage([product({ id: 42, barcodes: [] })]);

            await rowCells(wrapper)[3].get('button').trigger('click');

            expect(
                wrapper
                    .get('#products-new-barcode')
                    .element.closest('form')!
                    .querySelector('button[type="submit"]')!
                    .hasAttribute('disabled'),
            ).toBe(true);
        });

        it.each([
            ['box-count', 1, { boxes_count: 'The boxes count is too large.' }],
            ['minimum-pallets', 2, { minimum_pallets: 'Must be at most 500.' }],
            ['barcodes', 3, { barcode: 'Barcode already taken.' }],
        ])(
            'shows the %s form’s server error in a banner',
            async (_dialog, column, errors) => {
                formSlotPropsMock.mockReturnValue({
                    errors,
                    processing: false,
                });
                const wrapper = mountPage([product({ barcodes: [] })]);

                await rowCells(wrapper)[column].get('button').trigger('click');

                expect(
                    wrapper.getComponent(ActionErrorBanner).props('message'),
                ).toBe(Object.values(errors)[0]);
            },
        );

        it('shows no error banner message when the form has none', async () => {
            const wrapper = mountPage([product({ boxes_count: 12 })]);

            await rowCells(wrapper)[1].get('button').trigger('click');

            expect(
                wrapper.getComponent(ActionErrorBanner).props('message'),
            ).toBeUndefined();
        });
    });

    it('labels the filters button and dialog with the products filter strings', async () => {
        const wrapper = mountPage([]);

        await openFilters(wrapper);

        expect(t('products.filters.title')).toBe('Filters');
        expect(wrapper.text()).toContain(t('products.filters.title'));
        expect(
            wrapper
                .find(`[aria-label="${t('products.filters.close')}"]`)
                .exists(),
        ).toBe(true);
    });

    it('renders the Arabic filters title and close label', async () => {
        i18n.global.locale.value = 'ar';
        const wrapper = mountPage([]);

        await openFilters(wrapper);

        expect(wrapper.text()).toContain('الفلاتر');
        expect(wrapper.find('[aria-label="إغلاق الفلاتر"]').exists()).toBe(
            true,
        );
    });

    it('renders the full count linking to the map filtered to this product and state=full', () => {
        const wrapper = mountPage([product({ id: 42, full_cells_count: 3 })]);

        const cell = rowCells(wrapper)[4];
        expect(cell.text()).toBe('3');
        const href = cell.get('a').attributes('href') ?? '';
        expect(href).toContain(cellsIndex.url());
        expect(href).toContain('product_id%5B%5D=42');
        expect(href).toContain('state=full');
    });

    it('renders the opened count linking to the map filtered to this product and state=opened', () => {
        const wrapper = mountPage([product({ id: 42, opened_cells_count: 2 })]);

        const cell = rowCells(wrapper)[5];
        expect(cell.text()).toBe('2');
        const href = cell.get('a').attributes('href') ?? '';
        expect(href).toContain(cellsIndex.url());
        expect(href).toContain('state=opened');
    });

    it('renders the expired count linking to the map filtered to this product and expired=true', () => {
        const wrapper = mountPage([
            product({ id: 42, expired_cells_count: 1 }),
        ]);

        const cell = rowCells(wrapper)[6];
        expect(cell.text()).toBe('1');
        const href = cell.get('a').attributes('href') ?? '';
        expect(href).toContain(cellsIndex.url());
        expect(href).toContain('expired=1');
    });

    it('renders the expiring-soon count linking to the map with the resolved day window', () => {
        const wrapper = mountPage(
            [product({ id: 42, expiring_soon_count: 5 })],
            {},
            { expiringSoonDays: 30 },
        );

        const cell = rowCells(wrapper)[7];
        expect(cell.text()).toBe('5');
        const href = cell.get('a').attributes('href') ?? '';
        expect(href).toContain(cellsIndex.url());
        expect(href).toContain('expires_within_days=30');
    });

    it('renders a barcode export link for the product', () => {
        const wrapper = mountPage([product({ id: 42, name: 'Widgets' })]);

        const cell = rowCells(wrapper)[8];
        const link = cell.get('a');
        expect(link.attributes('href')).toBe(
            productExportQr.url({ product: 42 }),
        );
        expect(link.attributes('aria-label')).toBe(
            t('products.exportQrLabel', { product: 'Widgets' }),
        );
        expect(link.attributes('title')).toBe(t('products.columns.qr'));
        expect(link.findComponent(Barcode).exists()).toBe(true);
    });

    it('requests the current filter values when the filter form is submitted', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('#filter-state').setValue('full');
        await wrapper.get('#filter-expires-within-days').setValue('7');
        await wrapper.get('form').trigger('submit');

        expect(routerGetMock).toHaveBeenCalledWith(
            productsIndex.url(),
            expect.objectContaining({
                state: 'full',
                expires_within_days: 7,
            }),
            { preserveState: true, replace: true },
        );
    });

    it('includes expired only when the checkbox is checked', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('#filter-expired').setValue(true);
        await wrapper.get('form').trigger('submit');

        expect(routerGetMock).toHaveBeenCalledWith(
            productsIndex.url(),
            expect.objectContaining({ expired: true }),
            { preserveState: true, replace: true },
        );
    });

    it('omits expired entirely when the checkbox is left unchecked', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('form').trigger('submit');

        const [, sentFilters] = routerGetMock.mock.calls[0];
        expect(sentFilters).not.toHaveProperty('expired');
    });

    it('includes inactive only when the checkbox is checked', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('#filter-inactive').setValue(true);
        await wrapper.get('form').trigger('submit');

        expect(routerGetMock).toHaveBeenCalledWith(
            productsIndex.url(),
            expect.objectContaining({ inactive: true }),
            { preserveState: true, replace: true },
        );
    });

    it('omits inactive entirely when the checkbox is left unchecked', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('form').trigger('submit');

        const [, sentFilters] = routerGetMock.mock.calls[0];
        expect(sentFilters).not.toHaveProperty('inactive');
    });

    it('sets the expires_within_days field to a quick-pick day count when clicked', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        const quickPick = wrapper
            .findAll('button')
            .find((button) => button.text() === '30');
        await quickPick?.trigger('click');

        expect(
            (
                wrapper.get('#filter-expires-within-days')
                    .element as HTMLInputElement
            ).value,
        ).toBe('30');
    });

    function columnHeader(
        wrapper: ReturnType<typeof mountPage>,
        label: string,
    ) {
        const header = wrapper
            .findAll('thead th')
            .find((th) => th.text().includes(label));

        if (!header) {
            throw new Error(`Column header "${label}" not found`);
        }

        return header;
    }

    function isColumnActive(
        wrapper: ReturnType<typeof mountPage>,
        label: string,
    ): boolean {
        return columnHeader(wrapper, label)
            .get('button[title]')
            .classes()
            .includes('text-blue-600');
    }

    it("shows the primary column's filter icon in each shared group, none active, when no filters are set", () => {
        const wrapper = mountPage([]);

        for (const label of [
            t('products.columns.product'),
            t('products.columns.full'),
        ]) {
            expect(
                columnHeader(wrapper, label).findComponent(Filter).exists(),
            ).toBe(true);
            expect(isColumnActive(wrapper, label)).toBe(false);
        }
    });

    it("hides the redundant sibling columns' filter icons when no filters are set", () => {
        const wrapper = mountPage([]);

        for (const label of [
            t('products.columns.opened'),
            t('products.columns.expired'),
            t('products.columns.expiringSoon', { days: 45 }),
        ]) {
            expect(
                columnHeader(wrapper, label).findComponent(Filter).exists(),
            ).toBe(false);
        }
    });

    it("reveals a redundant sibling column's icon once a filter affecting it is applied", () => {
        const wrapper = mountPage([], { state: 'full' });

        expect(
            columnHeader(wrapper, t('products.columns.opened'))
                .findComponent(Filter)
                .exists(),
        ).toBe(true);
        expect(isColumnActive(wrapper, t('products.columns.opened'))).toBe(
            true,
        );
    });

    it("can still open the shared occupancy popover from a revealed sibling column's icon", async () => {
        const wrapper = mountPage([], { state: 'full' });

        await columnHeader(wrapper, t('products.columns.opened'))
            .get('button[title]')
            .trigger('click');

        expect(wrapper.find('#popover-filter-state').exists()).toBe(true);
    });

    it('marks the product column active when a product filter is applied', () => {
        const wrapper = mountPage([], { product_id: [10] });

        expect(isColumnActive(wrapper, t('products.columns.product'))).toBe(
            true,
        );
        expect(isColumnActive(wrapper, t('products.columns.full'))).toBe(false);
    });

    it('marks every occupancy column active when the state filter is applied', () => {
        const wrapper = mountPage([], { state: 'full' });

        for (const label of [
            t('products.columns.full'),
            t('products.columns.opened'),
            t('products.columns.expired'),
            t('products.columns.expiringSoon', { days: 45 }),
        ]) {
            expect(isColumnActive(wrapper, label)).toBe(true);
        }
    });

    it('marks the product column (not the occupancy columns) active when the inactive filter is applied', () => {
        // `inactive` restricts which product rows appear (the store admin's
        // `published` flag) — it doesn't narrow what counts as
        // full/opened/expired for a shown row, so it behaves like
        // `product_id` rather than like `state`/`expired`. See
        // .ai/rules/products.md.
        const wrapper = mountPage([], { inactive: true });

        expect(isColumnActive(wrapper, t('products.columns.product'))).toBe(
            true,
        );
        expect(isColumnActive(wrapper, t('products.columns.full'))).toBe(false);
    });

    it('marks the product column (not the occupancy columns) active when the low_stock filter is applied', () => {
        const wrapper = mountPage([], { low_stock: true });

        expect(isColumnActive(wrapper, t('products.columns.product'))).toBe(
            true,
        );
        expect(isColumnActive(wrapper, t('products.columns.full'))).toBe(false);
    });

    async function openColumnPopover(
        wrapper: ReturnType<typeof mountPage>,
        label: string,
    ): Promise<void> {
        await columnHeader(wrapper, label)
            .get('button[title]')
            .trigger('click');
    }

    it('opens the occupancy popover with the state/expired/expiring fields when the Full column icon is clicked', async () => {
        const wrapper = mountPage([]);

        await openColumnPopover(wrapper, t('products.columns.full'));

        expect(wrapper.find('#popover-filter-state').exists()).toBe(true);
        expect(wrapper.find('#popover-filter-expired').exists()).toBe(true);
        expect(
            wrapper.find('#popover-filter-expires-within-days').exists(),
        ).toBe(true);
        expect(wrapper.find('#popover-filter-action').exists()).toBe(false);
    });

    it('opens the product popover with the product select when the Product column icon is clicked', async () => {
        const wrapper = mountPage([]);

        await openColumnPopover(wrapper, t('products.columns.product'));

        expect(wrapper.find('#popover-filter-product').exists()).toBe(true);
        expect(wrapper.find('#popover-filter-state').exists()).toBe(false);
    });

    it('closes one popover and opens another when a different column icon is clicked', async () => {
        const wrapper = mountPage([]);

        await openColumnPopover(wrapper, t('products.columns.product'));
        expect(wrapper.find('#popover-filter-product').exists()).toBe(true);

        await openColumnPopover(wrapper, t('products.columns.full'));
        expect(wrapper.find('#popover-filter-product').exists()).toBe(false);
        expect(wrapper.find('#popover-filter-state').exists()).toBe(true);
    });

    it('auto-applies, debounced, when a field is changed inside an open column popover', async () => {
        vi.useFakeTimers();
        const wrapper = mountPage([]);

        await openColumnPopover(wrapper, t('products.columns.full'));
        await wrapper.get('#popover-filter-state').setValue('opened');

        expect(routerGetMock).not.toHaveBeenCalled();

        vi.advanceTimersByTime(400);

        expect(routerGetMock).toHaveBeenCalledWith(
            productsIndex.url(),
            expect.objectContaining({ state: 'opened' }),
            { preserveState: true, replace: true },
        );
        vi.useRealTimers();
    });

    it('does not auto-apply a change made in the main Filters dialog', async () => {
        vi.useFakeTimers();
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('#filter-state').setValue('opened');
        vi.advanceTimersByTime(400);

        expect(routerGetMock).not.toHaveBeenCalled();
        vi.useRealTimers();
    });

    it('requests the selected products when the product filter is submitted', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('#filter-product').trigger('click');
        await wrapper
            .get('[role="listbox"]')
            .findAll('input[type="checkbox"]')[0]
            .setValue(true);
        await wrapper.get('form').trigger('submit');

        expect(routerGetMock).toHaveBeenCalledWith(
            productsIndex.url(),
            expect.objectContaining({ product_id: ['10'] }),
            { preserveState: true, replace: true },
        );
    });

    it('requests the selected users when the user filter is submitted', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('#filter-user').trigger('click');
        await wrapper
            .get('[role="listbox"]')
            .findAll('input[type="checkbox"]')[0]
            .setValue(true);
        await wrapper.get('form').trigger('submit');

        expect(routerGetMock).toHaveBeenCalledWith(
            productsIndex.url(),
            expect.objectContaining({ user_id: ['7'] }),
            { preserveState: true, replace: true },
        );
    });

    it('resets every filter field and reloads the unfiltered list when Clear is clicked', async () => {
        const wrapper = mountPage([], {
            state: 'full',
            expired: true,
            inactive: true,
            product_id: [10],
            user_id: [7],
            action: ['opened'],
            date_from: '2026-08-01',
        });
        await openFilters(wrapper);

        const clearButton = wrapper
            .findAll('button')
            .find((button) => button.text() === t('cellLog.filters.clear'));
        expect(clearButton?.findComponent(X).exists()).toBe(true);
        await clearButton?.trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith(
            productsIndex.url(),
            { per_page: 20 },
            { preserveState: true, replace: true },
        );
        expect(
            (wrapper.get('#filter-state').element as HTMLSelectElement).value,
        ).toBe('');
        expect(
            (wrapper.get('#filter-expired').element as HTMLInputElement)
                .checked,
        ).toBe(false);
        expect(wrapper.get('#filter-product').text()).toBe(
            t('cellLog.filters.all'),
        );
        expect(wrapper.get('#filter-user').text()).toBe(
            t('cellLog.filters.all'),
        );
        expect(wrapper.get('#filter-action').text()).toBe(
            t('cellLog.filters.all'),
        );
    });

    it('disables the created_within_days field once a date range value is entered', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        expect(
            (
                wrapper.get('#filter-created-within-days')
                    .element as HTMLInputElement
            ).disabled,
        ).toBe(false);

        await wrapper.get('#filter-date-from').setValue('2026-08-01');

        expect(
            (
                wrapper.get('#filter-created-within-days')
                    .element as HTMLInputElement
            ).disabled,
        ).toBe(true);
    });

    it('disables the date range fields once created_within_days is filled in', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('#filter-created-within-days').setValue('7');

        expect(
            (wrapper.get('#filter-date-from').element as HTMLInputElement)
                .disabled,
        ).toBe(true);
        expect(
            (wrapper.get('#filter-date-to').element as HTMLInputElement)
                .disabled,
        ).toBe(true);
    });

    it('applies a sort immediately when a sortable column header is clicked, without waiting for Apply', async () => {
        const wrapper = mountPage([]);

        const header = wrapper
            .findAll('thead th')
            .find((th) => th.text().includes(t('products.columns.full')));
        await header?.get('button').trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith(
            productsIndex.url(),
            expect.objectContaining({
                sort_by: 'full_cells_count',
                sort_direction: 'asc',
            }),
            { preserveState: true, replace: true },
        );
    });

    it('flips the sort direction when the same sortable header is clicked again', async () => {
        const wrapper = mountPage([], {
            sort_by: 'full_cells_count',
            sort_direction: 'asc',
        });

        const header = wrapper
            .findAll('thead th')
            .find((th) => th.text().includes(t('products.columns.full')));
        await header?.get('button').trigger('click');

        expect(routerGetMock).toHaveBeenCalledWith(
            productsIndex.url(),
            expect.objectContaining({
                sort_by: 'full_cells_count',
                sort_direction: 'desc',
            }),
            { preserveState: true, replace: true },
        );
    });

    it('preselects the current per-page value in the page-size selector', () => {
        const wrapper = mountPage([], { per_page: 50 });

        expect((wrapper.get('select').element as HTMLSelectElement).value).toBe(
            '50',
        );
    });

    it('requests the new page size when the selector changes', async () => {
        const wrapper = mountPage([]);

        await wrapper.get('select').setValue('50');

        expect(routerGetMock).toHaveBeenCalledWith(
            productsIndex.url(),
            expect.objectContaining({ per_page: 50 }),
            { preserveState: true, replace: true },
        );
    });

    it("renders the product's Arabic name, alt text and box-count label when the locale is Arabic", () => {
        i18n.global.locale.value = 'ar';

        const wrapper = mountPage([
            product({
                name: 'Widgets',
                ar_name: 'ودجات',
                image_url: '/img/widgets.png',
            }),
        ]);

        const productCell = rowCells(wrapper)[0];
        expect(productCell.text()).toContain('ودجات');
        expect(productCell.text()).not.toContain('Widgets');
        expect(productCell.get('img').attributes('alt')).toBe('ودجات');
        expect(
            rowCells(wrapper)[1].get('button').attributes('aria-label'),
        ).toBe(t('products.boxesPerPalletLabel', { product: 'ودجات' }));
    });

    it('falls back to the base product name in Arabic when the store never translated it', () => {
        i18n.global.locale.value = 'ar';

        const wrapper = mountPage([
            product({
                name: 'Widgets',
                ar_name: '',
                image_url: '/img/widgets.png',
            }),
        ]);

        const productCell = rowCells(wrapper)[0];
        expect(productCell.text()).toContain('Widgets');
        expect(productCell.get('img').attributes('alt')).toBe('Widgets');
        expect(
            rowCells(wrapper)[1].get('button').attributes('aria-label'),
        ).toBe(t('products.boxesPerPalletLabel', { product: 'Widgets' }));
    });
});

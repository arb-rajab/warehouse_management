import { Check, Filter, X } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
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

const { usePageMock, routerGetMock, routerPatchMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
    routerPatchMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { get: routerGetMock, patch: routerPatchMock },
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
        boxes_count: 12,
        full_cells_count: 2,
        opened_cells_count: 1,
        expired_cells_count: 0,
        expiring_soon_count: 0,
        activity_today_count: 0,
        activity_week_count: 0,
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
        today?: string;
        weekStart?: string;
        expiringSoonDays?: number;
    } = {},
) {
    usePageMock.mockReturnValue({
        url: '/admin/products',
        props: defaultAuthProps(),
    });

    return mount(Index, {
        props: {
            products: paginated(products),
            today: overrides.today ?? '2026-08-13',
            weekStart: overrides.weekStart ?? '2026-08-10',
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
        .find((button) => button.text().includes(t('cellLog.filters.title')));
    await trigger?.trigger('click');
}

describe('Products Index', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, routerGetMock, routerPatchMock });
    });

    afterEach(() => {
        i18n.global.locale.value = 'en';
    });

    it('renders every column header', () => {
        const wrapper = mountPage([]);

        const headers = wrapper.findAll('thead th').map((th) => th.text());
        expect(headers).toEqual([
            t('products.columns.product'),
            t('products.columns.boxesPerPallet'),
            t('products.columns.full'),
            t('products.columns.opened'),
            t('products.columns.expired'),
            t('products.columns.expiringSoon', { days: 45 }),
            t('products.columns.activityToday'),
            t('products.columns.activityWeek'),
        ]);
    });

    it('shows the empty message when there are no products', () => {
        const wrapper = mountPage([]);

        expect(wrapper.text()).toContain(t('products.empty'));
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
            t('cellLog.filters.title'),
        );
        expect(wrapper.find('#filter-product').exists()).toBe(true);
    });

    it('groups the filter fields under section headings', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        const dialog = wrapper.get('[role="dialog"]');
        expect(dialog.text()).toContain(t('cellLog.filters.sections.location'));
        expect(dialog.text()).toContain(
            t('products.filters.sections.occupancy'),
        );
        expect(dialog.text()).toContain(t('cellLog.filters.sections.activity'));
    });

    it('closes the filter dialog after Apply is clicked', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        const applyButton = wrapper
            .get('form')
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
                button.text().includes(t('cellLog.filters.title')),
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
                button.text().includes(t('cellLog.filters.title')),
            );
        expect(trigger?.get('span').text()).toBe('3');
    });

    it("populates the row and column filter select's options from filterOptions", async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        expect(
            wrapper
                .get('#filter-row')
                .findAll('option')
                .map((o) => o.text()),
        ).toEqual([t('cellLog.filters.all'), 'A', 'B']);
        expect(
            wrapper
                .get('#filter-column')
                .findAll('option')
                .map((o) => o.text()),
        ).toEqual([t('cellLog.filters.all'), '1', '2', '3']);
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

    it("renders the product's stored box count in an editable field", () => {
        const wrapper = mountPage([
            product({ name: 'Widgets', ar_name: 'ودجات', boxes_count: 24 }),
        ]);

        const input = rowCells(wrapper)[1].get('input');
        expect(input.attributes('type')).toBe('number');
        expect((input.element as HTMLInputElement).value).toBe('24');
        expect(input.attributes('aria-label')).toBe(
            t('products.boxesPerPalletLabel', { product: 'Widgets' }),
        );
    });

    it('offers no column filter on the box count, unlike every other column', () => {
        const wrapper = mountPage([]);

        const header = wrapper.findAll('thead th')[1];
        expect(header.text()).toBe(t('products.columns.boxesPerPallet'));
        expect(header.find('button[title]').exists()).toBe(false);
    });

    it('saves a changed box count against that product', async () => {
        const wrapper = mountPage([product({ id: 42, boxes_count: 12 })]);

        await rowCells(wrapper)[1].get('input').setValue('30');

        expect(routerPatchMock).toHaveBeenCalledWith(
            '/admin/products/42/box-count',
            { boxes_count: 30 },
            { preserveScroll: true, preserveState: true },
        );
    });

    it('rejects a box count below one without a round trip, restoring the stored value', async () => {
        const wrapper = mountPage([product({ boxes_count: 12 })]);

        const input = rowCells(wrapper)[1].get('input');
        await input.setValue('0');

        expect(routerPatchMock).not.toHaveBeenCalled();
        expect((input.element as HTMLInputElement).value).toBe('12');
    });

    it('rejects a cleared box count without a round trip, restoring the stored value', async () => {
        const wrapper = mountPage([product({ boxes_count: 12 })]);

        const input = rowCells(wrapper)[1].get('input');
        await input.setValue('');

        expect(routerPatchMock).not.toHaveBeenCalled();
        expect((input.element as HTMLInputElement).value).toBe('12');
    });

    it('renders the full count linking to the map filtered to this product and state=full', () => {
        const wrapper = mountPage([product({ id: 42, full_cells_count: 3 })]);

        const cell = rowCells(wrapper)[2];
        expect(cell.text()).toBe('3');
        const href = cell.get('a').attributes('href') ?? '';
        expect(href).toContain('/admin/cells');
        expect(href).toContain('product_id%5B%5D=42');
        expect(href).toContain('state=full');
    });

    it('renders the opened count linking to the map filtered to this product and state=opened', () => {
        const wrapper = mountPage([product({ id: 42, opened_cells_count: 2 })]);

        const cell = rowCells(wrapper)[3];
        expect(cell.text()).toBe('2');
        const href = cell.get('a').attributes('href') ?? '';
        expect(href).toContain('/admin/cells');
        expect(href).toContain('state=opened');
    });

    it('renders the expired count linking to the map filtered to this product and expired=true', () => {
        const wrapper = mountPage([
            product({ id: 42, expired_cells_count: 1 }),
        ]);

        const cell = rowCells(wrapper)[4];
        expect(cell.text()).toBe('1');
        const href = cell.get('a').attributes('href') ?? '';
        expect(href).toContain('/admin/cells');
        expect(href).toContain('expired=1');
    });

    it('renders the expiring-soon count linking to the map with the resolved day window', () => {
        const wrapper = mountPage(
            [product({ id: 42, expiring_soon_count: 5 })],
            {},
            { expiringSoonDays: 30 },
        );

        const cell = rowCells(wrapper)[5];
        expect(cell.text()).toBe('5');
        const href = cell.get('a').attributes('href') ?? '';
        expect(href).toContain('/admin/cells');
        expect(href).toContain('expires_within_days=30');
    });

    it('renders the today activity count linking to the cell log with today as both ends of the date range', () => {
        const wrapper = mountPage(
            [product({ id: 42, activity_today_count: 4 })],
            {},
            { today: '2026-08-13' },
        );

        const cell = rowCells(wrapper)[6];
        expect(cell.text()).toBe('4');
        const href = cell.get('a').attributes('href') ?? '';
        expect(href).toContain('/admin/cell-logs');
        expect(href).toContain('date_from=2026-08-13');
        expect(href).toContain('date_to=2026-08-13');
    });

    it('renders the this-week activity count linking to the cell log from the week start through today', () => {
        const wrapper = mountPage(
            [product({ id: 42, activity_week_count: 9 })],
            {},
            { today: '2026-08-13', weekStart: '2026-08-10' },
        );

        const cell = rowCells(wrapper)[7];
        expect(cell.text()).toBe('9');
        const href = cell.get('a').attributes('href') ?? '';
        expect(href).toContain('/admin/cell-logs');
        expect(href).toContain('date_from=2026-08-10');
        expect(href).toContain('date_to=2026-08-13');
    });

    it('carries the current row/column/user/action filters into the activity drill-down links', () => {
        const wrapper = mountPage([product({ id: 42 })], {
            row_id: 1,
            user_id: [7],
            action: ['opened'],
        });

        const href = rowCells(wrapper)[6].get('a').attributes('href') ?? '';
        expect(href).toContain('row_id=1');
        expect(href).toContain('user_id%5B%5D=7');
        expect(href).toContain('action%5B%5D=opened');
    });

    it('requests the current filter values when the filter form is submitted', async () => {
        const wrapper = mountPage([]);
        await openFilters(wrapper);

        await wrapper.get('#filter-row').setValue('1');
        await wrapper.get('#filter-column').setValue('2');
        await wrapper.get('#filter-state').setValue('full');
        await wrapper.get('#filter-expires-within-days').setValue('7');
        await wrapper.get('form').trigger('submit');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/products',
            expect.objectContaining({
                row_id: 1,
                column_number: 2,
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
            '/admin/products',
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
            t('products.columns.activityToday'),
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
            t('products.columns.activityWeek'),
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

    it('marks every occupancy column active when the state filter is applied, but leaves the activity columns alone', () => {
        const wrapper = mountPage([], { state: 'full' });

        for (const label of [
            t('products.columns.full'),
            t('products.columns.opened'),
            t('products.columns.expired'),
            t('products.columns.expiringSoon', { days: 45 }),
        ]) {
            expect(isColumnActive(wrapper, label)).toBe(true);
        }

        expect(
            isColumnActive(wrapper, t('products.columns.activityToday')),
        ).toBe(true);
        expect(
            isColumnActive(wrapper, t('products.columns.activityWeek')),
        ).toBe(true);
    });

    it('marks only the activity columns active when only an action filter is applied', () => {
        const wrapper = mountPage([], { action: ['opened'] });

        expect(
            isColumnActive(wrapper, t('products.columns.activityToday')),
        ).toBe(true);
        expect(isColumnActive(wrapper, t('products.columns.full'))).toBe(false);
    });

    it('marks every metric column active when a row filter is applied', () => {
        const wrapper = mountPage([], { row_id: 1 });

        for (const label of [
            t('products.columns.full'),
            t('products.columns.opened'),
            t('products.columns.expired'),
            t('products.columns.expiringSoon', { days: 45 }),
            t('products.columns.activityToday'),
            t('products.columns.activityWeek'),
        ]) {
            expect(isColumnActive(wrapper, label)).toBe(true);
        }
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

    it('opens the activity popover with the action/user/date fields when the Activity Today column icon is clicked', async () => {
        const wrapper = mountPage([]);

        await openColumnPopover(wrapper, t('products.columns.activityToday'));

        expect(wrapper.find('#popover-filter-action').exists()).toBe(true);
        expect(wrapper.find('#popover-filter-user').exists()).toBe(true);
        expect(wrapper.find('#popover-filter-date-from').exists()).toBe(true);
        expect(wrapper.find('#popover-filter-state').exists()).toBe(false);
    });

    it('closes one popover and opens another when a different column icon is clicked', async () => {
        const wrapper = mountPage([]);

        await openColumnPopover(wrapper, t('products.columns.product'));
        expect(wrapper.find('#popover-filter-product').exists()).toBe(true);

        await openColumnPopover(wrapper, t('products.columns.activityToday'));
        expect(wrapper.find('#popover-filter-product').exists()).toBe(false);
        expect(wrapper.find('#popover-filter-action').exists()).toBe(true);
    });

    it('auto-applies, debounced, when a field is changed inside an open column popover', async () => {
        vi.useFakeTimers();
        const wrapper = mountPage([]);

        await openColumnPopover(wrapper, t('products.columns.full'));
        await wrapper.get('#popover-filter-state').setValue('opened');

        expect(routerGetMock).not.toHaveBeenCalled();

        vi.advanceTimersByTime(400);

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/products',
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
            '/admin/products',
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
            '/admin/products',
            expect.objectContaining({ user_id: ['7'] }),
            { preserveState: true, replace: true },
        );
    });

    it('resets every filter field and reloads the unfiltered list when Clear is clicked', async () => {
        const wrapper = mountPage([], {
            row_id: 1,
            state: 'full',
            expired: true,
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
            '/admin/products',
            { per_page: 20 },
            { preserveState: true, replace: true },
        );
        expect(
            (wrapper.get('#filter-row').element as HTMLSelectElement).value,
        ).toBe('');
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
            '/admin/products',
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
            '/admin/products',
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
            '/admin/products',
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
        expect(rowCells(wrapper)[1].get('input').attributes('aria-label')).toBe(
            t('products.boxesPerPalletLabel', { product: 'ودجات' }),
        );
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
        expect(rowCells(wrapper)[1].get('input').attributes('aria-label')).toBe(
            t('products.boxesPerPalletLabel', { product: 'Widgets' }),
        );
    });
});

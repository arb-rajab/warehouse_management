import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { formatDate, formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import type { Cell, Row } from '@/types/admin';
import Show from './Show.vue';

const { usePageMock, routerGetMock, routerPostMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
    routerPostMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');

    const LinkStub = defineComponent({
        props: ['href', 'as'],
        setup(props, { slots }) {
            return () =>
                h(
                    props.as ?? 'a',
                    {
                        href:
                            typeof props.href === 'string'
                                ? props.href
                                : props.href?.url,
                    },
                    slots.default?.(),
                );
        },
    });

    return {
        Head: defineComponent({ render: () => null }),
        Link: LinkStub,
        usePage: usePageMock,
        router: { get: routerGetMock, post: routerPostMock },
    };
});

const products = [
    { id: 1, name: 'Widgets' },
    { id: 2, name: 'Gadgets' },
];

function row(overrides: Partial<Row> = {}): Row {
    return {
        id: 1,
        letter: 'A',
        cells_count: 2,
        flats_count: 2,
        has_pallets: false,
        ...overrides,
    };
}

function cell(overrides: Partial<Cell> = {}): Cell {
    return {
        id: 1,
        cell_number: 1,
        flat_number: 1,
        state: 'empty',
        pallet: null,
        ...overrides,
    };
}

function pallet(
    overrides: Partial<Cell['pallet']> = {},
): NonNullable<Cell['pallet']> {
    return {
        id: 1,
        product_id: 1,
        product_name: 'Widgets',
        product_image_url: null,
        expiration_date: '2026-09-01',
        added_at: '2026-07-01T10:00:00Z',
        is_stale: null,
        ...overrides,
    };
}

function mountPage(
    rowOverrides: Partial<Row>,
    cells: Cell[],
    dateOverrides: { today?: string } = {},
) {
    usePageMock.mockReturnValue({
        url: '/admin/rows/A',
        props: { locale: 'en', auth: { user: { name: 'Jane Doe', id: 7 } } },
    });

    return mount(Show, {
        props: {
            row: row(rowOverrides),
            cells,
            today: dateOverrides.today ?? '2026-08-13',
            filterOptions: { products },
        },
    });
}

async function openHighlightFilters(
    wrapper: ReturnType<typeof mountPage>,
): Promise<void> {
    const trigger = wrapper
        .findAll('button')
        .find((button) =>
            button.text().includes(t('rows.show.highlight.button')),
        );
    await trigger?.trigger('click');
}

function slot(wrapper: ReturnType<typeof mountPage>, label: string) {
    return wrapper
        .findAll('[data-testid="cell-slot"]')
        .find((el) => el.text().includes(label));
}

describe('Rows Show', () => {
    it('renders the title with the row letter', () => {
        const wrapper = mountPage({ letter: 'B' }, []);

        expect(wrapper.text()).toContain(t('rows.show.title', { letter: 'B' }));
    });

    it("links to the row's edit page", () => {
        const wrapper = mountPage({ letter: 'B' }, []);

        const editLink = wrapper
            .findAll('a')
            .find((a) => a.text().includes(t('rows.show.editRow')));
        expect(editLink?.attributes('href')).toBe('/admin/rows/B/edit');
    });

    it('renders a column header for every cell number, ascending', () => {
        const wrapper = mountPage({ cells_count: 3, flats_count: 1 }, []);

        const headers = wrapper
            .findAll('[data-testid="cell-header"]')
            .map((el) => el.text());
        expect(headers).toEqual([
            t('rows.show.column', { n: 1 }),
            t('rows.show.column', { n: 2 }),
            t('rows.show.column', { n: 3 }),
        ]);
    });

    it('renders a flat header for every flat number, highest first', () => {
        const wrapper = mountPage({ cells_count: 1, flats_count: 3 }, []);

        const headers = wrapper
            .findAll('[data-testid="flat-header"]')
            .map((el) => el.text());
        expect(headers).toEqual([
            t('rows.show.flat', { n: 3 }),
            t('rows.show.flat', { n: 2 }),
            t('rows.show.flat', { n: 1 }),
        ]);
    });

    it('labels every grid slot with its cell/flat coordinate', () => {
        const wrapper = mountPage({ cells_count: 2, flats_count: 2 }, []);

        expect(wrapper.text()).toContain(formatSlot('A', 1, 1));
        expect(wrapper.text()).toContain(formatSlot('A', 1, 2));
        expect(wrapper.text()).toContain(formatSlot('A', 2, 1));
        expect(wrapper.text()).toContain(formatSlot('A', 2, 2));
    });

    it('shows the pallet details for a slot that has one', () => {
        const wrapper = mountPage({ cells_count: 1, flats_count: 1 }, [
            cell({
                cell_number: 1,
                flat_number: 1,
                state: 'full',
                pallet: pallet({
                    id: 9,
                    product_image_url: '/img/widgets.png',
                    expiration_date: '2026-09-01',
                    added_at: '2026-08-01T10:00:00Z',
                }),
            }),
        ]);

        expect(wrapper.text()).toContain('Widgets');
        expect(wrapper.text()).toContain(formatDate('2026-09-01'));
        expect(wrapper.text()).toContain(
            formatDateTime('2026-08-01T10:00:00Z'),
        );
        const img = wrapper.get('img');
        expect(img.attributes('src')).toBe('/img/widgets.png');
        expect(img.attributes('alt')).toBe('Widgets');
    });

    it('shows "Empty" for a slot with a cell record but no pallet, without the missing-cell styling', () => {
        const wrapper = mountPage({ cells_count: 1, flats_count: 1 }, [
            cell({
                cell_number: 1,
                flat_number: 1,
                state: 'empty',
                pallet: null,
            }),
        ]);

        const slotEl = slot(wrapper, formatSlot('A', 1, 1));
        expect(slotEl?.text()).toContain(t('rows.show.empty'));
        expect(slotEl?.classes()).not.toContain('border-dashed');
    });

    it('shows "Empty" with the missing-cell styling for a coordinate with no cell record at all', () => {
        const wrapper = mountPage({ cells_count: 1, flats_count: 1 }, []);

        const slotEl = slot(wrapper, formatSlot('A', 1, 1));
        expect(slotEl?.text()).toContain(t('rows.show.empty'));
        expect(slotEl?.classes()).toContain('border-dashed');
    });

    it('applies a distinct background class per cell state', () => {
        const wrapper = mountPage({ cells_count: 3, flats_count: 1 }, [
            cell({ cell_number: 1, flat_number: 1, state: 'full' }),
            cell({ cell_number: 2, flat_number: 1, state: 'opened' }),
            cell({ cell_number: 3, flat_number: 1, state: 'empty' }),
        ]);

        expect(slot(wrapper, formatSlot('A', 1, 1))?.classes()).toContain(
            'bg-green-50',
        );
        expect(slot(wrapper, formatSlot('A', 2, 1))?.classes()).toContain(
            'bg-orange-50',
        );
        expect(slot(wrapper, formatSlot('A', 3, 1))?.classes()).toContain(
            'bg-gray-100',
        );
    });

    it("does not mark an expired pallet's slot with a warning badge or border, absent a highlight filter", () => {
        const wrapper = mountPage({ cells_count: 1, flats_count: 1 }, [
            cell({
                cell_number: 1,
                flat_number: 1,
                state: 'full',
                pallet: pallet({ expiration_date: '2026-08-01' }),
            }),
        ]);

        const slotEl = slot(wrapper, formatSlot('A', 1, 1));
        expect(slotEl?.find('[data-testid="expiry-badge"]').exists()).toBe(
            false,
        );
        expect(slotEl?.classes()).not.toContain('border-red-500');
    });

    it('opens the highlight filter dialog when the highlight button is clicked', async () => {
        const wrapper = mountPage({ cells_count: 1, flats_count: 1 }, []);

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);

        await openHighlightFilters(wrapper);

        expect(wrapper.get('[role="dialog"]').text()).toContain(
            t('rows.show.highlight.button'),
        );
        expect(wrapper.find('#highlight-state').exists()).toBe(true);
        expect(wrapper.find('#highlight-expires-within-days').exists()).toBe(
            true,
        );
        expect(wrapper.find('#highlight-product').exists()).toBe(true);
        expect(wrapper.find('#highlight-stale-after-days').exists()).toBe(true);
    });

    it('highlights only cells matching the selected state', async () => {
        const wrapper = mountPage({ cells_count: 2, flats_count: 1 }, [
            cell({ cell_number: 1, flat_number: 1, state: 'full' }),
            cell({ cell_number: 2, flat_number: 1, state: 'opened' }),
        ]);

        await openHighlightFilters(wrapper);
        await wrapper.get('#highlight-state').trigger('click');
        await wrapper.findAll('input[type="checkbox"]')[2].setValue(true);

        expect(slot(wrapper, formatSlot('A', 1, 1))?.classes()).not.toContain(
            'ring-blue-500',
        );
        expect(slot(wrapper, formatSlot('A', 2, 1))?.classes()).toContain(
            'ring-blue-500',
        );
    });

    it('highlights only cells expiring within the given number of days', async () => {
        const wrapper = mountPage({ cells_count: 2, flats_count: 1 }, [
            cell({
                cell_number: 1,
                flat_number: 1,
                state: 'full',
                pallet: pallet({ id: 1, expiration_date: '2026-08-15' }),
            }),
            cell({
                cell_number: 2,
                flat_number: 1,
                state: 'full',
                pallet: pallet({
                    id: 2,
                    product_name: 'Gadgets',
                    expiration_date: '2026-09-01',
                }),
            }),
        ]);

        await openHighlightFilters(wrapper);
        await wrapper.get('#highlight-expires-within-days').setValue(5);

        expect(slot(wrapper, formatSlot('A', 1, 1))?.classes()).toContain(
            'ring-blue-500',
        );
        expect(slot(wrapper, formatSlot('A', 2, 1))?.classes()).not.toContain(
            'ring-blue-500',
        );
    });

    it('highlights only cells whose pallet matches the selected product', async () => {
        const wrapper = mountPage({ cells_count: 2, flats_count: 1 }, [
            cell({
                cell_number: 1,
                flat_number: 1,
                state: 'full',
                pallet: pallet({
                    id: 1,
                    product_id: 1,
                    product_name: 'Widgets',
                }),
            }),
            cell({
                cell_number: 2,
                flat_number: 1,
                state: 'full',
                pallet: pallet({
                    id: 2,
                    product_id: 2,
                    product_name: 'Gadgets',
                }),
            }),
        ]);

        await openHighlightFilters(wrapper);
        await wrapper.get('#highlight-product').trigger('click');
        await wrapper
            .findAll('[role="listbox"] input[type="checkbox"]')[0]
            .setValue(true);

        expect(slot(wrapper, formatSlot('A', 1, 1))?.classes()).toContain(
            'ring-blue-500',
        );
        expect(slot(wrapper, formatSlot('A', 2, 1))?.classes()).not.toContain(
            'ring-blue-500',
        );
    });

    it('highlights only cells stale for at least the given number of days', async () => {
        const wrapper = mountPage({ cells_count: 2, flats_count: 1 }, [
            cell({
                cell_number: 1,
                flat_number: 1,
                state: 'full',
                pallet: pallet({ id: 1, added_at: '2026-07-01T00:00:00Z' }),
            }),
            cell({
                cell_number: 2,
                flat_number: 1,
                state: 'full',
                pallet: pallet({ id: 2, added_at: '2026-08-12T00:00:00Z' }),
            }),
        ]);

        await openHighlightFilters(wrapper);
        await wrapper.get('#highlight-stale-after-days').setValue(5);

        expect(slot(wrapper, formatSlot('A', 1, 1))?.classes()).toContain(
            'ring-blue-500',
        );
        expect(slot(wrapper, formatSlot('A', 2, 1))?.classes()).not.toContain(
            'ring-blue-500',
        );
    });

    it('shows no highlights when no highlight filters are selected', () => {
        const wrapper = mountPage({ cells_count: 1, flats_count: 1 }, [
            cell({ cell_number: 1, flat_number: 1, state: 'full' }),
        ]);

        expect(slot(wrapper, formatSlot('A', 1, 1))?.classes()).not.toContain(
            'ring-blue-500',
        );
    });

    it('clears highlight filters and removes the highlight when Clear is clicked', async () => {
        const wrapper = mountPage({ cells_count: 1, flats_count: 1 }, [
            cell({ cell_number: 1, flat_number: 1, state: 'opened' }),
        ]);

        await openHighlightFilters(wrapper);
        await wrapper.get('#highlight-state').trigger('click');
        await wrapper.findAll('input[type="checkbox"]')[2].setValue(true);
        expect(slot(wrapper, formatSlot('A', 1, 1))?.classes()).toContain(
            'ring-blue-500',
        );

        const clearButton = wrapper
            .findAll('button')
            .find((button) => button.text() === t('cellLog.filters.clear'));
        await clearButton?.trigger('click');

        expect(slot(wrapper, formatSlot('A', 1, 1))?.classes()).not.toContain(
            'ring-blue-500',
        );
        expect(wrapper.get('#highlight-state').text()).toBe(
            t('cellLog.filters.all'),
        );
    });
});

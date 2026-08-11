import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { formatDate, formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import type { Cell, Row } from '@/types/admin';
import Show from './Show.vue';

const { usePageMock, routerPostMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
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
        router: { post: routerPostMock },
    };
});

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

function mountPage(rowOverrides: Partial<Row>, cells: Cell[]) {
    usePageMock.mockReturnValue({
        url: '/admin/rows/A',
        props: { locale: 'en', auth: { user: { name: 'Jane Doe', id: 7 } } },
    });

    return mount(Show, { props: { row: row(rowOverrides), cells } });
}

function slot(wrapper: ReturnType<typeof mountPage>, label: string) {
    return wrapper
        .findAll('[data-testid], .relative')
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
                pallet: {
                    id: 9,
                    product_name: 'Widgets',
                    product_image_url: '/img/widgets.png',
                    expiration_date: '2026-09-01',
                    added_at: '2026-08-01T10:00:00Z',
                },
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
            'bg-red-50',
        );
        expect(slot(wrapper, formatSlot('A', 2, 1))?.classes()).toContain(
            'bg-amber-50',
        );
        expect(slot(wrapper, formatSlot('A', 3, 1))?.classes()).toContain(
            'bg-white',
        );
    });
});

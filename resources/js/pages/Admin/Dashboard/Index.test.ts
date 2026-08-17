import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import DashboardStatTile from '@/components/DashboardStatTile.vue';
import { t } from '@/lib/i18n';
import Index from './Index.vue';

const { usePageMock, routerGetMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');

    const LinkStub = defineComponent({
        props: ['href', 'data'],
        setup(props, { slots }) {
            return () =>
                h(
                    'a',
                    {
                        href: props.href,
                        'data-query': JSON.stringify(props.data ?? {}),
                    },
                    slots.default?.(),
                );
        },
    });

    return {
        Head: defineComponent({ render: () => null }),
        Link: LinkStub,
        usePage: usePageMock,
        router: { get: routerGetMock },
    };
});

const stats = {
    occupancy: { empty: 3, full: 5, opened: 1 },
    expiring: {
        expired: 2,
        windows: [
            { days: 7, until: '2026-08-20', count: 4 },
            { days: 14, until: '2026-08-27', count: 6 },
            { days: 30, until: '2026-09-12', count: 9 },
            { days: 60, until: '2026-10-12', count: 11 },
        ],
        custom: { days: 45, until: '2026-09-27', count: 8 },
    },
    activity_today: { stored: 6, opened: 2, emptied: 1, transferred: 3 },
    activity_week: { stored: 20, opened: 8, emptied: 4, transferred: 9 },
};

const products = [
    { id: 1, name: 'Widgets' },
    { id: 2, name: 'Gadgets' },
];

function mountPage(
    overrides: {
        filters?: { product_id: number[] | null };
    } = {},
) {
    usePageMock.mockReturnValue({
        url: '/admin',
        props: { locale: 'en', auth: { user: { name: 'Jane Doe', id: 7 } } },
    });

    return mount(Index, {
        props: {
            stats,
            today: '2026-08-13',
            weekStart: '2026-08-10',
            filters: overrides.filters ?? { product_id: null },
            filterOptions: { products },
        },
    });
}

// `label` alone is ambiguous across sections. Disambiguate by href + query.
function tileByLabelAndQuery(
    wrapper: ReturnType<typeof mountPage>,
    label: string,
    query: Record<string, unknown>,
) {
    return wrapper
        .findAllComponents(DashboardStatTile)
        .find(
            (tile) =>
                tile.props('label') === label &&
                JSON.stringify(tile.props('query') ?? {}) ===
                    JSON.stringify(query),
        );
}

describe('Dashboard Index', () => {
    beforeEach(() => {
        usePageMock.mockReset();
        routerGetMock.mockReset();
    });

    it('renders every occupancy tile with its count, linking to the cells map with a matching highlight filter', () => {
        const wrapper = mountPage();

        const tiles = wrapper.findAllComponents(DashboardStatTile);
        const empty = tiles.find(
            (tile) => tile.props('label') === t('dashboard.occupancy.empty'),
        );
        expect(empty?.props('value')).toBe(3);
        expect(empty?.props('href')).toBe('/admin/cells');
        expect(empty?.props('query')).toEqual({ state: 'empty' });

        const full = tiles.find(
            (tile) => tile.props('label') === t('dashboard.occupancy.full'),
        );
        expect(full?.props('value')).toBe(5);
        expect(full?.props('query')).toEqual({ state: 'full' });

        const opened = tiles.find(
            (tile) =>
                tile.props('label') === t('dashboard.occupancy.opened') &&
                tile.props('href') === '/admin/cells',
        );
        expect(opened?.props('value')).toBe(1);
        expect(opened?.props('query')).toEqual({ state: 'opened' });
    });

    it('includes the active product filter in every occupancy tile link', () => {
        const wrapper = mountPage({ filters: { product_id: [1, 2] } });

        const full = wrapper
            .findAllComponents(DashboardStatTile)
            .find(
                (tile) => tile.props('label') === t('dashboard.occupancy.full'),
            );

        expect(full?.props('query')).toEqual({
            state: 'full',
            product_id: [1, 2],
        });
    });

    it('renders the expired tile linking to the cells map with an expires-within-days highlight', () => {
        const wrapper = mountPage();

        const expired = tileByLabelAndQuery(
            wrapper,
            t('dashboard.expiring.expired'),
            { expired: true },
        );
        expect(expired?.props('value')).toBe(2);
        expect(expired?.props('href')).toBe('/admin/cells');
    });

    it('renders one tile per fixed expiring-soon window', () => {
        const wrapper = mountPage();

        for (const window of stats.expiring.windows) {
            const tile = tileByLabelAndQuery(
                wrapper,
                t('dashboard.expiring.soon', { days: window.days }),
                { expires_within_days: window.days },
            );
            expect(tile?.props('value')).toBe(window.count);
            expect(tile?.props('href')).toBe('/admin/cells');
        }
    });

    it('renders the custom expiring-soon card with its current count and day count', () => {
        const wrapper = mountPage();

        const customLink = wrapper
            .find('#dashboard-custom-expiring-days')
            .element.closest('div')
            ?.querySelector('a');
        expect(customLink?.getAttribute('href')).toBe('/admin/cells');
        expect(customLink?.getAttribute('data-query')).toBe(
            JSON.stringify({ expires_within_days: 45 }),
        );
        expect(wrapper.text()).toContain('8');
        expect(wrapper.text()).toContain(
            t('dashboard.expiring.soon', { days: 45 }),
        );
        expect(
            (
                wrapper.get('#dashboard-custom-expiring-days')
                    .element as HTMLInputElement
            ).value,
        ).toBe('45');
    });

    it('reloads with the new custom day count when it changes', async () => {
        const wrapper = mountPage();

        const input = wrapper.get('#dashboard-custom-expiring-days');
        await input.setValue('90');
        await input.trigger('change');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin',
            { product_id: [], expiring_days: 90 },
            { preserveState: true, replace: true },
        );
    });

    it("renders today's activity tiles linking to the cell log filtered to today", () => {
        const wrapper = mountPage();

        const stored = tileByLabelAndQuery(
            wrapper,
            t('dashboard.activityToday.stored'),
            {
                action: ['stored'],
                date_from: '2026-08-13',
                date_to: '2026-08-13',
            },
        );
        expect(stored?.props('value')).toBe(6);
        expect(stored?.props('href')).toBe('/admin/cell-logs');

        const transferred = tileByLabelAndQuery(
            wrapper,
            t('dashboard.activityToday.transferred'),
            {
                action: ['transferred_out', 'transferred_in'],
                date_from: '2026-08-13',
                date_to: '2026-08-13',
            },
        );
        expect(transferred?.props('value')).toBe(3);
    });

    it("renders this week's activity tiles linking to the cell log filtered to the week", () => {
        const wrapper = mountPage();

        const stored = tileByLabelAndQuery(
            wrapper,
            t('dashboard.activityWeek.stored'),
            {
                action: ['stored'],
                date_from: '2026-08-10',
                date_to: '2026-08-13',
            },
        );
        expect(stored?.props('value')).toBe(20);

        const emptied = tileByLabelAndQuery(
            wrapper,
            t('dashboard.activityWeek.emptied'),
            {
                action: ['emptied'],
                date_from: '2026-08-10',
                date_to: '2026-08-13',
            },
        );
        expect(emptied?.props('value')).toBe(4);
    });

    it('includes the active product filter in every activity/expiring tile link', () => {
        const wrapper = mountPage({ filters: { product_id: [1, 2] } });

        const stored = tileByLabelAndQuery(
            wrapper,
            t('dashboard.activityToday.stored'),
            {
                action: ['stored'],
                date_from: '2026-08-13',
                date_to: '2026-08-13',
                product_id: [1, 2],
            },
        );
        expect(stored?.exists()).toBe(true);

        const expired = tileByLabelAndQuery(
            wrapper,
            t('dashboard.expiring.expired'),
            { expired: true, product_id: [1, 2] },
        );
        expect(expired?.exists()).toBe(true);
    });

    it('renders exactly four sections, with the stale section removed', () => {
        const wrapper = mountPage();

        expect(wrapper.findAll('section')).toHaveLength(4);
    });

    it('renders every section heading', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('dashboard.occupancy.title'));
        expect(wrapper.text()).toContain(t('dashboard.expiring.title'));
        expect(wrapper.text()).toContain(t('dashboard.activityToday.title'));
        expect(wrapper.text()).toContain(t('dashboard.activityWeek.title'));
    });

    it('reloads with the selected products when the product filter changes, preserving the custom day count', async () => {
        const wrapper = mountPage();

        await wrapper.get('#dashboard-product').trigger('click');
        await wrapper.findAll('input[type="checkbox"]')[0].setValue(true);

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin',
            { product_id: ['1'], expiring_days: 45 },
            { preserveState: true, replace: true },
        );
    });
});

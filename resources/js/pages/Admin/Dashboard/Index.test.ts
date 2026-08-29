import {
    ArrowLeftRight,
    CalendarPlus,
    CalendarX,
    CircleDashed,
    Inbox,
    PackageOpen,
} from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import DashboardStatTile from '@/components/DashboardStatTile.vue';
import { t } from '@/lib/i18n';
import { paginated } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import Index from './Index.vue';

const { usePageMock, routerGetMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');
    const { headStub } = await import('@/testing/inertiaStubs');

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
        Head: headStub,
        Link: LinkStub,
        usePage: usePageMock,
        router: { get: routerGetMock },
        useHttp: () => ({
            get: (
                _url: string,
                options?: { onSuccess?: (response: unknown) => void },
            ) => options?.onSuccess?.(paginated(products, 20)),
        }),
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
        props: defaultAuthProps(),
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

async function openCustomExpiringDaysDialog(
    wrapper: ReturnType<typeof mountPage>,
): Promise<void> {
    const trigger = wrapper
        .findAll('button')
        .find((button) => button.text().includes(t('expiringWindow.label')));
    await trigger?.trigger('click');
}

describe('Dashboard Index', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, routerGetMock });
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

        expect(empty?.props('icon')).toBe(CircleDashed);
        expect(full?.props('icon')).toBe(Inbox);
        expect(opened?.props('icon')).toBe(PackageOpen);
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
        expect(expired?.props('icon')).toBe(CalendarX);
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
            expect(tile?.props('icon')).toBe(CalendarX);
        }
    });

    it('keeps the custom expiring-days field out of the DOM until its dialog is opened', () => {
        const wrapper = mountPage();

        expect(wrapper.find('#dashboard-custom-expiring-days').exists()).toBe(
            false,
        );
        expect(wrapper.text()).toContain(t('expiringWindow.label'));
    });

    it('renders the custom expiring-soon card with its current count and day count', () => {
        const wrapper = mountPage();

        const customLink = wrapper
            .findAll('a')
            .find(
                (link) =>
                    link.attributes('data-query') ===
                    JSON.stringify({ expires_within_days: 45 }),
            );
        expect(customLink?.attributes('href')).toBe('/admin/cells');
        expect(customLink?.find('svg.lucide-calendar-x').exists()).toBe(true);
        expect(wrapper.text()).toContain('8');
        expect(wrapper.text()).toContain(
            t('dashboard.expiring.soon', { days: 45 }),
        );
    });

    it('opens the custom expiring-days dialog pre-filled with the current day count, and shows a visible label', async () => {
        const wrapper = mountPage();

        await openCustomExpiringDaysDialog(wrapper);

        expect(
            wrapper.get('label[for="dashboard-custom-expiring-days"]').text(),
        ).toBe(t('cellHighlight.expiresWithinDays'));
        expect(
            (
                wrapper.get('#dashboard-custom-expiring-days')
                    .element as HTMLInputElement
            ).value,
        ).toBe('45');
    });

    it("doesn't reload while the custom expiring-days field is being edited, only once the dialog form is submitted", async () => {
        const wrapper = mountPage();

        await openCustomExpiringDaysDialog(wrapper);

        const input = wrapper.get('#dashboard-custom-expiring-days');
        await input.setValue('90');
        await input.trigger('change');

        expect(routerGetMock).not.toHaveBeenCalled();

        await wrapper.get('form').trigger('submit');

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
        expect(stored?.props('icon')).toBe(CalendarPlus);

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
        expect(transferred?.props('icon')).toBe(ArrowLeftRight);
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
        expect(stored?.props('icon')).toBe(CalendarPlus);

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
        expect(emptied?.props('icon')).toBe(CircleDashed);
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

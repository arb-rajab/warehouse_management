import { Head } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    CalendarPlus,
    CalendarX,
    CircleDashed,
    Clock,
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
                        href:
                            typeof props.href === 'string'
                                ? props.href
                                : props.href?.url,
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
            { months: 1, days: 31, until: '2026-09-13', count: 4 },
            { months: 2, days: 61, until: '2026-10-13', count: 6 },
            { months: 4, days: 122, until: '2026-12-13', count: 9 },
            { months: 6, days: 184, until: '2027-02-13', count: 11 },
        ],
        custom: { days: 45, until: '2026-09-27', count: 8 },
    },
    stale: { days: 30, count: 5 },
    activity_today: { stored: 6, opened: 2, emptied: 1, transferred: 3 },
    activity_week: { stored: 20, opened: 8, emptied: 4, transferred: 9 },
};

const products = [
    { id: 1, name: 'Widgets', ar_name: 'ودجات' },
    { id: 2, name: 'Gadgets', ar_name: 'أدوات' },
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

async function openCustomStaleDaysDialog(
    wrapper: ReturnType<typeof mountPage>,
): Promise<void> {
    const trigger = wrapper
        .findAll('button')
        .find((button) => button.text().includes(t('staleWindow.label')));
    await trigger?.trigger('click');
}

describe('Dashboard Index', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, routerGetMock });
    });

    it('renders the page title in the Head and the PageHeader', () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('dashboard.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('dashboard.title'));
    });

    it('links the help icon to the dashboard help page', () => {
        const wrapper = mountPage();

        const helpLink = wrapper
            .findAll('a')
            .find((a) => a.attributes('aria-label') === t('help.viewHelp'));
        expect(helpLink?.attributes('href')).toBe('/admin/help/dashboard');
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
                t('dashboard.expiring.soonMonths', { months: window.months }),
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
            { product_id: [], expiring_days: 90, stale_days: 30 },
            { preserveState: true, replace: true },
        );
    });

    it('keeps the custom stale-days field out of the DOM until its dialog is opened', () => {
        const wrapper = mountPage();

        expect(wrapper.find('#dashboard-custom-stale-days').exists()).toBe(
            false,
        );
        expect(wrapper.text()).toContain(t('staleWindow.label'));
    });

    it('renders the custom stale card with its current count and day count', () => {
        const wrapper = mountPage();

        const customLink = wrapper
            .findAll('a')
            .find(
                (link) =>
                    link.attributes('data-query') ===
                    JSON.stringify({ stale_after_days: 30 }),
            );
        expect(customLink?.attributes('href')).toBe('/admin/cells');
        expect(customLink?.find('svg.lucide-clock').exists()).toBe(true);
        expect(wrapper.text()).toContain('5');
        expect(wrapper.text()).toContain(
            t('dashboard.stale.soon', { days: 30 }),
        );

        const staleTile = tileByLabelAndQuery(
            wrapper,
            t('dashboard.stale.soon', { days: 30 }),
            { stale_after_days: 30 },
        );
        expect(staleTile?.props('icon')).toBe(Clock);
    });

    it('opens the custom stale-days dialog pre-filled with the current day count, and shows a visible label', async () => {
        const wrapper = mountPage();

        await openCustomStaleDaysDialog(wrapper);

        expect(
            wrapper.get('label[for="dashboard-custom-stale-days"]').text(),
        ).toBe(t('cellHighlight.staleAfterDays'));
        expect(
            (
                wrapper.get('#dashboard-custom-stale-days')
                    .element as HTMLInputElement
            ).value,
        ).toBe('30');
    });

    it("doesn't reload while the custom stale-days field is being edited, only once the dialog form is submitted", async () => {
        const wrapper = mountPage();

        await openCustomStaleDaysDialog(wrapper);

        const input = wrapper.get('#dashboard-custom-stale-days');
        await input.setValue('60');
        await input.trigger('change');

        expect(routerGetMock).not.toHaveBeenCalled();

        await wrapper.get('form').trigger('submit');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin',
            { product_id: [], expiring_days: 45, stale_days: 60 },
            { preserveState: true, replace: true },
        );
    });

    it("renders today's activity tiles linking to the cell log filtered to today", () => {
        const wrapper = mountPage();

        const stored = tileByLabelAndQuery(
            wrapper,
            t('cellLog.actions.stored'),
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
            t('cellLog.actions.transferred'),
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
            t('cellLog.actions.stored'),
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
            t('cellLog.actions.emptied'),
            {
                action: ['emptied'],
                date_from: '2026-08-10',
                date_to: '2026-08-13',
            },
        );
        expect(emptied?.props('value')).toBe(4);
        expect(emptied?.props('icon')).toBe(CircleDashed);
    });

    it('includes the active product filter in every activity/expiring/stale tile link', () => {
        const wrapper = mountPage({ filters: { product_id: [1, 2] } });

        const stored = tileByLabelAndQuery(
            wrapper,
            t('cellLog.actions.stored'),
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

        const stale = tileByLabelAndQuery(
            wrapper,
            t('dashboard.stale.soon', { days: 30 }),
            { stale_after_days: 30, product_id: [1, 2] },
        );
        expect(stale?.exists()).toBe(true);
    });

    it('renders exactly five sections, including the stale section', () => {
        const wrapper = mountPage();

        expect(wrapper.findAll('section')).toHaveLength(5);
    });

    it('renders every section heading', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('dashboard.occupancy.title'));
        expect(wrapper.text()).toContain(t('dashboard.expiring.title'));
        expect(wrapper.text()).toContain(t('dashboard.stale.title'));
        expect(wrapper.text()).toContain(t('dashboard.activityToday.title'));
        expect(wrapper.text()).toContain(t('dashboard.activityWeek.title'));
    });

    it('reloads with the selected products when the product filter changes, preserving the custom day counts', async () => {
        const wrapper = mountPage();

        await wrapper.get('#dashboard-product').trigger('click');
        await wrapper.findAll('input[type="checkbox"]')[0].setValue(true);

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin',
            { product_id: ['1'], expiring_days: 45, stale_days: 30 },
            { preserveState: true, replace: true },
        );
    });
});

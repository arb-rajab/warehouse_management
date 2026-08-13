import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import DashboardStatTile from '@/components/DashboardStatTile.vue';
import { t } from '@/lib/i18n';
import Index from './Index.vue';

const { usePageMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
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
    };
});

const stats = {
    occupancy: { empty: 3, full: 5, opened: 1 },
    expiring: { expired: 2, soon: 4 },
    activity_today: { stored: 6, opened: 2, emptied: 1, transferred: 3 },
};

function mountPage() {
    usePageMock.mockReturnValue({
        url: '/admin',
        props: { locale: 'en', auth: { user: { name: 'Jane Doe', id: 7 } } },
    });

    return mount(Index, {
        props: {
            stats,
            today: '2026-08-13',
            expiringSoonUntil: '2026-08-20',
        },
    });
}

// `label` alone is ambiguous: dashboard.occupancy.opened and
// dashboard.activityToday.opened both render as "Opened". Disambiguate by
// the tile's href (cells list vs cell log list) as well.
function tileByLabelAndHref(
    wrapper: ReturnType<typeof mountPage>,
    label: string,
    href: string,
) {
    return wrapper
        .findAllComponents(DashboardStatTile)
        .find(
            (tile) =>
                tile.props('label') === label && tile.props('href') === href,
        );
}

describe('Dashboard Index', () => {
    beforeEach(() => {
        usePageMock.mockReset();
    });

    it('renders every occupancy tile with its count and link', () => {
        const wrapper = mountPage();

        const empty = tileByLabelAndHref(
            wrapper,
            t('dashboard.occupancy.empty'),
            '/admin/cells',
        );
        expect(empty?.props('value')).toBe(3);
        expect(empty?.props('query')).toEqual({ state: 'empty' });

        const full = tileByLabelAndHref(
            wrapper,
            t('dashboard.occupancy.full'),
            '/admin/cells',
        );
        expect(full?.props('value')).toBe(5);
        expect(full?.props('query')).toEqual({ state: 'full' });

        const opened = tileByLabelAndHref(
            wrapper,
            t('dashboard.occupancy.opened'),
            '/admin/cells',
        );
        expect(opened?.props('value')).toBe(1);
        expect(opened?.props('query')).toEqual({ state: 'opened' });
    });

    it('renders the expired and expiring-soon tiles linking to the cells list with the expiration range', () => {
        const wrapper = mountPage();

        const expired = tileByLabelAndHref(
            wrapper,
            t('dashboard.expiring.expired'),
            '/admin/cells',
        );
        expect(expired?.props('value')).toBe(2);
        expect(expired?.props('query')).toEqual({
            expiration_date_to: '2026-08-13',
        });

        const soon = tileByLabelAndHref(
            wrapper,
            t('dashboard.expiring.soon'),
            '/admin/cells',
        );
        expect(soon?.props('value')).toBe(4);
        expect(soon?.props('query')).toEqual({
            expiration_date_from: '2026-08-13',
            expiration_date_to: '2026-08-20',
        });
    });

    it("renders today's activity tiles linking to the cell log filtered to today", () => {
        const wrapper = mountPage();

        const stored = tileByLabelAndHref(
            wrapper,
            t('dashboard.activityToday.stored'),
            '/admin/cell-logs',
        );
        expect(stored?.props('value')).toBe(6);
        expect(stored?.props('query')).toEqual({
            action: ['stored'],
            date_from: '2026-08-13',
            date_to: '2026-08-13',
        });

        const opened = tileByLabelAndHref(
            wrapper,
            t('dashboard.activityToday.opened'),
            '/admin/cell-logs',
        );
        expect(opened?.props('value')).toBe(2);
        expect(opened?.props('query')).toEqual({
            action: ['opened'],
            date_from: '2026-08-13',
            date_to: '2026-08-13',
        });

        const emptied = tileByLabelAndHref(
            wrapper,
            t('dashboard.activityToday.emptied'),
            '/admin/cell-logs',
        );
        expect(emptied?.props('value')).toBe(1);
        expect(emptied?.props('query')).toEqual({
            action: ['emptied'],
            date_from: '2026-08-13',
            date_to: '2026-08-13',
        });

        const transferred = tileByLabelAndHref(
            wrapper,
            t('dashboard.activityToday.transferred'),
            '/admin/cell-logs',
        );
        expect(transferred?.props('value')).toBe(3);
        expect(transferred?.props('query')).toEqual({
            action: ['transferred_out', 'transferred_in'],
            date_from: '2026-08-13',
            date_to: '2026-08-13',
        });
    });

    it('renders every section heading', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('dashboard.occupancy.title'));
        expect(wrapper.text()).toContain(t('dashboard.expiring.title'));
        expect(wrapper.text()).toContain(t('dashboard.activityToday.title'));
    });
});

import { Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { smtp } from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import StoreDashboardChart from '@/components/StoreDashboardChart.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import { storeDashboardProps, storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Index from './Index.vue';

vi.mock(
    'chart.js',
    async () => (await import('@/testing/chartMock')).chartModuleMock,
);
const { permissions } = vi.hoisted(() => ({
    permissions: { value: ['admin_dashboard', 'smtp_settings'] },
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');

    return {
        Link: createLinkStub(),
        Head: headStub,
        usePage: () => ({
            url: '/temp/admin',
            props: defaultAuthProps({
                store: storeSharedProps({ permissions: permissions.value }),
            }),
        }),
        router: { get: vi.fn(), post: vi.fn() },
    };
});
beforeEach(() => {
    permissions.value = ['admin_dashboard', 'smtp_settings'];
    vi.stubGlobal(
        'matchMedia',
        vi.fn(() => ({
            matches: false,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        })),
    );
});
afterEach(() => {
    i18n.global.locale.value = 'en';
    vi.unstubAllGlobals();
});
it.each(['en', 'ar'] as const)(
    'full mounts all dashboard labels, chart props and storefront links in %s',
    (locale) => {
        i18n.global.locale.value = locale;
        const props = storeDashboardProps();
        const wrapper = mount(Index, { props });
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.findAll('dd').map((node) => node.text())).toEqual([
            '8',
            '12',
            '3',
            '4',
        ]);

        for (const key of [
            'title',
            'customers',
            'orders',
            'categories',
            'brands',
            'topProducts',
            'smtpWarning',
            'configureNow',
            'category',
            'count',
            'numberOfSales',
            'numberOfStock',
            'numberOfVisits',
        ]) {
            expect(wrapper.text()).toContain(t(`store.dashboard.${key}`));
        }

        const charts = wrapper.findAllComponents(StoreDashboardChart);
        expect(charts).toHaveLength(5);
        const labels = [
            [
                t('store.dashboard.totalProducts'),
                t('store.dashboard.activeProducts'),
            ],
            [
                t('store.dashboard.apiProducts'),
                t('store.dashboard.adminProducts'),
            ],
            ['Oils'],
            ['Oils'],
            ['SY'],
        ];
        const values = [
            props.series.products,
            props.series.sources,
            props.series.sales.values,
            props.series.stock.values,
            props.series.visits.values,
        ];
        const valueKeys = [
            'count',
            'count',
            'numberOfSales',
            'numberOfStock',
            'numberOfVisits',
        ];
        ['products', 'sources', 'sales', 'stock', 'visits'].forEach(
            (key, index) => {
                expect(charts[index].props()).toEqual({
                    title: t(`store.dashboard.charts.${key}`),
                    type: index < 2 ? 'doughnut' : 'bar',
                    labels: labels[index],
                    values: values[index],
                    valueLabel: t(`store.dashboard.${valueKeys[index]}`),
                    categoryLabel: t('store.dashboard.category'),
                });
                expect(wrapper.text()).toContain(
                    t(`store.dashboard.charts.${key}`),
                );
            },
        );
        expect(wrapper.get('article a').element.tagName).toBe('A');
        expect(wrapper.get('article a').attributes('href')).toBe(
            props.topProducts[0].public_url,
        );
        expect(wrapper.get('article img').attributes()).toMatchObject({
            src: '/oil.png',
            alt: 'Olive oil',
            loading: 'lazy',
        });
        expect(wrapper.get('del').text()).toBe('€22.00');
        expect(wrapper.get('article').text()).toContain('€19.80');
        expect(wrapper.get('article').text()).toContain(
            t('store.dashboard.rating', { rating: 4.5 }),
        );
        expect(
            wrapper
                .findAllComponents(Link)
                .some(
                    (link) =>
                        JSON.stringify(link.props('href')) ===
                        JSON.stringify(smtp()),
                ),
        ).toBe(true);
        wrapper.unmount();
    },
);
it('hides SMTP without its flag or permission and renders the empty product state', () => {
    permissions.value = ['admin_dashboard'];
    const wrapper = mount(Index, {
        props: { ...storeDashboardProps(), topProducts: [] },
    });
    expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    expect(wrapper.text()).toContain(t('store.dashboard.emptyProducts'));
    wrapper.unmount();
    permissions.value = ['admin_dashboard', 'smtp_settings'];
    const hidden = mount(Index, {
        props: { ...storeDashboardProps(), showSmtpAlert: false },
    });
    expect(hidden.find('[role="alert"]').exists()).toBe(false);
    hidden.unmount();
});
it('gates the dashboard body and does not show an undiscounted price twice', () => {
    const props = storeDashboardProps();
    props.topProducts[0].discounted_price = props.topProducts[0].base_price;
    const wrapper = mount(Index, { props });
    expect(wrapper.find('del').exists()).toBe(false);
    wrapper.unmount();
    permissions.value = [];
    const denied = mount(Index, { props });
    expect(denied.findAllComponents(StoreDashboardChart)).toHaveLength(0);
    expect(denied.find('article').exists()).toBe(false);
    denied.unmount();
});

it('keeps products without a storefront slug readable without inventing a link', () => {
    const props = storeDashboardProps();
    props.topProducts[0].public_url = null;
    const wrapper = mount(Index, { props });
    expect(wrapper.find('article a').exists()).toBe(false);
    expect(wrapper.get('article').text()).toContain('Olive oil');
    wrapper.unmount();
});

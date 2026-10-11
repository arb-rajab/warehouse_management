import { mount } from '@vue/test-utils';
import { expect, it, vi } from 'vitest';
import StoreDashboardChart from '@/components/StoreDashboardChart.vue';
import { storeSharedProps } from '@/testing/factories';
import Index from './Index.vue';
vi.mock('chart.js/auto', () => ({
    default: class {
        destroy() {}
    },
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, createFormStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        Form: createFormStub(() => ({ errors: {}, processing: false })),
        usePage: () => ({
            url: '/temp/admin',
            props: {
                locale: 'en',
                auth: { user: { name: 'Admin' } },
                store: storeSharedProps({ isSuperAdmin: true }),
            },
        }),
        router: { post: vi.fn() },
    };
});
it('mounts dashboard metrics, four charts, SMTP alert and safe public product cards', () => {
    const wrapper = mount(Index, {
        props: {
            stats: {
                customers: 2,
                orders: 4,
                categories: 1,
                brands: 3,
                products: 10,
                active_products: 6,
                api_products: 7,
                local_products: 3,
            },
            charts: { labels: ['Root'], sales: [4], stock: [20] },
            smtp_missing: true,
            topProducts: [
                {
                    id: 1,
                    name: '<b>Product</b>',
                    url: '/temp/product/product',
                    image: null,
                    base_price: '€12.00',
                    price: '€10.00',
                    rating: 4.5,
                },
            ],
        },
    });
    expect(wrapper.get('h1').text()).toBe('Dashboard');
    expect(wrapper.findAllComponents(StoreDashboardChart)).toHaveLength(4);
    expect(
        wrapper.findAllComponents(StoreDashboardChart)[0].props('values'),
    ).toEqual([6, 4]);
    expect(wrapper.text()).toContain('Top 12 Products');
    expect(wrapper.get('a[href="/temp/product/product"]').text()).toContain(
        '<b>Product</b>',
    );
    expect(
        wrapper.get('a[href="/temp/product/product"]').find('b').exists(),
    ).toBe(false);
    expect(wrapper.text()).toContain('SMTP');
});

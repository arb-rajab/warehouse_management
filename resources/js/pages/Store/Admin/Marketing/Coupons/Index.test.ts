import { Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    create,
    destroy,
    edit,
    index as couponIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/CouponController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { paginated, storeCoupon, storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Index from './Index.vue';

const { usePageMock, routerGetMock, confirmMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
    confirmMock: vi.fn(),
}));
vi.mock('@/lib/confirm', () => ({
    confirmDelete: confirmMock,
    confirmLogout: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { get: routerGetMock, post: vi.fn() },
    };
});
const coupon = storeCoupon();
function mountPage(
    permissions = ['add_coupon', 'edit_coupon', 'delete_coupon'],
    empty = false,
) {
    usePageMock.mockReturnValue({
        url: couponIndex.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, {
        props: {
            coupons: paginated(empty ? [] : [coupon]),
            filters: { per_page: 10 },
        },
    });
}
describe('Store coupon Index', () => {
    beforeEach(() => vi.clearAllMocks());
    it('renders the store layout translated title every column and full row', () => {
        const wrapper = mountPage();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.get('h1').text()).toBe(t('store.coupons.index.title'));
        expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual(
            ['code', 'type', 'discount', 'startDate', 'endDate', 'actions'].map(
                (key) => t(`store.coupons.index.${key}`),
            ),
        );

        for (const value of [
            'SPRING',
            t('store.coupons.fields.cart'),
            '10 %',
            '2026-10-01',
            '2026-10-31',
        ]) {
            expect(wrapper.text()).toContain(value);
        }

        expect(mountPage([], true).text()).toContain(
            t('store.coupons.index.empty'),
        );
    });
    it('gates all actions and wires routes with DELETE query preservation and native confirmation', () => {
        const wrapper = mountPage();
        expect(wrapper.getComponent(AddResourceLink).props('href')).toEqual(
            create(),
        );
        const links = wrapper.findAllComponents(TableActionLink);
        expect(links[0].props('href')).toEqual(edit(coupon));
        expect(links[0].text()).toContain(t('store.coupons.index.edit'));
        expect(links[1].props()).toMatchObject({
            href: destroy(coupon, { mergeQuery: {} }),
            variant: 'danger',
        });
        expect(links[1].attributes('method')).toBe('delete');
        expect(links[1].getComponent(Link).props('as')).toBe('button');
        const onBefore = links[1]
            .getComponent(Link)
            .props('onBefore') as () => boolean;
        onBefore();
        expect(confirmMock).toHaveBeenCalledWith(coupon.code);
        expect(mountPage([]).findComponent(AddResourceLink).exists()).toBe(
            false,
        );
        expect(mountPage([]).findAllComponents(TableActionLink)).toHaveLength(
            0,
        );
    });
    it('passes pagination props and sends a server per-page visit', () => {
        const wrapper = mountPage();
        const pagination = wrapper.getComponent(Pagination);
        expect(pagination.props()).toMatchObject({
            perPage: 10,
            links: paginated([coupon]).meta.links,
        });
        pagination.vm.$emit('update:per-page', 50);
        expect(routerGetMock).toHaveBeenCalledWith(
            couponIndex.url(),
            { per_page: 50 },
            { preserveState: true, replace: true },
        );
    });
});

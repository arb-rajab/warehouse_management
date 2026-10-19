import { Form, Link } from '@inertiajs/vue3';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { ComponentPublicInstance } from 'vue';
import {
    approved,
    ban,
    bulkDestroy,
    destroy,
    edit,
    index as sellersIndex,
    login,
    verification,
} from '@/actions/App/Http/Controllers/Store/Admin/SellerController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import Pagination from '@/components/Pagination.vue';
import StoreSellerPaymentDialog from '@/components/StoreSellerPaymentDialog.vue';
import StoreSellerProfileDialog from '@/components/StoreSellerProfileDialog.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { paginated, storeSeller, storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type { StoreSeller } from '@/types/store';
import Index from './Index.vue';

const { usePageMock, routerGetMock, formSlotProps } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
    formSlotProps: vi.fn(() => ({ errors: {}, processing: false })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, createUseHttpStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Form: createFormStub(formSlotProps),
        Link: createLinkStub(),
        Head: headStub,
        usePage: usePageMock,
        useHttp: (data: object) => createUseHttpStub(data),
        router: { post: vi.fn(), get: routerGetMock },
    };
});
vi.mock('@/lib/confirm', async (importOriginal) => ({
    ...(await importOriginal<Record<string, unknown>>()),
    confirmDelete: vi.fn(() => true),
}));
const permissions = [
    'view_seller_profile',
    'edit_seller',
    'delete_seller',
    'ban_seller',
    'login_as_seller',
    'pay_to_seller',
    'seller_payment_history',
];
function mountPage(
    allowed = permissions,
    seller = storeSeller(),
    payouts = true,
) {
    usePageMock.mockReturnValue({
        url: sellersIndex.url(),
        props: defaultAuthProps({
            store: storeSharedProps({ permissions: allowed }),
        }),
    });

    return mount(Index, {
        props: {
            sellers: paginated([seller]),
            filters: { search: '', approved_status: null, per_page: 20 },
            payouts,
            payment_links: payouts,
        },
    });
}
describe('seller list', () => {
    beforeEach(() => vi.clearAllMocks());
    it('fully mounts the shell table pagination and every visible static label', () => {
        const wrapper = mountPage();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(
            wrapper
                .getComponent<
                    ComponentPublicInstance<{
                        rows: StoreSeller[];
                        columns: string[];
                    }>
                >(DataTable)
                .props('rows'),
        ).toEqual([storeSeller()]);
        expect(wrapper.getComponent(Pagination).props()).toMatchObject({
            perPage: 20,
            links: [],
        });

        for (const key of [
            'title',
            'add',
            'search',
            'filterApproval',
            'all',
            'approved',
            'unapproved',
            'select',
            'selectAll',
            'shopName',
            'phone',
            'email',
            'verificationInfo',
            'approval',
            'products_count',
            'due',
            'options',
            'show',
            'profile',
            'login',
            'payment',
            'paymentHistory',
            'edit',
            'ban',
            'delete',
        ]) {
            expect(wrapper.text()).toContain(t(`store.sellers.${key}`));
        }

        const links = wrapper
            .findAllComponents(TableActionLink)
            .map((link) => link.props('href'));
        expect(links).toEqual([
            verification({ id: 17 }),
            edit({ id: 17 }),
            ban({ id: 17 }, { mergeQuery: {} }),
            destroy({ id: 17 }, { mergeQuery: {} }),
        ]);
        expect(wrapper.getComponent(Form).props('action')).toEqual(
            approved({ mergeQuery: {} }),
        );
        expect(
            wrapper.find(`a[href="${login.url({ id: 17 })}"]`).exists(),
        ).toBe(true);
        const payment = wrapper
            .findAll('button')
            .find((button) => button.text() === t('store.sellers.payment'));
        expect(payment).toBeDefined();
    });
    it('opens the Vue payment dialog for the selected shop', async () => {
        const wrapper = mountPage();
        await wrapper
            .findAll('button')
            .find((button) => button.text() === t('store.sellers.payment'))!
            .trigger('click');
        expect(
            wrapper.getComponent(StoreSellerPaymentDialog).props(),
        ).toMatchObject({ open: true, shopId: 17 });
    });
    it('hides every unpermitted action including profile fetch login and approval', () => {
        const wrapper = mountPage([]);
        expect(wrapper.findComponent(AddResourceLink).exists()).toBe(false);
        expect(wrapper.findComponent(StoreSellerProfileDialog).exists()).toBe(
            false,
        );
        expect(wrapper.find('button[role="switch"]').exists()).toBe(false);
        expect(wrapper.findAllComponents(TableActionLink)).toHaveLength(1);
        expect(
            wrapper.find(`a[href="${login.url({ id: 17 })}"]`).exists(),
        ).toBe(false);
        expect(
            wrapper
                .findAll('button')
                .some((button) => button.text() === t('store.sellers.payment')),
        ).toBe(false);
    });
    it('uses edit_seller for approval and keeps payout links behind their setting', () => {
        const wrapper = mountPage(
            ['approve_seller', 'pay_to_seller', 'seller_payment_history'],
            storeSeller(),
            false,
        );
        expect(wrapper.find('button[role="switch"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain(t('store.sellers.payment'));
        expect(
            wrapper
                .getComponent<
                    ComponentPublicInstance<{
                        rows: StoreSeller[];
                        columns: string[];
                    }>
                >(DataTable)
                .props('columns'),
        ).not.toContain(t('store.sellers.due'));
    });
    it('wires query-preserving deletes bulk selection and native ban confirms', async () => {
        const nativeConfirm = vi
            .spyOn(window, 'confirm')
            .mockReturnValue(false);
        const wrapper = mountPage();
        const actions = wrapper.findAllComponents(TableActionLink);
        const deleteBefore = actions[3]
            .getComponent(Link)
            .props('onBefore') as () => boolean;
        deleteBefore();
        expect(confirmDelete).toHaveBeenCalledWith('Acme shop');
        const banBefore = actions[2]
            .getComponent(Link)
            .props('onBefore') as () => boolean;
        expect(banBefore()).toBe(false);
        expect(nativeConfirm).toHaveBeenCalledWith(
            t('store.sellers.confirmBan'),
        );
        await wrapper
            .findAll('label')
            .find((label) => label.text() === t('store.sellers.selectAll'))!
            .get('input')
            .setValue(true);
        const bulk = wrapper
            .findAllComponents(Form)
            .find(
                (form) =>
                    JSON.stringify(form.props('action')) ===
                    JSON.stringify(bulkDestroy({ mergeQuery: {} })),
            )!;
        expect(bulk.get('input[name="id[]"]').attributes('value')).toBe('17');
        (bulk.props('onBefore') as () => boolean)();
        expect(confirmDelete).toHaveBeenCalledWith(
            t('store.sellers.selection'),
        );
        nativeConfirm.mockRestore();
    });
    it('opens the profile dialog and submits both filters and page-size changes', async () => {
        const wrapper = mountPage();
        const button = wrapper
            .findAll('button')
            .find((button) => button.text() === t('store.sellers.profile'))!;
        await button.trigger('click');
        await flushPromises();
        expect(
            wrapper.getComponent(StoreSellerProfileDialog).props(),
        ).toMatchObject({ open: true, sellerId: 17 });
        await wrapper.get('main input[type="search"]').setValue('Sam');
        await wrapper.get('main select').setValue('0');
        await wrapper.get('main form').trigger('submit');
        expect(routerGetMock).toHaveBeenLastCalledWith(
            sellersIndex.url(),
            { search: 'Sam', approved_status: '0', per_page: 20 },
            { preserveState: true, replace: true },
        );
        wrapper.getComponent(Pagination).vm.$emit('update:perPage', 50);
        expect(routerGetMock).toHaveBeenLastCalledWith(
            sellersIndex.url(),
            { search: 'Sam', approved_status: '0', per_page: 50 },
            { preserveState: true, replace: true },
        );
    });
    it('renders ban state due-to-admin text and seller text safely', () => {
        const wrapper = mountPage(
            permissions,
            storeSeller({
                name: '<script>seller</script>',
                banned: true,
                due_to_admin: true,
                approved: true,
            }),
        );
        expect(wrapper.find('script').exists()).toBe(false);
        expect(wrapper.text()).toContain('<script>seller</script>');
        expect(wrapper.text()).toContain(t('store.sellers.dueToAdmin'));
        expect(wrapper.text()).toContain(t('store.sellers.unban'));
        expect(
            wrapper.get('button[role="switch"]').attributes('aria-checked'),
        ).toBe('true');
    });
});

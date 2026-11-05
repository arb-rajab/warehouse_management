import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { ComponentPublicInstance } from 'vue';
import { edit as sellerEdit } from '@/actions/App/Http/Controllers/Store/Admin/SellerController';
import { show } from '@/actions/App/Http/Controllers/Store/Admin/SellerPaymentController';
import { index as withdrawalsIndex } from '@/actions/App/Http/Controllers/Store/Admin/SellerWithdrawRequestController';
import DataTable from '@/components/DataTable.vue';
import Pagination from '@/components/Pagination.vue';
import StoreSellerPaymentDialog from '@/components/StoreSellerPaymentDialog.vue';
import StoreSellerWithdrawMessageDialog from '@/components/StoreSellerWithdrawMessageDialog.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import {
    paginated,
    storeSellerWithdrawal,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import {
    expectColumnCanBeHidden,
    expectColumnLocked,
} from '@/testing/tableColumns';
import type { StoreSellerWithdrawal } from '@/types/store';
import Index from './Index.vue';

const { usePage, get } = vi.hoisted(() => ({ usePage: vi.fn(), get: vi.fn() }));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, createUseHttpStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Link: createLinkStub(),
        Head: headStub,
        usePage,
        router: { get },
        useHttp: (data: object) => createUseHttpStub(data),
    };
});
function access(permissions: string[]) {
    usePage.mockReturnValue({
        url: '/temp/admin/sellers',
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });
}

describe('seller withdrawals page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        access([
            'pay_to_seller',
            'view_seller_payout_requests',
            'seller_payment_history',
        ]);
    });
    it('mounts real layout table pagination every label and typed dialogs', async () => {
        const row = storeSellerWithdrawal();
        const w = mount(Index, {
            props: { withdrawals: paginated([row]), filters: { per_page: 20 } },
        });
        expect(w.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(
            w
                .getComponent<
                    ComponentPublicInstance<{ rows: StoreSellerWithdrawal[] }>
                >(DataTable)
                .props('rows'),
        ).toEqual([row]);
        expect(w.getComponent(Pagination).props()).toMatchObject({
            perPage: 20,
            links: [],
        });

        for (const key of [
            'withdrawalsTitle',
            'id',
            'date',
            'seller',
            'due',
            'requestedAmount',
            'message',
            'status',
            'options',
            'pending',
            'payNow',
            'viewMessage',
            'paymentHistory',
        ]) {
            expect(w.text()).toContain(t(`store.sellers.${key}`));
        }

        expect(w.getComponent(TableActionLink).props('href')).toEqual(
            show(row.seller),
        );
        await w
            .findAll('button')
            .find((b) => b.text() === t('store.sellers.payNow'))!
            .trigger('click');
        expect(w.getComponent(StoreSellerPaymentDialog).props()).toMatchObject({
            open: true,
            shopId: null,
            withdrawal: row,
        });
        await w
            .findAll('button')
            .find((b) => b.text() === t('store.sellers.viewMessage'))!
            .trigger('click');
        expect(
            w.getComponent(StoreSellerWithdrawMessageDialog).props(),
        ).toMatchObject({ open: true, withdrawal: row });
        w.getComponent(Pagination).vm.$emit('per-page-change', 10);
        expect(get).toHaveBeenCalledWith(
            withdrawalsIndex['/temp/admin/withdraw_requests_all'].url(),
            { per_page: 10 },
            { preserveState: true, replace: true },
        );
    });
    it('gates pay message and history independently and displays paid status', () => {
        access(['view_seller_payout_requests']);
        const w = mount(Index, {
            props: {
                withdrawals: paginated([storeSellerWithdrawal({ paid: true })]),
                filters: { per_page: 20 },
            },
        });
        expect(w.text()).toContain(t('store.sellers.paid'));
        expect(w.text()).not.toContain(t('store.sellers.payNow'));
        expect(w.text()).toContain(t('store.sellers.viewMessage'));
        expect(w.findComponent(TableActionLink).exists()).toBe(false);
        access([]);
        const denied = mount(Index, {
            props: {
                withdrawals: paginated([storeSellerWithdrawal()]),
                filters: { per_page: 20 },
            },
        });
        expect(denied.text()).not.toContain(t('store.sellers.viewMessage'));
        const empty = mount(Index, {
            props: { withdrawals: paginated([]), filters: { per_page: 20 } },
        });
        expect(empty.text()).toContain(t('store.sellers.noWithdrawals'));
    });

    it('links the seller to their edit page only with edit_seller and a shop id', () => {
        const sellerCell = (w: ReturnType<typeof mount>) =>
            w.get('tbody tr').findAll('td')[2];
        const props = (shopId: number | null) => ({
            withdrawals: paginated([
                storeSellerWithdrawal({
                    seller: {
                        ...storeSellerWithdrawal().seller,
                        shop_id: shopId,
                    },
                }),
            ]),
            filters: { per_page: 20 },
        });

        access(['view_seller_payout_requests', 'edit_seller']);
        const allowed = mount(Index, { props: props(9) });
        expect(sellerCell(allowed).get('a').attributes('href')).toBe(
            sellerEdit(9).url,
        );
        expect(sellerCell(allowed).text()).toContain('Acme shop');

        const noShop = mount(Index, { props: props(null) });
        expect(sellerCell(noShop).find('a').exists()).toBe(false);

        access(['view_seller_payout_requests']);
        const denied = mount(Index, { props: props(9) });
        expect(sellerCell(denied).find('a').exists()).toBe(false);
        expect(sellerCell(denied).text()).toContain('Sam Seller');
    });

    it('lets staff hide an optional column from the header and every row, and keeps the identifier', async () => {
        window.localStorage.clear();
        const wrapper = mount(Index, {
            props: {
                withdrawals: paginated([storeSellerWithdrawal()]),
                filters: { per_page: 20 },
            },
        });
        await expectColumnCanBeHidden(wrapper, 'sellers.withdrawals', {
            key: 'message',
            label: t('store.sellers.message'),
        });
        expectColumnLocked(wrapper, t('store.sellers.seller'));
        window.localStorage.clear();
    });
});

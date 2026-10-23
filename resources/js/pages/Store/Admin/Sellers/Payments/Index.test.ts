import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { ComponentPublicInstance } from 'vue';
import {
    index as paymentsIndex,
    show,
} from '@/actions/App/Http/Controllers/Store/Admin/SellerPaymentController';
import DataTable from '@/components/DataTable.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableLink from '@/components/TableLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import {
    paginated,
    storeSellerPayment,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type { StoreSellerPayment } from '@/types/store';
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

describe('seller payments page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        access(['seller_payment_history']);
    });
    it('fully mounts the layout table pagination labels details and permitted seller link', () => {
        const payment = storeSellerPayment();
        const w = mount(Index, {
            props: {
                payments: paginated([payment]),
                seller: null,
                filters: { per_page: 20 },
            },
        });
        expect(w.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(
            w
                .getComponent<
                    ComponentPublicInstance<{ rows: StoreSellerPayment[] }>
                >(DataTable)
                .props('rows'),
        ).toEqual([payment]);
        expect(w.getComponent(Pagination).props()).toMatchObject({
            links: [],
            perPage: 20,
        });

        for (const key of [
            'paymentsTitle',
            'id',
            'date',
            'seller',
            'amount',
            'paymentDetails',
            'txnCode',
        ]) {
            expect(w.text()).toContain(t(`store.sellers.${key}`));
        }

        for (const value of [
            'Sam Seller',
            'Acme shop',
            '€12.50',
            'Bank payment',
            'T-1',
        ]) {
            expect(w.text()).toContain(value);
        }

        expect(w.getComponent(TableLink).props('href')).toEqual(
            show(payment.seller),
        );
        w.getComponent(Pagination).vm.$emit('per-page-change', 10);
        expect(get).toHaveBeenCalledWith(
            paymentsIndex.url(),
            { per_page: 10 },
            { preserveState: true, replace: true },
        );
    });
    it('uses the scoped route for pagination and the all payments back link', () => {
        const payment = storeSellerPayment();
        const w = mount(Index, {
            props: {
                payments: paginated([payment]),
                seller: payment.seller,
                filters: { per_page: 20 },
            },
        });
        expect(w.text()).toContain(t('store.sellers.allPayments'));
        expect(w.findAllComponents(TableActionLink)[0].props('href')).toEqual(
            paymentsIndex(),
        );
        w.getComponent(Pagination).vm.$emit('per-page-change', 10);
        expect(get).toHaveBeenCalledWith(
            show.url(payment.seller),
            { per_page: 10 },
            { preserveState: true, replace: true },
        );
    });
    it('hides unpermitted links and renders the empty state', () => {
        access([]);
        const w = mount(Index, {
            props: {
                payments: paginated([storeSellerPayment({ txn_code: null })]),
                seller: null,
                filters: { per_page: 20 },
            },
        });
        expect(w.findComponent(TableLink).exists()).toBe(false);
        const empty = mount(Index, {
            props: {
                payments: paginated([]),
                seller: null,
                filters: { per_page: 20 },
            },
        });
        expect(empty.text()).toContain(t('store.sellers.noPayments'));
    });
});

import { Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { ComponentPublicInstance, DefineComponent } from 'vue';
import * as actions from '@/actions/App/Http/Controllers/Store/Admin/ReportController';
import DataTable from '@/components/DataTable.vue';
import FilterMultiSelect from '@/components/FilterMultiSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreReportPage from '@/components/StoreReportPage.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { formatDate, formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import {
    paginated,
    storeProductReportRow,
    storeSellerReportRow,
    storeSearchReportRow,
    storeCommissionReportRow,
    storeWalletReportRow,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import CommissionHistory from './CommissionHistory.vue';
import InHouseSales from './InHouseSales.vue';
import Searches from './Searches.vue';
import SellerSales from './SellerSales.vue';
import Stock from './Stock.vue';
import WalletHistory from './WalletHistory.vue';
import Wishlist from './Wishlist.vue';

const { usePageMock, getMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    getMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { get: getMock, post: vi.fn() },
    };
});

const cases = [
    {
        page: InHouseSales,
        title: 'inHouseSalesTitle',
        action: actions.in_house_sale_report,
        row: storeProductReportRow(),
        columns: ['#', 'productName', 'sales'],
        cells: ['1', 'Olive oil', '12'],
        selection: 'category_ids',
        label: 'category',
    },
    {
        page: Stock,
        title: 'stockTitle',
        action: actions.stock_report,
        row: storeProductReportRow(),
        columns: ['productName', 'stock'],
        cells: ['Olive oil', '12'],
        selection: 'category_ids',
        label: 'category',
    },
    {
        page: Wishlist,
        title: 'wishlistTitle',
        action: actions.wish_report,
        row: storeProductReportRow(),
        columns: ['productName', 'wishes'],
        cells: ['Olive oil', '12'],
        selection: 'category_ids',
        label: 'category',
    },
    {
        page: SellerSales,
        title: 'sellerSalesTitle',
        action: actions.seller_sale_report,
        row: storeSellerReportRow(),
        columns: ['sellerName', 'shopName', 'sales', 'orderAmount'],
        cells: ['Sam Seller', 'Sam shop', '14', '€125.00'],
        selection: 'verification_statuses',
        label: 'verification',
    },
    {
        page: Searches,
        title: 'searchesTitle',
        action: actions.user_search_report,
        row: storeSearchReportRow(),
        columns: ['#', 'searchBy', 'searchCount'],
        cells: ['1', 'Olive oil', '9'],
    },
    {
        page: CommissionHistory,
        title: 'commissionHistoryTitle',
        action: actions.commission_history,
        row: storeCommissionReportRow(),
        columns: [
            '#',
            'orderCode',
            'adminCommission',
            'sellerEarning',
            'createdAt',
        ],
        cells: [
            '1',
            '20261008-101',
            '3.50',
            '31.50',
            formatDateTime('2026-10-08T10:30:00Z'),
        ],
        selection: 'seller_ids',
        label: 'seller',
    },
    {
        page: WalletHistory,
        title: 'walletHistoryTitle',
        action: actions.wallet_transaction_history,
        row: storeWalletReportRow(),
        columns: ['#', 'customer', 'createdAt', 'amount', 'paymentMethod'],
        cells: [
            '1',
            'Sam Customer',
            formatDate('2026-10-08T10:30:00Z'),
            '€100.00',
            'Cash on delivery',
        ],
        selection: 'user_ids',
        label: 'user',
    },
];

describe('Store report screens', () => {
    beforeEach(() => {
        getMock.mockReset();
        usePageMock.mockReturnValue({
            url: '/temp/admin/report',
            props: defaultAuthProps({ store: storeSharedProps() }),
        });
    });

    for (const entry of cases) {
        it(`full mounts ${entry.title} with narrow rows, labels and pagination`, async () => {
            const rows = paginated([entry.row]);
            const filters = { per_page: 50 };
            const options = [{ value: '1', label: 'Selected option' }];
            const wrapper = mount<
                typeof entry.page,
                DefineComponent<Record<string, unknown>>
            >(entry.page, {
                props: {
                    rows,
                    filters,
                    ...(entry.selection ? { options } : {}),
                },
            });
            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
            expect(wrapper.getComponent(Head).props('title')).toBe(
                t(`store.reports.${entry.title}`),
            );
            expect(wrapper.get('h1').text()).toBe(
                t(`store.reports.${entry.title}`),
            );
            expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual(
                entry.columns.map((key) =>
                    key === '#' ? '#' : t(`store.reports.${key}`),
                ),
            );
            expect(wrapper.findAll('tbody td').map((td) => td.text())).toEqual(
                entry.cells,
            );
            expect(
                wrapper
                    .getComponent<
                        ComponentPublicInstance<Record<string, unknown>>
                    >(DataTable)
                    .props('rows'),
            ).toEqual(rows.data);
            expect(wrapper.getComponent(Pagination).props()).toEqual({
                async: false,
                links: [],
                perPage: 50,
            });
            expect(wrapper.getComponent(StoreReportPage).props('url')).toBe(
                entry.action.url(),
            );
            wrapper.getComponent(Pagination).vm.$emit('update:perPage', 10);
            expect(getMock.mock.calls[0][0]).toBe(entry.action.url());
            expect(getMock.mock.calls[0][1]).toMatchObject({ per_page: 10 });

            if (entry.selection) {
                await wrapper
                    .getComponent(PageHeader)
                    .get('button')
                    .trigger('click');
                expect(
                    wrapper.getComponent(FilterMultiSelect).props(),
                ).toMatchObject({
                    options:
                        entry.selection === 'verification_statuses'
                            ? [
                                  {
                                      value: '1',
                                      label: t('store.reports.approved'),
                                  },
                                  {
                                      value: '0',
                                      label: t('store.reports.nonApproved'),
                                  },
                              ]
                            : options,
                    label: t(`store.reports.${entry.label}`),
                    modelValue: [],
                });
            }

            await wrapper.setProps({ rows: paginated([]) });
            expect(wrapper.text()).toContain(t('store.reports.empty'));
            wrapper.unmount();
        });
    }

    it('shows translated missing order/customer fallbacks without the removed wallet approval column', () => {
        const commission = mount(CommissionHistory, {
            props: {
                rows: paginated([
                    storeCommissionReportRow({ order_code: null }),
                ]),
                filters: {},
                options: [],
            },
        });
        expect(commission.text()).toContain(t('store.reports.orderDeleted'));
        const wallet = mount(WalletHistory, {
            props: {
                rows: paginated([
                    storeWalletReportRow({
                        user_name: null,
                        payment_method: null,
                    }),
                ]),
                filters: {},
                options: [],
            },
        });
        expect(wallet.text()).toContain(t('store.reports.userNotFound'));
        expect(wallet.findAll('thead th')).toHaveLength(5);
        expect(wallet.findAll('tbody td').at(-1)?.text()).toBe('—');
    });

    it('escapes search and order data as text', () => {
        const searches = mount(Searches, {
            props: {
                rows: paginated([
                    storeSearchReportRow({
                        query: '<img src=x onerror=alert(1)>',
                    }),
                ]),
                filters: {},
            },
        });
        expect(searches.find('tbody img').exists()).toBe(false);
        expect(searches.find('tbody').text()).toContain(
            '<img src=x onerror=alert(1)>',
        );
    });
});

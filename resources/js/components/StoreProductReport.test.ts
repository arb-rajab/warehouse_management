import { mount } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import type { ComponentPublicInstance } from 'vue';
import DataTable from '@/components/DataTable.vue';
import StoreReportPage from '@/components/StoreReportPage.vue';
import { t } from '@/lib/i18n';
import {
    paginated,
    storeProductReportRow,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import StoreProductReport from './StoreProductReport.vue';

const { usePageMock } = vi.hoisted(() => ({ usePageMock: vi.fn() }));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { get: vi.fn(), post: vi.fn() },
    };
});
beforeEach(() =>
    usePageMock.mockReturnValue({
        url: '/temp/admin/report',
        props: defaultAuthProps({ store: storeSharedProps() }),
    }),
);

it('passes the product filters and table payload and renders paginated numbering', () => {
    const rows = paginated([storeProductReportRow()], 20, { currentPage: 2 });
    const options = [{ value: '3', label: 'Groceries' }];
    const filters = { category_ids: ['3'], per_page: 20 };
    const wrapper = mount(StoreProductReport, {
        props: {
            title: 'Stock',
            metricLabel: 'Stock',
            url: '/temp/admin/report',
            rows,
            options,
            filters,
            numbered: true,
        },
    });
    expect(wrapper.getComponent(StoreReportPage).props()).toMatchObject({
        title: 'Stock',
        url: '/temp/admin/report',
        filters,
        links: [],
        selection: 'category_ids',
        selectionLabel: t('store.reports.category'),
        options,
    });
    expect(
        wrapper
            .getComponent<ComponentPublicInstance<Record<string, unknown>>>(
                DataTable,
            )
            .props(),
    ).toMatchObject({
        columns: ['#', t('store.reports.productName'), 'Stock'],
        rows: rows.data,
        emptyMessage: t('store.reports.empty'),
    });
    expect(wrapper.findAll('tbody td').map((cell) => cell.text())).toEqual([
        '21',
        'Olive oil',
        '12',
    ]);
});

it('escapes product names and renders the empty state', () => {
    const props = {
        title: 'Stock',
        metricLabel: 'Stock',
        url: '/temp/admin/report',
        options: [],
        filters: {},
    };
    const wrapper = mount(StoreProductReport, {
        props: {
            ...props,
            rows: paginated([
                storeProductReportRow({ name: '<script>alert(1)</script>' }),
            ]),
        },
    });
    expect(wrapper.find('tbody script').exists()).toBe(false);
    expect(wrapper.find('tbody').text()).toContain('<script>alert(1)</script>');
    const empty = mount(StoreProductReport, {
        props: { ...props, rows: paginated([]) },
    });
    expect(empty.text()).toContain(t('store.reports.empty'));
});

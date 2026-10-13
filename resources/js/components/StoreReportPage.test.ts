import { Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import DateRangeFilterFields from '@/components/DateRangeFilterFields.vue';
import FilterDialogFooter from '@/components/FilterDialogFooter.vue';
import FilterMultiSelect from '@/components/FilterMultiSelect.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type { StoreReportFilters } from '@/types/storeReports';
import StoreReportPage from './StoreReportPage.vue';

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

function mountReport(filters: StoreReportFilters = {}) {
    return mount(StoreReportPage, {
        props: {
            title: 'Report',
            url: '/temp/admin/report',
            filters,
            links: [],
            selection: 'seller_ids',
            selectionLabel: t('store.reports.seller'),
            options: [
                { value: '4', label: 'Seller A' },
                { value: '5', label: 'Seller B' },
            ],
            dates: true,
        },
        slots: { default: '<p>Report table</p>' },
    });
}

describe('StoreReportPage', () => {
    beforeEach(() => {
        getMock.mockReset();
        usePageMock.mockReturnValue({
            url: '/temp/admin/report',
            props: defaultAuthProps({ store: storeSharedProps() }),
        });
    });

    it('renders the title, layout, content and pagination props', () => {
        const wrapper = mountReport({ per_page: 50 });
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(Head).props('title')).toBe('Report');
        expect(wrapper.get('h1').text()).toBe('Report');
        expect(wrapper.text()).toContain('Report table');
        expect(wrapper.getComponent(Pagination).props()).toEqual({
            async: false,
            links: [],
            perPage: 50,
        });
    });

    it('renders every filter label and shared footer in the pinned slot', async () => {
        const wrapper = mountReport();
        await wrapper.getComponent(PageHeader).get('button').trigger('click');

        for (const key of [
            'common.filters.title',
            'common.filters.apply',
            'common.filters.clear',
            'store.reports.seller',
            'store.reports.all',
            'store.reports.dateFrom',
            'store.reports.dateTo',
            'store.reports.withinDays',
        ]) {
            expect(wrapper.text()).toContain(t(key));
        }

        expect(
            wrapper
                .get('[role=dialog] button[aria-label]')
                .attributes('aria-label'),
        ).toBe(t('common.filters.close'));
        expect(wrapper.getComponent(FilterDialogFooter).props('formId')).toBe(
            'store-report-filters',
        );
        expect(wrapper.getComponent(FilterMultiSelect).props()).toMatchObject({
            id: 'report-selection',
            options: [
                { value: '4', label: 'Seller A' },
                { value: '5', label: 'Seller B' },
            ],
            modelValue: [],
        });
        expect(wrapper.get('#report-date-from').attributes('type')).toBe(
            'date',
        );
        expect(wrapper.get('#report-within-days').attributes()).toMatchObject({
            type: 'number',
            min: '1',
            step: '1',
        });
    });

    it('submits all selected recipients, inclusive date boundaries and current page size', async () => {
        const wrapper = mountReport({
            seller_ids: ['4'],
            date_from: '2026-10-01',
            date_to: '2026-10-08',
            per_page: 25,
        });
        await wrapper.getComponent(PageHeader).get('button').trigger('click');
        const select = wrapper.getComponent(FilterMultiSelect);
        expect(select.props('modelValue')).toEqual(['4']);
        select.vm.$emit('update:modelValue', ['4', '5']);
        await wrapper.get('form').trigger('submit');
        expect(getMock).toHaveBeenCalledWith(
            '/temp/admin/report',
            {
                seller_ids: ['4', '5'],
                date_from: '2026-10-01',
                date_to: '2026-10-08',
                created_within_days: '',
                per_page: 25,
            },
            { preserveState: true, replace: true },
        );
    });

    it('makes date ranges and recent days mutually exclusive', async () => {
        const wrapper = mountReport({ created_within_days: 7 });
        await wrapper.getComponent(PageHeader).get('button').trigger('click');
        expect(
            wrapper.getComponent(DateRangeFilterFields).props(),
        ).toMatchObject({
            rangeDisabled: true,
            daysDisabled: false,
            withinDays: '7',
        });
        await wrapper.setProps({ filters: { date_from: '2026-10-01' } });
        expect(
            wrapper.getComponent(DateRangeFilterFields).props(),
        ).toMatchObject({
            rangeDisabled: false,
            daysDisabled: true,
            from: '2026-10-01',
            withinDays: '',
        });
    });

    it('clears all filters while preserving page size', async () => {
        const wrapper = mountReport({
            seller_ids: ['4'],
            date_from: '2026-10-01',
            per_page: 50,
        });
        await wrapper.getComponent(PageHeader).get('button').trigger('click');
        wrapper.getComponent(FilterDialogFooter).vm.$emit('clear');
        expect(getMock).toHaveBeenCalledWith(
            '/temp/admin/report',
            {
                seller_ids: [],
                date_from: '',
                date_to: '',
                created_within_days: '',
                per_page: 50,
            },
            { preserveState: true, replace: true },
        );
    });

    it('keeps filters and drops pagination when changing page size', () => {
        const wrapper = mountReport({
            seller_ids: ['5'],
            created_within_days: 3,
        });
        wrapper.getComponent(Pagination).vm.$emit('update:perPage', 10);
        expect(getMock).toHaveBeenCalledWith(
            '/temp/admin/report',
            {
                seller_ids: ['5'],
                date_from: '',
                date_to: '',
                created_within_days: '3',
                per_page: 10,
            },
            { preserveState: true, replace: true },
        );
    });
});

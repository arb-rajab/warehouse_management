import { Head } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    CalendarPlus,
    CalendarX,
    CircleDashed,
    PackageOpen,
} from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CELL_STATES, cellStateLabel } from '@/lib/cellStateColor';
import { t } from '@/lib/i18n';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import Dashboard from './Dashboard.vue';

const { usePageMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
    };
});

function mountPage() {
    usePageMock.mockReturnValue({
        url: '/admin/help/dashboard',
        props: defaultAuthProps(),
    });

    return mount(Dashboard);
}

describe('Help Dashboard', () => {
    beforeEach(() => {
        resetMocks({ usePageMock });
    });

    it('renders the page title in the Head and the PageHeader', () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('help.dashboard.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('help.dashboard.title'));
    });

    it('renders every section heading', () => {
        const wrapper = mountPage();

        const headings = wrapper.findAll('h2').map((h2) => h2.text());
        expect(headings).toEqual([
            t('help.dashboard.overview.heading'),
            t('help.dashboard.productFilter.heading'),
        ]);
    });

    it('explains what the dashboard shows', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('help.dashboard.overview.body'));
    });

    it('explains the product filter', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(
            t('help.dashboard.productFilter.body'),
        );
    });

    it('renders the cell-state legend from the real cell state colors', () => {
        const wrapper = mountPage();

        for (const state of CELL_STATES) {
            expect(wrapper.text()).toContain(cellStateLabel(state));
        }
    });

    it('renders representative expiring and activity stat tiles', () => {
        const wrapper = mountPage();

        expect(wrapper.findComponent(CalendarX).exists()).toBe(true);
        expect(wrapper.findComponent(CalendarPlus).exists()).toBe(true);
        expect(wrapper.findComponent(PackageOpen).exists()).toBe(true);
        expect(wrapper.findComponent(CircleDashed).exists()).toBe(true);
        expect(wrapper.findComponent(ArrowLeftRight).exists()).toBe(true);
        expect(wrapper.text()).toContain(t('dashboard.expiring.expired'));
        expect(wrapper.text()).toContain(t('cellLog.actions.stored'));
        expect(wrapper.text()).toContain(t('cellLog.actions.opened'));
        expect(wrapper.text()).toContain(t('cellLog.actions.emptied'));
        expect(wrapper.text()).toContain(t('cellLog.actions.transferred'));
    });

    it('renders a preview of the product filter trigger', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('cellLog.filters.product'));
        expect(wrapper.text()).toContain(t('cellLog.filters.all'));
    });

    it('links to the dashboard page', () => {
        const wrapper = mountPage();

        const dashboardLink = wrapper
            .findAll('a')
            .find((a) => a.text() === t('dashboard.title'));
        expect(dashboardLink?.attributes('href')).toBe('/admin');
    });

    it('links back to the help landing page', () => {
        const wrapper = mountPage();

        const backLink = wrapper
            .findAll('a')
            .find((a) => a.text() === t('help.backToHelp'));
        expect(backLink?.attributes('href')).toBe('/admin/help');
    });
});

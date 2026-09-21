import { Head } from '@inertiajs/vue3';
import { ArrowLeftRight, CalendarPlus } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CELL_STATES, cellStateLabel } from '@/lib/cellStateColor';
import { t } from '@/lib/i18n';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import CellLogs from './CellLogs.vue';

const { usePageMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
        useHttp: () => ({ get: vi.fn() }),
    };
});

function mountPage() {
    usePageMock.mockReturnValue({
        url: '/admin/help/cell-logs',
        props: defaultAuthProps(),
    });

    return mount(CellLogs);
}

describe('Help CellLogs', () => {
    beforeEach(() => {
        resetMocks({ usePageMock });
    });

    it('renders the page title in the Head and the PageHeader', () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('help.cellLogs.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('help.cellLogs.title'));
    });

    it('renders every section heading', () => {
        const wrapper = mountPage();

        const headings = wrapper.findAll('h2').map((h2) => h2.text());
        expect(headings).toEqual([
            t('help.cellLogs.filtering.heading'),
            t('help.cellLogs.actions.heading'),
            t('help.cellLogs.legend.heading'),
        ]);
    });

    it('explains how to filter the activity log', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('help.cellLogs.filtering.body'));
    });

    it('explains what each action type means', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('help.cellLogs.actions.stored'));
        expect(wrapper.text()).toContain(t('help.cellLogs.actions.opened'));
        expect(wrapper.text()).toContain(t('help.cellLogs.actions.emptied'));
        expect(wrapper.text()).toContain(
            t('help.cellLogs.actions.transferred'),
        );
    });

    it('renders an icon next to actions with an established icon, and a preview of the Filters button', () => {
        const wrapper = mountPage();

        expect(wrapper.findComponent(CalendarPlus).exists()).toBe(true);
        expect(wrapper.findComponent(ArrowLeftRight).exists()).toBe(true);
        expect(wrapper.text()).toContain(t('cellLog.filters.title'));
    });

    it('renders the cell-state legend from the real cell state colors, not hardcoded labels', () => {
        const wrapper = mountPage();

        for (const state of CELL_STATES) {
            expect(wrapper.text()).toContain(cellStateLabel(state));
        }
    });

    it('renders a preview of the activity-log table with its real column headers and an example row', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('cellLog.columns.cell'));
        expect(wrapper.text()).toContain(t('cellLog.columns.action'));
        expect(wrapper.text()).toContain(t('cellLog.columns.product'));
        expect(wrapper.text()).toContain(t('cellLog.columns.pallet'));
        expect(wrapper.text()).toContain(t('cellLog.columns.note'));
        expect(wrapper.text()).toContain(t('cellLog.columns.doneBy'));
        expect(wrapper.text()).toContain(t('cellLog.columns.when'));

        const table = wrapper.find('table');
        expect(table.text()).toContain(t('cellLog.actions.stored'));
        expect(table.text()).toContain('Example product');
        expect(table.text()).toContain('Jane Doe');
    });

    it('renders a preview of the real filter fields named in the filtering text', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('cellLog.filters.product'));
        expect(wrapper.text()).toContain(t('cellLog.filters.row'));
        expect(wrapper.text()).toContain(t('cellLog.filters.column'));
        expect(wrapper.text()).toContain(t('cellLog.filters.doneBy'));
        expect(wrapper.text()).toContain(t('cellLog.filters.statusChange'));
        expect(wrapper.text()).toContain(t('cellLog.filters.from'));
        expect(wrapper.text()).toContain(t('cellLog.filters.to'));
        expect(wrapper.text()).toContain(t('cellLog.filters.withinDays'));
    });

    it('links to the activity log page', () => {
        const wrapper = mountPage();

        const logLink = wrapper
            .findAll('a')
            .find((a) => a.text() === t('cellLog.title'));
        expect(logLink?.attributes('href')).toBe('/admin/cell-logs');
    });

    it('links back to the help landing page', () => {
        const wrapper = mountPage();

        const backLink = wrapper
            .findAll('a')
            .find((a) => a.text() === t('help.backToHelp'));
        expect(backLink?.attributes('href')).toBe('/admin/help');
    });
});

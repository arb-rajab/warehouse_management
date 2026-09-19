import { Ban, PackageSearch, Search } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CELL_STATES, cellStateLabel } from '@/lib/cellStateColor';
import { t } from '@/lib/i18n';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import Cells from './Cells.vue';

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
        url: '/admin/help/cells',
        props: defaultAuthProps(),
    });

    return mount(Cells);
}

describe('Help Cells', () => {
    beforeEach(() => {
        resetMocks({ usePageMock });
    });

    it('explains how to read the map', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('help.cells.map.body'));
    });

    it('renders the cell-state legend from the real cell state colors, not hardcoded labels', () => {
        const wrapper = mountPage();

        for (const state of CELL_STATES) {
            expect(wrapper.text()).toContain(cellStateLabel(state));
        }
    });

    it('explains managing pallets and deactivating a cell', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('help.cells.managingPallets.body'));
        expect(wrapper.text()).toContain(t('help.cells.deactivating.body'));
    });

    it('renders a preview of the search, manage-pallet, and deactivate controls', () => {
        const wrapper = mountPage();

        expect(wrapper.findComponent(Search).exists()).toBe(true);
        expect(wrapper.findComponent(PackageSearch).exists()).toBe(true);
        expect(wrapper.findComponent(Ban).exists()).toBe(true);
        expect(wrapper.text()).toContain(t('cells.search.placeholder'));
        expect(wrapper.text()).toContain(t('cells.palletActions.triggerLabel'));
        expect(wrapper.text()).toContain(
            t('cells.toggleActive.deactivateLabel'),
        );
    });

    it('links to the warehouse map page', () => {
        const wrapper = mountPage();

        const cellsLink = wrapper
            .findAll('a')
            .find((a) => a.text() === t('cells.title'));
        expect(cellsLink?.attributes('href')).toBe('/admin/cells');
    });

    it('links back to the help landing page', () => {
        const wrapper = mountPage();

        const backLink = wrapper
            .findAll('a')
            .find((a) => a.text() === t('help.backToHelp'));
        expect(backLink?.attributes('href')).toBe('/admin/help');
    });
});

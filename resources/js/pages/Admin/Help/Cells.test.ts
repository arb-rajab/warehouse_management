import { Head } from '@inertiajs/vue3';
import { Ban } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    show as helpShow,
    index as helpIndex,
} from '@/actions/App/Http/Controllers/Admin/HelpController';
import { index as rowsIndex } from '@/actions/App/Http/Controllers/Admin/RowController';
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
        useHttp: () => ({ get: vi.fn() }),
    };
});

function mountPage() {
    usePageMock.mockReturnValue({
        url: helpShow.url({ topic: 'cells' }),
        props: defaultAuthProps(),
    });

    return mount(Cells);
}

describe('Help Cells', () => {
    beforeEach(() => {
        resetMocks({ usePageMock });
    });

    it('renders the page title in the Head and the PageHeader', () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('help.cells.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('help.cells.title'));
    });

    it('renders every section heading', () => {
        const wrapper = mountPage();

        const headings = wrapper.findAll('h2').map((h2) => h2.text());
        expect(headings).toEqual([
            t('help.cells.legend.heading'),
            t('help.cells.managingPallets.heading'),
            t('help.cells.deactivating.heading'),
        ]);
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

    it('renders a preview of the manageable cell and deactivate controls', () => {
        const wrapper = mountPage();

        expect(wrapper.findComponent(Ban).exists()).toBe(true);
        expect(wrapper.text()).toContain('Example product');
        expect(
            wrapper
                .find(`[title="${t('cells.palletActions.triggerLabel')}"]`)
                .exists(),
        ).toBe(true);
        expect(wrapper.text()).toContain(
            t('cells.toggleActive.deactivateLabel'),
        );
    });

    it('renders a preview of the pallet action tabs named in the managingPallets text', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('cells.palletActions.tabs.store'));
        expect(wrapper.text()).toContain(t('cells.palletActions.tabs.open'));
        expect(wrapper.text()).toContain(
            t('cells.palletActions.tabs.removeBoxes'),
        );
        expect(wrapper.text()).toContain(t('cells.palletActions.tabs.empty'));
        expect(wrapper.text()).toContain(
            t('cells.palletActions.tabs.transfer'),
        );
        expect(wrapper.text()).toContain(t('cells.palletActions.tabs.edit'));
    });

    it('renders a preview of the store tab fields', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(
            t('cells.palletActions.store.productLabel'),
        );
        expect(wrapper.text()).toContain(
            t('cells.palletActions.store.expirationLabel'),
        );
        expect(wrapper.text()).toContain(t('cells.palletActions.noteLabel'));
    });

    it('renders a preview of the open and remove-boxes tab fields', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(
            t('cells.palletActions.open.boxesCountLabel'),
        );
        expect(wrapper.text()).toContain(
            t('cells.palletActions.open.confirmEmptyLabel'),
        );
        expect(wrapper.text()).toContain(
            t('cells.palletActions.removeBoxes.boxesCountLabel'),
        );
    });

    it('renders a preview of the edit tab fields, without the note field', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(
            t('cells.palletActions.edit.productLabel'),
        );
        expect(wrapper.text()).toContain(
            t('cells.palletActions.edit.expirationLabel'),
        );
        expect(wrapper.text()).toContain(
            t('cells.palletActions.edit.boxesCountLabel'),
        );
        expect(wrapper.text()).toContain(
            t('cells.palletActions.edit.confirmEmptyLabel'),
        );

        // The real dialog hides the note field on the edit tab, so the
        // preview must not show one more note field than there are tabs
        // that actually have it (store, open, removeBoxes, transfer).
        const noteLabels = wrapper
            .findAll('label')
            .filter(
                (label) => label.text() === t('cells.palletActions.noteLabel'),
            );
        expect(noteLabels).toHaveLength(4);
    });

    it('renders a preview of the empty tab confirmation text', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(
            t('cells.palletActions.empty.confirmText', { location: 'A3·2' }),
        );
    });

    it('renders a preview of the transfer tab fields', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(
            t('cells.palletActions.transfer.rowLabel'),
        );
        expect(wrapper.text()).toContain(
            t('cells.palletActions.transfer.cellNumberLabel'),
        );
        expect(wrapper.text()).toContain(
            t('cells.palletActions.transfer.flatNumberLabel'),
        );
    });

    it('links to the rows page, where cells and pallets are managed', () => {
        const wrapper = mountPage();

        const rowsLink = wrapper
            .findAll('a')
            .find((a) => a.text() === t('nav.rows'));
        expect(rowsLink?.attributes('href')).toBe(rowsIndex.url());
    });

    it('links back to the help landing page', () => {
        const wrapper = mountPage();

        const backLink = wrapper
            .findAll('a')
            .find((a) => a.text() === t('help.backToHelp'));
        expect(backLink?.attributes('href')).toBe(helpIndex.url());
    });
});

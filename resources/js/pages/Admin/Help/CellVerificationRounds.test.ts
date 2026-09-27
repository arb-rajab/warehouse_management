import { Head } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CellVerificationAcceptButton from '@/components/CellVerificationAcceptButton.vue';
import CellVerificationAcceptedNote from '@/components/CellVerificationAcceptedNote.vue';
import CellVerificationCorrectnessBadge from '@/components/CellVerificationCorrectnessBadge.vue';
import HelpUiPreview from '@/components/HelpUiPreview.vue';
import { cellStateLabel } from '@/lib/cellStateColor';
import { formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import CellVerificationRounds from './CellVerificationRounds.vue';

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
        url: '/admin/help/cell-verification-rounds',
        props: defaultAuthProps(),
    });

    return mount(CellVerificationRounds);
}

describe('Help CellVerificationRounds', () => {
    beforeEach(() => {
        resetMocks({ usePageMock });
    });

    it('renders the page title in the Head and the PageHeader', () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('help.cellVerificationRounds.title'),
        );
        expect(wrapper.get('h1').text()).toBe(
            t('help.cellVerificationRounds.title'),
        );
    });

    it('renders every section heading', () => {
        const wrapper = mountPage();

        const headings = wrapper.findAll('h2').map((h2) => h2.text());
        expect(headings).toEqual([
            t('help.cellVerificationRounds.what.heading'),
            t('help.cellVerificationRounds.reports.heading'),
            t('help.cellVerificationRounds.accepting.heading'),
            t('help.cellVerificationRounds.exporting.heading'),
        ]);
    });

    it('explains what a verification round is', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(
            t('help.cellVerificationRounds.what.body'),
        );
    });

    it('explains correct/incorrect reports and exporting', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(
            t('help.cellVerificationRounds.reports.body'),
        );
        expect(wrapper.text()).toContain(
            t('help.cellVerificationRounds.exporting.body'),
        );
    });

    it('explains accepting a reported state and previews the real accept button and accepted note, non-interactively', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(
            t('help.cellVerificationRounds.accepting.body'),
        );

        const preview = wrapper
            .findAllComponents(HelpUiPreview)
            .find((p) =>
                p.findComponent(CellVerificationAcceptButton).exists(),
            );
        expect(preview?.attributes('inert')).toBeDefined();
        expect(preview?.text()).toContain(t('cellVerificationReport.accept'));

        const note = preview!.getComponent(CellVerificationAcceptedNote);
        expect(note.props('acceptedBy')).toEqual({ id: 1, name: 'Admin' });
        expect(note.text()).toContain(
            t('cellVerificationReport.acceptedOn', {
                date: formatDateTime('2026-01-02T09:30:00Z'),
            }),
        );
    });

    it('renders a preview of the export button', () => {
        const wrapper = mountPage();

        expect(wrapper.findComponent(Download).exists()).toBe(true);
        expect(wrapper.text()).toContain(t('cellVerificationReport.export'));
    });

    it('renders a preview of the correct/incorrect report badges', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('cellVerificationReport.correct'));
        expect(wrapper.text()).toContain(t('cellVerificationReport.incorrect'));
    });

    it('renders a preview of the reports table with its real column headers and example rows', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(
            t('cellVerificationReport.columns.cell'),
        );
        expect(wrapper.text()).toContain(
            t('cellVerificationReport.columns.correctness'),
        );
        expect(wrapper.text()).toContain(
            t('cellVerificationReport.columns.expected'),
        );
        expect(wrapper.text()).toContain(
            t('cellVerificationReport.columns.reported'),
        );
        expect(wrapper.text()).toContain(
            t('cellVerificationReport.columns.note'),
        );
        expect(wrapper.text()).toContain(
            t('cellVerificationReport.columns.when'),
        );

        const table = wrapper.find('table');
        expect(
            table.findAllComponents(CellVerificationCorrectnessBadge),
        ).toHaveLength(2);
        expect(table.text()).toContain(cellStateLabel('full'));
        expect(table.text()).toContain(cellStateLabel('empty'));
    });

    it('links to the verification rounds page', () => {
        const wrapper = mountPage();

        const roundsLink = wrapper
            .findAll('a')
            .find((a) => a.text() === t('cellVerificationRound.title'));
        expect(roundsLink?.attributes('href')).toBe(
            '/admin/cell-verification-rounds',
        );
    });

    it('links back to the help landing page', () => {
        const wrapper = mountPage();

        const backLink = wrapper
            .findAll('a')
            .find((a) => a.text() === t('help.backToHelp'));
        expect(backLink?.attributes('href')).toBe('/admin/help');
    });
});

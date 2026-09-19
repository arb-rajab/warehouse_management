import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
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

import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
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

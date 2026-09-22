import { Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { t } from '@/lib/i18n';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import HelpTopicPage from './HelpTopicPage.vue';

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
        url: '/admin/help/rows',
        props: defaultAuthProps(),
    });

    return mount(HelpTopicPage, {
        props: {
            title: 'Rows help',
            featureHref: '/admin/rows',
            featureLabel: 'Rows',
        },
        slots: { default: '<section><h2>A topic section</h2></section>' },
    });
}

describe('HelpTopicPage', () => {
    beforeEach(() => {
        resetMocks({ usePageMock });
    });

    it('renders the title in the Head and the PageHeader', () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(Head).props('title')).toBe('Rows help');
        expect(wrapper.get('h1').text()).toBe('Rows help');
    });

    it('renders its slot content inside the content column', () => {
        const wrapper = mountPage();

        const column = wrapper.get('.max-w-2xl');
        expect(column.text()).toContain('A topic section');
    });

    it('links back to the help landing page above the content column', () => {
        const wrapper = mountPage();

        const backLink = wrapper
            .findAll('a')
            .find((a) => a.text() === t('help.backToHelp'));
        expect(backLink?.attributes('href')).toBe('/admin/help');
        expect(backLink?.classes()).toContain('mb-6');
    });

    it('links to the documented feature at the end of the content column', () => {
        const wrapper = mountPage();

        const featureLink = wrapper
            .get('.max-w-2xl')
            .findAll('a')
            .find((a) => a.text() === 'Rows');
        expect(featureLink?.attributes('href')).toBe('/admin/rows');
        expect(featureLink?.classes()).not.toContain('mb-6');
    });
});

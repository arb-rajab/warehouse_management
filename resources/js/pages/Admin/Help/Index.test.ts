import { Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { t } from '@/lib/i18n';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import Index from './Index.vue';

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
        url: '/admin/help',
        props: defaultAuthProps(),
    });

    return mount(Index);
}

describe('Help Index', () => {
    beforeEach(() => {
        resetMocks({ usePageMock });
    });

    it('renders the page title in the Head and the PageHeader', () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('help.index.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('help.index.title'));
    });

    it('renders the intro text', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('help.index.intro'));
    });

    const topicHrefs: Record<string, string> = {
        dashboard: '/admin/help/dashboard',
        rows: '/admin/help/rows',
        cells: '/admin/help/cells',
        cellLogs: '/admin/help/cell-logs',
        cellVerificationRounds: '/admin/help/cell-verification-rounds',
        products: '/admin/help/products',
        users: '/admin/help/users',
        settings: '/admin/help/settings',
    };

    it('links each topic to its help page', () => {
        const wrapper = mountPage();

        // Scoped to the topic grid, not the whole page — AdminLayout's own
        // nav also renders a "Dashboard" link, which would otherwise collide
        // with the "Dashboard" topic card's text.
        const links = wrapper.get('ul').findAll('a');

        for (const [key, href] of Object.entries(topicHrefs)) {
            const link = links.find((a) =>
                a.text().includes(t(`help.index.topics.${key}.title`)),
            );
            expect(link?.attributes('href')).toBe(href);
        }
    });

    it('renders every topic description', () => {
        const wrapper = mountPage();

        for (const key of Object.keys(topicHrefs)) {
            expect(wrapper.text()).toContain(
                t(`help.index.topics.${key}.description`),
            );
        }
    });
});

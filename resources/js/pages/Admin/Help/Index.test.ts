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

    it('renders the intro text', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('help.index.intro'));
    });

    it('links each topic to its help page', () => {
        const wrapper = mountPage();

        const links = wrapper.findAll('a');

        const rowsLink = links.find((a) =>
            a.text().includes(t('help.index.topics.rows.title')),
        );
        expect(rowsLink?.attributes('href')).toBe('/admin/help/rows');

        const usersLink = links.find((a) =>
            a.text().includes(t('help.index.topics.users.title')),
        );
        expect(usersLink?.attributes('href')).toBe('/admin/help/users');

        const productsLink = links.find((a) =>
            a.text().includes(t('help.index.topics.products.title')),
        );
        expect(productsLink?.attributes('href')).toBe('/admin/help/products');

        const cellLogsLink = links.find((a) =>
            a.text().includes(t('help.index.topics.cellLogs.title')),
        );
        expect(cellLogsLink?.attributes('href')).toBe('/admin/help/cell-logs');
    });

    it('renders every topic description', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(
            t('help.index.topics.rows.description'),
        );
        expect(wrapper.text()).toContain(
            t('help.index.topics.users.description'),
        );
        expect(wrapper.text()).toContain(
            t('help.index.topics.products.description'),
        );
        expect(wrapper.text()).toContain(
            t('help.index.topics.cellLogs.description'),
        );
    });
});

import { Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    show as helpShow,
    index as helpIndex,
} from '@/actions/App/Http/Controllers/Admin/HelpController';
import { index as receiptsIndex } from '@/actions/App/Http/Controllers/Admin/ReceiptController';
import { t } from '@/lib/i18n';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import Receipts from './Receipts.vue';

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
        url: helpShow.url({ topic: 'receipts' }),
        props: defaultAuthProps(),
    });

    return mount(Receipts);
}

describe('Help Receipts', () => {
    beforeEach(() => {
        resetMocks({ usePageMock });
    });

    it('renders the page title in the Head and the PageHeader', () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('help.receipts.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('help.receipts.title'));
    });

    it('renders every section heading and body', () => {
        const wrapper = mountPage();

        const sections = [
            'what',
            'list',
            'status',
            'filtering',
            'files',
            'pallets',
        ];
        expect(wrapper.findAll('h2').map((h2) => h2.text())).toEqual(
            sections.map((section) => t(`help.receipts.${section}.heading`)),
        );

        for (const section of sections) {
            expect(wrapper.text()).toContain(
                t(`help.receipts.${section}.body`),
            );
        }
    });

    it('previews both status badges and the warehouse and status filters', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('receipts.status.draft'));
        expect(wrapper.text()).toContain(t('receipts.status.finalized'));
        expect(wrapper.find('#help-receipts-warehouse-filter').exists()).toBe(
            true,
        );
        expect(wrapper.find('#help-receipts-status-filter').exists()).toBe(
            true,
        );
    });

    it('links to the receipts page', () => {
        const wrapper = mountPage();

        const link = wrapper
            .findAll('a')
            .find((a) => a.text() === t('receipts.index.title'));
        expect(link?.attributes('href')).toBe(receiptsIndex.url());
    });

    it('links back to the help landing page', () => {
        const wrapper = mountPage();

        const backLink = wrapper
            .findAll('a')
            .find((a) => a.text() === t('help.backToHelp'));
        expect(backLink?.attributes('href')).toBe(helpIndex.url());
    });
});

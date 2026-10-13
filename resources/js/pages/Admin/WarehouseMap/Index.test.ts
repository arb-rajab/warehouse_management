import { Head, router } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { index as helpIndex } from '@/actions/App/Http/Controllers/Admin/HelpController';
import { rows as warehouseMapRows } from '@/actions/App/Http/Controllers/Admin/WarehouseMapController';
import { t } from '@/lib/i18n';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import Index from './Index.vue';

const { usePageMock, routerPostMock, mountMapMock, disposeMock } = vi.hoisted(
    () => ({
        usePageMock: vi.fn(),
        routerPostMock: vi.fn(),
        mountMapMock: vi.fn(),
        disposeMock: vi.fn(),
    }),
);

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { post: routerPostMock },
    };
});

vi.mock('@/warehouse-map/main', () => ({
    mountWarehouseMap: mountMapMock,
}));

function mountPage() {
    usePageMock.mockReturnValue({
        url: '/admin/warehouse-map',
        props: defaultAuthProps(),
    });

    return mount(Index, { attachTo: document.body });
}

describe('WarehouseMap Index', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, routerPostMock });
        mountMapMock.mockReset();
        disposeMock.mockReset();
        mountMapMock.mockReturnValue({
            dispose: disposeMock,
            refresh: vi.fn(),
        });
    });

    it('sets the page title in the Head', () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('nav.warehouseMap'),
        );
        wrapper.unmount();
    });

    it('hands the whole area under the app bar to the map', () => {
        const wrapper = mountPage();

        expect(wrapper.get('main').classes()).toContain('flex-1');
        expect(wrapper.get('main').classes()).not.toContain('max-w-6xl');
        expect(wrapper.find('[data-testid="warehouse-map"]').exists()).toBe(
            true,
        );
        wrapper.unmount();
    });

    it('mounts the map on its container with the endpoint, the help page and the panel language', () => {
        const wrapper = mountPage();

        expect(mountMapMock).toHaveBeenCalledTimes(1);

        const [element, options] = mountMapMock.mock.calls[0];

        expect(element).toBe(
            wrapper.get('[data-testid="warehouse-map"]').element,
        );
        expect(options.rowsUrl).toBe(warehouseMapRows.url());
        expect(options.helpUrl).toBe(helpIndex.url());
        expect(options.locale).toBe('en');
        expect(options.messages.appTitle).toBe('Warehouse Map');
        expect(options.fallbackMessages.appTitle).toBe('Warehouse Map');
        wrapper.unmount();
    });

    it('switches the panel language with a full reload, like the language switcher', () => {
        const wrapper = mountPage();
        const { switchLocale } = mountMapMock.mock.calls[0][1];

        switchLocale('ar');

        expect(routerPostMock).toHaveBeenCalledWith(
            '/locale/ar',
            {},
            expect.objectContaining({ onSuccess: expect.any(Function) }),
        );
        expect(router.post).toBe(routerPostMock);
        wrapper.unmount();
    });

    it('disposes the map when leaving the page', () => {
        const wrapper = mountPage();

        wrapper.unmount();

        expect(disposeMock).toHaveBeenCalledTimes(1);
    });
});

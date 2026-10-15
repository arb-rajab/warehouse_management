import { mount } from '@vue/test-utils';
import { expect, it, vi } from 'vitest';
import PageHeader from '@/components/PageHeader.vue';
import StoreUploadManager from '@/components/StoreUploadManager.vue';
import { t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { createUseHttpStub } from '@/testing/inertiaStubs';
import Page from './Create.vue';

const { usePageMock, useHttpMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    useHttpMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, createFormStub } =
        await import('@/testing/inertiaStubs');

    return {
        usePage: usePageMock,
        useHttp: useHttpMock,
        Link: createLinkStub(),
        Head: { template: '<span />' },
        Form: createFormStub(() => ({ errors: {}, processing: false })),
        router: { post: vi.fn() },
    };
});
it('renders the translated page title around the shared file manager', () => {
    usePageMock.mockReturnValue({
        url: '/temp/admin/uploads',
        props: { auth: { user: null }, store: storeSharedProps() },
    });
    useHttpMock.mockImplementation((data) => createUseHttpStub(data));
    const wrapper = mount(Page);
    expect(wrapper.getComponent(StoreUploadManager).props('library')).toBe(
        true,
    );
    expect(wrapper.getComponent(StoreUploadManager).props('initialTab')).toBe(
        'upload',
    );
    expect(wrapper.getComponent(PageHeader).props('title')).toBe(
        t('store.uploads.upload'),
    );
});

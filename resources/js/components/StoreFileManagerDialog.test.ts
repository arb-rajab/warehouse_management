import { mount } from '@vue/test-utils';
import { expect, it, vi } from 'vitest';
import FilterDialog from '@/components/FilterDialog.vue';
import StoreUploadManager from '@/components/StoreUploadManager.vue';
import { t } from '@/lib/i18n';
import { storeSharedProps, storeUpload } from '@/testing/factories';
import { createUseHttpStub } from '@/testing/inertiaStubs';
import StoreFileManagerDialog from './StoreFileManagerDialog.vue';

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
it('reuses the accessible dialog and keeps selection as a draft until applied', async () => {
    usePageMock.mockReturnValue({ props: { store: storeSharedProps() } });
    useHttpMock.mockImplementation((data) => createUseHttpStub(data));
    const wrapper = mount(StoreFileManagerDialog, {
        props: {
            open: true,
            selected: [storeUpload()],
            multiple: true,
            type: 'image',
        },
    });
    expect(wrapper.getComponent(FilterDialog).props('title')).toBe(
        t('store.uploads.choose'),
    );
    expect(wrapper.getComponent(FilterDialog).props('closeLabel')).toBe(
        t('common.close'),
    );
    expect(wrapper.getComponent(StoreUploadManager).props('selected')).toEqual([
        storeUpload(),
    ]);
    expect(wrapper.getComponent(StoreUploadManager).props('type')).toBe(
        'image',
    );
    expect(wrapper.getComponent(StoreUploadManager).props('multiple')).toBe(
        true,
    );
    await wrapper
        .findAll('button')
        .find((button) => button.text() === t('store.uploads.select'))!
        .trigger('click');
    expect(wrapper.emitted('choose')).toEqual([[[storeUpload()]]]);
    expect(wrapper.emitted('update:open')).toEqual([[false]]);
    await wrapper.setProps({ open: false });
    expect(wrapper.findComponent(StoreUploadManager).exists()).toBe(false);
});

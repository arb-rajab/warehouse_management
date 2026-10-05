import { Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    create as uploadedFilesCreate,
    index as uploadedFilesIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/UploadController';
import PageHeader from '@/components/PageHeader.vue';
import StoreFileUploader from '@/components/StoreFileUploader.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import Create from './Create.vue';

const { usePageMock } = vi.hoisted(() => ({ usePageMock: vi.fn() }));

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');
    const { reactive } = await import('vue');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
        useHttp: () =>
            reactive({ processing: false, post: () => Promise.resolve() }),
    };
});

function mountPage() {
    usePageMock.mockReturnValue({
        url: uploadedFilesCreate.url(),
        props: defaultAuthProps({ store: storeSharedProps() }),
    });

    return mount(Create);
}

describe('Store uploaded files Create', () => {
    beforeEach(() => {
        resetMocks({ usePageMock });
    });

    it('renders inside the store layout, titled in the Head and the header', () => {
        const wrapper = mountPage();

        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('store.uploads.create.title'),
        );
        expect(wrapper.getComponent(PageHeader).props('title')).toBe(
            t('store.uploads.create.title'),
        );
        expect(t('store.uploads.create.title')).toBe('Upload new files');
    });

    it('links back to the library', () => {
        const wrapper = mountPage();
        const back = wrapper
            .getComponent(PageHeader)
            .findAllComponents(Link)
            .find((link) =>
                link.text().includes(t('store.uploads.create.back')),
            );

        expect(back?.props('href')).toEqual(uploadedFilesIndex());
    });

    it('offers the uploader for any kind of file under its heading', () => {
        const wrapper = mountPage();

        expect(wrapper.get('section h2').text()).toBe(
            t('store.uploads.create.heading'),
        );
        expect(
            wrapper.getComponent(StoreFileUploader).props('accept'),
        ).toBeUndefined();
    });
});

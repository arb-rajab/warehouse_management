import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { previews } from '@/actions/App/Http/Controllers/Store/Admin/UploadController';
import StoreFileManagerDialog from '@/components/StoreFileManagerDialog.vue';
import { t } from '@/lib/i18n';
import { storeSharedProps, storeUpload } from '@/testing/factories';
import { createUseHttpStub } from '@/testing/inertiaStubs';
import StoreUploadField from './StoreUploadField.vue';

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
beforeEach(() => {
    vi.clearAllMocks();
    usePageMock.mockReturnValue({
        props: { store: storeSharedProps({ permissions: ['view_uploads'] }) },
    });
    useHttpMock.mockImplementation((data) =>
        createUseHttpStub(data, [storeUpload()]),
    );
});

describe('StoreUploadField', () => {
    it('preserves comma separated IDs, previews and removal without deleting uploads', async () => {
        const wrapper = mount(StoreUploadField, {
            props: {
                name: 'photos',
                label: 'Photos',
                modelValue: '1',
                initialFiles: [storeUpload()],
                multiple: true,
                error: 'Invalid avatar',
            },
        });
        expect(wrapper.get('input[type="hidden"]').attributes('value')).toBe(
            '1',
        );
        expect(wrapper.get('img').attributes('src')).toBe(storeUpload().url);
        expect(wrapper.text()).toContain('Photos');
        expect(wrapper.text()).toContain('Invalid avatar');
        expect(wrapper.text()).toContain(t('store.uploads.choose'));
        await wrapper.get('button[aria-label]').trigger('click');
        expect(wrapper.emitted('update:modelValue')).toEqual([['']]);
        wrapper
            .getComponent(StoreFileManagerDialog)
            .vm.$emit('choose', [storeUpload(), storeUpload({ id: 2 })]);
        await flushPromises();
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['1,2']);
        expect(
            wrapper.getComponent(StoreFileManagerDialog).props('multiple'),
        ).toBe(true);
    });
    it('hydrates selected IDs through the previews action and passes type to the picker', async () => {
        const http = createUseHttpStub({ ids: '' }, [storeUpload()]);
        const get = vi.spyOn(http, 'get');
        useHttpMock.mockReturnValue(http);
        const wrapper = mount(StoreUploadField, {
            props: {
                name: 'avatar',
                label: 'Avatar',
                modelValue: '1',
                type: 'image',
            },
        });
        await flushPromises();
        expect(get).toHaveBeenCalledWith(previews.url());
        expect(http.ids).toBe('1');
        expect(wrapper.get('img').attributes('alt')).toBe('Photo');
        expect(wrapper.getComponent(StoreFileManagerDialog).props('type')).toBe(
            'image',
        );
    });
    it('preserves current previews and shows an error when hydration has no response', async () => {
        const http = createUseHttpStub({ ids: '' }, null);
        useHttpMock.mockReturnValue(http);
        const wrapper = mount(StoreUploadField, {
            props: {
                name: 'avatar',
                label: 'Avatar',
                modelValue: '1',
                initialFiles: [storeUpload()],
            },
        });
        await wrapper.setProps({ modelValue: 'invalid' });
        await flushPromises();
        expect(wrapper.get('img').attributes('alt')).toBe('Photo');
        expect(wrapper.get('[role="alert"]').text()).toBe(
            t('store.uploads.loadFailed'),
        );
    });
    it('shows current previews but hides the picker without read permission', () => {
        usePageMock.mockReturnValue({ props: { store: storeSharedProps() } });
        const wrapper = mount(StoreUploadField, {
            props: {
                name: 'avatar',
                label: 'Avatar',
                modelValue: '1',
                initialFiles: [storeUpload()],
            },
        });
        expect(wrapper.find('img').exists()).toBe(true);
        expect(wrapper.text()).not.toContain(t('store.uploads.choose'));
    });
});

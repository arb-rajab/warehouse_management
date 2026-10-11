import { File as FileIcon, FolderOpen } from '@lucide/vue';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import { previews as previewUploads } from '@/actions/App/Http/Controllers/Store/Admin/UploadController';
import FieldError from '@/components/FieldError.vue';
import StoreFileManagerDialog from '@/components/StoreFileManagerDialog.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { t } from '@/lib/i18n';
import { storeUpload } from '@/testing/factories';
import type { StoreUpload, StoreUploadKind } from '@/types/store';

interface GetOptions {
    onSuccess?: (response: StoreUpload[]) => void;
}

const { getMock } = vi.hoisted(() => ({
    getMock: vi.fn<(url: string, options: GetOptions) => void>(),
}));

vi.mock('@inertiajs/vue3', () => ({
    useHttp: () =>
        reactive({
            processing: false,
            get: (url: string, options: GetOptions) => {
                getMock(url, options);

                return Promise.resolve();
            },
            post: () => Promise.resolve(),
        }),
}));

const logo = storeUpload({ id: 4, name: 'logo' });
const banner = storeUpload({
    id: 9,
    name: 'banner',
    type: 'document',
    extension: 'pdf',
});

function mountField({
    value = '',
    multiple,
    type,
    error,
    required,
}: {
    value?: string;
    multiple?: boolean;
    type?: StoreUploadKind;
    error?: string;
    required?: boolean;
} = {}) {
    const wrapper = mount(StoreUploadField, {
        props: {
            id: 'photos',
            name: 'photos',
            label: 'Gallery images',
            modelValue: value,
            multiple,
            type,
            error,
            required,
            'onUpdate:modelValue': (ids: string) =>
                wrapper.setProps({ modelValue: ids }),
        },
    });

    return wrapper;
}

function hiddenValue(wrapper: ReturnType<typeof mountField>): string {
    return (
        wrapper.get('input[type="hidden"][name="photos"]')
            .element as HTMLInputElement
    ).value;
}

describe('StoreUploadField', () => {
    beforeEach(() => {
        getMock.mockReset();
    });

    it('renders its label, an empty hidden input and the browse button', () => {
        const wrapper = mountField();

        expect(wrapper.get('#photos-label').text()).toBe('Gallery images');
        expect(hiddenValue(wrapper)).toBe('');
        expect(wrapper.get('button#photos').text()).toBe(
            t('store.uploads.field.browse'),
        );
        expect(
            wrapper.get('button#photos').findComponent(FolderOpen).exists(),
        ).toBe(true);
        expect(wrapper.find('ul').exists()).toBe(false);
        expect(getMock).not.toHaveBeenCalled();
        expect(t('store.uploads.field.browse')).toBe('Choose a file');
    });

    it('validates a required image with an unnamed native proxy and opens the picker on invalid', async () => {
        const wrapper = mountField({ required: true });
        const proxy = wrapper.get('input[type="text"]');
        expect(proxy.attributes('required')).toBeDefined();
        expect(proxy.attributes('name')).toBeUndefined();
        expect((proxy.element as HTMLInputElement).checkValidity()).toBe(false);
        await proxy.trigger('invalid');
        expect(wrapper.getComponent(StoreFileManagerDialog).props('open')).toBe(
            true,
        );
    });

    it('says files in the plural for a multiple field', () => {
        const wrapper = mountField({ multiple: true });

        expect(wrapper.get('button#photos').text()).toBe(
            t('store.uploads.field.browseMany'),
        );
    });

    it('fetches the previews of the ids it already holds and shows them', async () => {
        const wrapper = mountField({ value: '4, 9,abc' });

        expect(getMock.mock.calls[0][0]).toBe(
            previewUploads.url({ query: { ids: [4, 9] } }),
        );

        getMock.mock.calls[0][1].onSuccess?.([logo, banner]);
        await flushPromises();

        const items = wrapper.findAll('li');
        expect(items).toHaveLength(2);
        expect(items[0].get('img').attributes('src')).toBe(logo.url);
        expect(items[0].text()).toContain('logo.png');
        expect(items[1].find('img').exists()).toBe(false);
        expect(items[1].findComponent(FileIcon).exists()).toBe(true);
        expect(hiddenValue(wrapper)).toBe('4, 9,abc');
    });

    it('removes a file from the list and from the posted ids', async () => {
        const wrapper = mountField({ value: '4,9' });
        getMock.mock.calls[0][1].onSuccess?.([logo, banner]);
        await flushPromises();

        const remove = wrapper.findAll('li')[0].get('button');
        expect(remove.attributes('aria-label')).toBe(
            t('store.uploads.field.removeFile', { name: 'logo.png' }),
        );
        expect(remove.text()).toBe(t('store.uploads.field.remove'));

        await remove.trigger('click');

        expect(wrapper.findAll('li')).toHaveLength(1);
        expect(hiddenValue(wrapper)).toBe('9');
        expect(wrapper.emitted('update:modelValue')).toEqual([['9']]);
    });

    it('opens the file manager with its kind, multiplicity and current files, and takes what it picks', async () => {
        const wrapper = mountField({
            value: '4',
            multiple: true,
            type: 'image',
        });
        getMock.mock.calls[0][1].onSuccess?.([logo]);
        await flushPromises();
        const dialog = wrapper.getComponent(StoreFileManagerDialog);

        expect(dialog.props()).toEqual({
            open: false,
            multiple: true,
            type: 'image',
            selected: [logo],
        });

        await wrapper.get('button#photos').trigger('click');
        expect(dialog.props('open')).toBe(true);

        dialog.vm.$emit('select', [banner, logo]);
        await flushPromises();

        expect(hiddenValue(wrapper)).toBe('9,4');
        expect(wrapper.findAll('li')).toHaveLength(2);
    });

    it('shows the server error under the field', () => {
        const wrapper = mountField({ error: 'Choose a photo.' });

        expect(wrapper.getComponent(FieldError).props('message')).toBe(
            'Choose a photo.',
        );
    });
});

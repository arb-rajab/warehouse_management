import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    files,
    store,
    destroy,
    bulkDestroy,
} from '@/actions/App/Http/Controllers/Store/Admin/UploadController';
import Pagination from '@/components/Pagination.vue';
import { t } from '@/lib/i18n';
import { storeSharedProps, storeUpload, paginated } from '@/testing/factories';
import { createUseHttpStub } from '@/testing/inertiaStubs';
import StoreUploadManager from './StoreUploadManager.vue';

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

function context(
    permissions = ['view_uploads', 'upload_files', 'delete_uploads'],
) {
    usePageMock.mockReturnValue({
        url: '/temp/admin/uploads',
        props: { store: storeSharedProps({ permissions }) },
    });
    const filter = createUseHttpStub(
        { search: '', sort: 'newest', type: '', per_page: 20, page: 1 },
        paginated([storeUpload()]),
    );
    const upload = createUseHttpStub(
        { file: null as File | null },
        storeUpload({ id: 2 }),
    );
    const deletion = createUseHttpStub({ id: [] as number[] }, { deleted: 1 });
    useHttpMock
        .mockReturnValueOnce(filter)
        .mockReturnValueOnce(upload)
        .mockReturnValueOnce(deletion);

    return { filter, upload, deletion };
}
beforeEach(() => {
    vi.clearAllMocks();
});

describe('StoreUploadManager', () => {
    it('renders every library label and sends filters and pagination to the JSON action', async () => {
        const { filter } = context();
        const get = vi.spyOn(filter, 'get');
        const wrapper = mount(StoreUploadManager);
        await flushPromises();
        expect(get).toHaveBeenCalledWith(files.url());

        for (const key of [
            'library',
            'upload',
            'search',
            'sort',
            'type',
            'allTypes',
            'newest',
            'oldest',
            'smallest',
            'largest',
            'image',
            'video',
            'audio',
            'archive',
            'document',
        ]) {
            expect(wrapper.text()).toContain(t(`store.uploads.${key}`));
        }

        expect(wrapper.text()).toContain(t('common.filters.apply'));
        await wrapper.get('#upload-search').setValue('report_');
        await wrapper.findAll('select')[0].setValue('largest');
        await wrapper.findAll('select')[1].setValue('document');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(filter.search).toBe('report_');
        expect(filter.sort).toBe('largest');
        expect(filter.type).toBe('document');
        expect(wrapper.getComponent(Pagination).props('perPage')).toBe(20);
        wrapper
            .getComponent(Pagination)
            .vm.$emit('navigate', files.url({ query: { page: 2 } }));
        await flushPromises();
        expect(filter.page).toBe(2);
        wrapper.getComponent(Pagination).vm.$emit('update:perPage', 10);
        await flushPromises();
        expect(filter.per_page).toBe(10);
        expect(filter.page).toBe(1);
    });
    it('selects and deselects files with single and multiple contracts', async () => {
        context();
        const wrapper = mount(StoreUploadManager, {
            props: { multiple: true, selected: [storeUpload({ id: 2 })] },
        });
        await flushPromises();
        await wrapper.get('button[aria-pressed="false"] img').trigger('click');
        expect(wrapper.emitted('update:selected')?.[0]).toEqual([
            [storeUpload({ id: 2 }), storeUpload()],
        ]);
        await wrapper.setProps({ selected: [storeUpload()] });
        await wrapper.get('button img').trigger('click');
        expect(wrapper.emitted('update:selected')?.at(-1)).toEqual([[]]);
    });
    it('hides read, upload and delete controls independently', async () => {
        const { filter } = context([]);
        const get = vi.spyOn(filter, 'get');
        const wrapper = mount(StoreUploadManager, { props: { library: true } });
        await flushPromises();
        expect(get).not.toHaveBeenCalled();
        expect(wrapper.find('form').exists()).toBe(false);
        expect(wrapper.find('button').exists()).toBe(false);
        wrapper.unmount();
        context(['view_uploads']);
        const readOnly = mount(StoreUploadManager, {
            props: { library: true },
        });
        await flushPromises();
        expect(readOnly.text()).not.toContain(t('store.uploads.upload'));
        expect(readOnly.text()).not.toContain(t('store.uploads.delete'));
        expect(readOnly.find('input[type="checkbox"]').exists()).toBe(false);
    });
    it('renders the upload tab and submits a multipart file with progress', async () => {
        const { upload } = context();
        const post = vi.spyOn(upload, 'post');
        const wrapper = mount(StoreUploadManager, {
            props: { initialTab: 'upload' },
        });

        for (const key of ['files', 'limits', 'upload']) {
            expect(wrapper.text()).toContain(t(`store.uploads.${key}`));
        }

        const input = wrapper.get('#upload-files');
        expect(input.attributes('multiple')).toBeDefined();
        expect(input.attributes('required')).toBeDefined();
        const file = new File(['image'], 'photo.png', { type: 'image/png' });
        Object.defineProperty(input.element, 'files', {
            value: [file],
            configurable: true,
        });
        await input.trigger('change');
        upload.progress = { percentage: 50 };
        await flushPromises();
        expect(wrapper.get('progress').attributes('value')).toBe('50');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(post).toHaveBeenCalledWith(store.url());
        expect(wrapper.emitted('uploaded')).toEqual([[storeUpload({ id: 2 })]]);
    });
    it('shows a rejected upload without selecting it', async () => {
        const { upload } = context();
        upload.errors.file = 'Rejected file';
        vi.spyOn(upload, 'post').mockResolvedValue(undefined);
        const wrapper = mount(StoreUploadManager, {
            props: { initialTab: 'upload' },
        });
        const input = wrapper.get('#upload-files');
        Object.defineProperty(input.element, 'files', {
            value: [new File(['bad'], 'bad.png')],
        });
        await input.trigger('change');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toBe('Rejected file');
        expect(wrapper.emitted('uploaded')).toBeUndefined();
        expect(wrapper.emitted('update:selected')).toBeUndefined();
    });
    it('retries only unfinished files after a partial upload failure', async () => {
        const { upload } = context();
        const post = vi
            .spyOn(upload, 'post')
            .mockResolvedValueOnce(storeUpload({ id: 2 }))
            .mockRejectedValueOnce(new Error())
            .mockResolvedValueOnce(storeUpload({ id: 3 }));
        const wrapper = mount(StoreUploadManager, {
            props: { initialTab: 'upload', multiple: true },
        });
        const input = wrapper.get('input[type="file"]');
        Object.defineProperty(input.element, 'files', {
            value: [
                new File(['first'], 'first.png'),
                new File(['second'], 'second.png'),
            ],
        });
        await input.trigger('change');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(post).toHaveBeenCalledTimes(2);
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(post).toHaveBeenCalledTimes(3);
        expect(
            wrapper
                .emitted('uploaded')
                ?.map(([file]) => (file as ReturnType<typeof storeUpload>).id),
        ).toEqual([2, 3]);
        expect(wrapper.emitted('update:selected')?.at(-1)).toEqual([
            [storeUpload({ id: 2 }), storeUpload({ id: 3 })],
        ]);
    });
    it('opens file info from JSON and confirms deletion through the typed action', async () => {
        const { deletion } = context();
        const remove = vi.spyOn(deletion, 'delete');
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const wrapper = mount(StoreUploadManager, { props: { library: true } });
        await flushPromises();
        await wrapper.get('button img').trigger('click');

        for (const key of ['info', 'size', 'openFile', 'delete']) {
            expect(wrapper.text()).toContain(t(`store.uploads.${key}`));
        }

        const button = wrapper
            .findAll('button')
            .find((button) => button.text() === t('store.uploads.delete'))!;
        await button.trigger('click');
        expect(remove).not.toHaveBeenCalled();
        confirm.mockReturnValue(true);
        await button.trigger('click');
        await flushPromises();
        expect(remove).toHaveBeenCalledWith(
            destroy.url(storeUpload(), { mergeQuery: {} }),
        );
        expect(wrapper.find('aside').exists()).toBe(false);
        confirm.mockRestore();
    });
    it('confirms bulk deletion and sends only selected IDs to the typed action', async () => {
        const { deletion } = context();
        const post = vi.spyOn(deletion, 'post');
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const wrapper = mount(StoreUploadManager, { props: { library: true } });
        await flushPromises();
        await wrapper.get('#select-upload-1').setValue(true);
        const button = wrapper
            .findAll('button')
            .find(
                (button) => button.text() === t('store.uploads.deleteSelected'),
            )!;
        await button.trigger('click');
        expect(post).not.toHaveBeenCalled();
        confirm.mockReturnValue(true);
        await button.trigger('click');
        await flushPromises();
        expect(deletion.id).toEqual([1]);
        expect(post).toHaveBeenCalledWith(bulkDestroy.url({ mergeQuery: {} }));
        expect(wrapper.text()).not.toContain(t('store.uploads.deleteSelected'));
        confirm.mockRestore();
    });
    it('retains the selection after a refused bulk deletion', async () => {
        const { deletion } = context();
        vi.spyOn(deletion, 'post').mockResolvedValue(undefined);
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(true);
        const wrapper = mount(StoreUploadManager, { props: { library: true } });
        await flushPromises();
        await wrapper.get('#select-upload-1').setValue(true);
        await wrapper
            .findAll('button')
            .find(
                (button) => button.text() === t('store.uploads.deleteSelected'),
            )!
            .trigger('click');
        await flushPromises();
        expect(
            (wrapper.get('#select-upload-1').element as HTMLInputElement)
                .checked,
        ).toBe(true);
        expect(wrapper.get('[role="alert"]').text()).toBe(
            t('store.uploads.deleteFailed'),
        );
        confirm.mockRestore();
    });
    it('shows empty, loading and failed states and cancels requests on unmount', async () => {
        const { filter } = context();
        filter.response = paginated([]);
        const cancel = vi.spyOn(filter, 'cancel');
        const wrapper = mount(StoreUploadManager);
        await flushPromises();
        expect(wrapper.text()).toContain(t('store.uploads.empty'));
        filter.processing = true;
        await flushPromises();
        expect(wrapper.text()).toContain(t('store.uploads.loading'));
        filter.processing = false;
        vi.spyOn(filter, 'get').mockRejectedValue(new Error());
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.text()).toContain(t('store.uploads.loadFailed'));
        wrapper.unmount();
        expect(cancel).toHaveBeenCalled();
    });
});

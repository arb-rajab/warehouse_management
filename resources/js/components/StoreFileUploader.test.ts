import { Check, CircleAlert, LoaderCircle } from '@lucide/vue';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import { store as storeUpload } from '@/actions/App/Http/Controllers/Store/Admin/UploadController';
import StoreFileUploader from '@/components/StoreFileUploader.vue';
import { t } from '@/lib/i18n';
import { storeUpload as uploadFixture } from '@/testing/factories';

interface PostOptions {
    onProgress?: (event: {
        loaded: number;
        total?: number;
        percentage?: number;
    }) => void;
    onSuccess?: (response: unknown) => void;
    onError?: (errors: Record<string, string>) => void;
    onHttpException?: () => void;
    onNetworkError?: () => void;
    onFinish?: () => void;
}

const { http, postMock } = vi.hoisted(() => {
    const postMock = vi.fn<(url: string, options: PostOptions) => void>();

    return {
        postMock,
        http: {
            file: null as File | null,
            processing: false,
            sentFiles: [] as (File | null)[],
        },
    };
});

vi.mock('@inertiajs/vue3', () => ({
    useHttp: () =>
        reactive({
            get file() {
                return http.file;
            },
            set file(value: File | null) {
                http.file = value;
            },
            get processing() {
                return http.processing;
            },
            post: (url: string, options: PostOptions) => {
                http.processing = true;
                http.sentFiles.push(http.file);
                postMock(url, options);

                return Promise.resolve();
            },
        }),
}));

function finish(index: number, outcome: (options: PostOptions) => void) {
    const options = postMock.mock.calls[index][1];
    outcome(options);
    http.processing = false;
    options.onFinish?.();
}

function chooseFiles(
    wrapper: ReturnType<typeof mount>,
    files: File[],
): Promise<void> {
    const input = wrapper.get('input[type="file"]');
    Object.defineProperty(input.element, 'files', {
        value: files,
        configurable: true,
    });

    return input.trigger('change');
}

function file(name: string, size = 10): File {
    const chosen = new File(['x'], name);
    Object.defineProperty(chosen, 'size', { value: size });

    return chosen;
}

describe('StoreFileUploader', () => {
    beforeEach(() => {
        postMock.mockReset();
        http.file = null;
        http.processing = false;
        http.sentFiles = [];
    });

    it('renders the drop zone with its labels and a multiple file input', () => {
        const wrapper = mount(StoreFileUploader, {
            props: { accept: 'image/*' },
        });

        expect(wrapper.text()).toContain(t('store.uploads.uploader.choose'));
        expect(wrapper.text()).toContain(t('store.uploads.uploader.hint'));
        expect(wrapper.get('input[type="file"]').attributes()).toMatchObject({
            multiple: '',
            accept: 'image/*',
        });
        expect(t('store.uploads.uploader.choose')).toBe(
            'Choose files to upload',
        );
    });

    it('posts each chosen file on its own, one after another, to the store endpoint', async () => {
        const wrapper = mount(StoreFileUploader);
        const first = file('a.png');
        const second = file('b.png');

        await chooseFiles(wrapper, [first, second]);

        expect(postMock).toHaveBeenCalledTimes(1);
        expect(postMock.mock.calls[0][0]).toBe(storeUpload.url());
        expect(http.sentFiles).toEqual([first]);
        expect(wrapper.findAll('li').map((li) => li.text())).toEqual([
            `a.png${t('store.uploads.uploader.status.uploading')}`,
            `b.png${t('store.uploads.uploader.status.waiting')}`,
        ]);
        expect(wrapper.findComponent(LoaderCircle).exists()).toBe(true);

        finish(0, (options) => options.onSuccess?.(uploadFixture({ id: 1 })));
        await flushPromises();

        expect(postMock).toHaveBeenCalledTimes(2);
        expect(http.sentFiles).toEqual([first, second]);
        expect(wrapper.emitted('uploaded')).toEqual([
            [uploadFixture({ id: 1 })],
        ]);
    });

    it('shows the upload progress', async () => {
        const wrapper = mount(StoreFileUploader);

        await chooseFiles(wrapper, [file('a.png')]);
        postMock.mock.calls[0][1].onProgress?.({ loaded: 30, total: 120 });
        await flushPromises();

        expect(wrapper.get('progress').attributes()).toMatchObject({
            max: '100',
            value: '25',
            'aria-label': 'a.png',
        });

        postMock.mock.calls[0][1].onProgress?.({
            loaded: 60,
            total: 120,
            percentage: 50,
        });
        await flushPromises();

        expect(wrapper.get('progress').attributes('value')).toBe('50');
    });

    it('marks an uploaded file done', async () => {
        const wrapper = mount(StoreFileUploader);

        await chooseFiles(wrapper, [file('a.png')]);
        finish(0, (options) => options.onSuccess?.(uploadFixture()));
        await flushPromises();

        expect(wrapper.get('li').text()).toContain(
            t('store.uploads.uploader.status.done'),
        );
        expect(wrapper.findComponent(Check).exists()).toBe(true);
        expect(wrapper.find('progress').exists()).toBe(false);
    });

    it("shows the server's reason for a refused file, and a general one for any other failure", async () => {
        const wrapper = mount(StoreFileUploader);

        await chooseFiles(wrapper, [file('a.png'), file('b.png')]);
        finish(0, (options) =>
            options.onError?.({ file: 'This file type is not allowed.' }),
        );
        await flushPromises();
        finish(1, (options) => options.onHttpException?.());
        await flushPromises();

        const items = wrapper.findAll('li');
        expect(items[0].text()).toContain('This file type is not allowed.');
        expect(items[0].text()).toContain(
            t('store.uploads.uploader.status.failed'),
        );
        expect(items[1].text()).toContain(t('store.uploads.uploader.failed'));
        expect(wrapper.findAllComponents(CircleAlert)).toHaveLength(2);
        expect(wrapper.emitted('uploaded')).toBeUndefined();
    });

    it('says when the network failed', async () => {
        const wrapper = mount(StoreFileUploader);

        await chooseFiles(wrapper, [file('a.png')]);
        finish(0, (options) => options.onNetworkError?.());
        await flushPromises();

        expect(wrapper.get('li').text()).toContain(
            t('store.uploads.uploader.failed'),
        );
    });

    it('refuses a file over 512 MB without sending it', async () => {
        const wrapper = mount(StoreFileUploader);

        await chooseFiles(wrapper, [file('big.zip', 512 * 1024 * 1024 + 1)]);

        expect(postMock).not.toHaveBeenCalled();
        expect(wrapper.get('li').text()).toContain(
            t('store.uploads.uploader.tooLarge'),
        );
    });
});

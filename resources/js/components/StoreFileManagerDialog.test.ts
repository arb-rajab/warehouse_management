import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import { browse as browseUploads } from '@/actions/App/Http/Controllers/Store/Admin/UploadController';
import FilterDialog from '@/components/FilterDialog.vue';
import StoreFileFilterBar from '@/components/StoreFileFilterBar.vue';
import StoreFileGrid from '@/components/StoreFileGrid.vue';
import StoreFileManagerDialog from '@/components/StoreFileManagerDialog.vue';
import StoreFileUploader from '@/components/StoreFileUploader.vue';
import { t } from '@/lib/i18n';
import { paginated, storeUpload } from '@/testing/factories';
import type { Paginated } from '@/types/admin';
import type { StoreUpload, StoreUploadKind } from '@/types/store';

interface GetOptions {
    onSuccess?: (response: Paginated<StoreUpload>) => void;
}

const { getMock, httpState } = vi.hoisted(() => ({
    getMock: vi.fn<(url: string, options: GetOptions) => void>(),
    httpState: { processing: false },
}));

vi.mock('@inertiajs/vue3', () => ({
    useHttp: () =>
        reactive({
            file: null,
            get processing() {
                return httpState.processing;
            },
            get: (url: string, options: GetOptions) => {
                getMock(url, options);

                return Promise.resolve();
            },
            post: () => Promise.resolve(),
        }),
}));

const logo = storeUpload({ id: 1, name: 'logo' });
const banner = storeUpload({ id: 2, name: 'banner' });

function respond(index: number, response: Paginated<StoreUpload>): void {
    getMock.mock.calls[index][1].onSuccess?.(response);
}

async function mountDialog({
    multiple = false,
    type,
    selected = [],
}: {
    multiple?: boolean;
    type?: StoreUploadKind;
    selected?: StoreUpload[];
} = {}) {
    const wrapper = mount(StoreFileManagerDialog, {
        props: {
            open: false,
            multiple,
            type,
            selected,
            'onUpdate:open': (open: boolean) => wrapper.setProps({ open }),
        },
    });
    await wrapper.setProps({ open: true });

    return wrapper;
}

function button(
    wrapper: Awaited<ReturnType<typeof mountDialog>>,
    label: string,
) {
    const found = wrapper
        .findAll('button')
        .find((candidate) => candidate.text().includes(label));

    if (!found) {
        throw new Error(`No button "${label}"`);
    }

    return found;
}

describe('StoreFileManagerDialog', () => {
    beforeEach(() => {
        getMock.mockReset();
        httpState.processing = false;
    });

    it('opens as a dialog titled File manager, with both tabs and its footer', async () => {
        const wrapper = await mountDialog();

        expect(wrapper.getComponent(FilterDialog).props()).toMatchObject({
            open: true,
            title: t('store.uploads.dialog.title'),
            closeLabel: t('common.close'),
        });
        expect(t('store.uploads.dialog.title')).toBe('File manager');
        expect(
            button(wrapper, t('store.uploads.dialog.browseTab')).exists(),
        ).toBe(true);
        expect(
            button(wrapper, t('store.uploads.dialog.uploadTab')).exists(),
        ).toBe(true);
        expect(
            button(wrapper, t('store.uploads.dialog.confirm')).exists(),
        ).toBe(true);
        expect(button(wrapper, t('store.uploads.dialog.clear')).exists()).toBe(
            true,
        );
        expect(wrapper.text()).toContain(
            t('store.uploads.dialog.selectedCount', { count: 0 }),
        );
    });

    it("loads the first page of the account's files, newest first, when it opens", async () => {
        const wrapper = await mountDialog({ type: 'image' });

        expect(getMock.mock.calls[0][0]).toBe(
            browseUploads.url({
                query: { sort: 'newest', type: 'image', page: 1 },
            }),
        );

        respond(0, paginated([logo, banner], 60));
        await flushPromises();

        expect(wrapper.getComponent(StoreFileGrid).props()).toEqual({
            files: [logo, banner],
            selectedIds: [],
            emptyMessage: t('store.uploads.empty'),
        });
    });

    it('reloads with the search and sort the filter bar submits', async () => {
        const wrapper = await mountDialog();
        const bar = wrapper.getComponent(StoreFileFilterBar);

        expect(bar.props('idPrefix')).toBe('file-manager');

        bar.vm.$emit('update:search', 'logo');
        bar.vm.$emit('update:sort', 'largest');
        bar.vm.$emit('submit');
        await flushPromises();

        expect(getMock.mock.calls[1][0]).toBe(
            browseUploads.url({
                query: { search: 'logo', sort: 'largest', page: 1 },
            }),
        );
    });

    it('loads more files onto the grid while pages remain, and says when it is loading', async () => {
        const wrapper = await mountDialog();
        respond(0, paginated([logo], 60, { currentPage: 1, lastPage: 2 }));
        await flushPromises();

        await button(wrapper, t('store.uploads.dialog.loadMore')).trigger(
            'click',
        );
        expect(getMock.mock.calls[1][0]).toBe(
            browseUploads.url({ query: { sort: 'newest', page: 2 } }),
        );

        respond(1, paginated([banner], 60, { currentPage: 2, lastPage: 2 }));
        httpState.processing = true;
        await flushPromises();

        expect(wrapper.getComponent(StoreFileGrid).props('files')).toEqual([
            logo,
            banner,
        ]);
        expect(wrapper.text()).toContain(t('store.uploads.dialog.loading'));

        httpState.processing = false;
        await flushPromises();

        expect(wrapper.text()).not.toContain(
            t('store.uploads.dialog.loadMore'),
        );
    });

    it('picks one file at a time for a single field, and hands it back on confirm', async () => {
        const wrapper = await mountDialog({ selected: [banner] });
        respond(0, paginated([logo, banner], 60));
        await flushPromises();
        const grid = wrapper.getComponent(StoreFileGrid);

        expect(grid.props('selectedIds')).toEqual([2]);

        grid.vm.$emit('toggle', logo);
        await flushPromises();
        expect(grid.props('selectedIds')).toEqual([1]);

        await button(wrapper, t('store.uploads.dialog.confirm')).trigger(
            'click',
        );

        expect(wrapper.emitted('select')).toEqual([[[logo]]]);
        expect(wrapper.emitted('update:open')).toEqual([[false]]);
    });

    it('picks several files for a multiple field, untoggles one, and clears them all', async () => {
        const wrapper = await mountDialog({ multiple: true });
        respond(0, paginated([logo, banner], 60));
        await flushPromises();
        const grid = wrapper.getComponent(StoreFileGrid);

        grid.vm.$emit('toggle', logo);
        grid.vm.$emit('toggle', banner);
        await flushPromises();
        expect(grid.props('selectedIds')).toEqual([1, 2]);
        expect(wrapper.text()).toContain(
            t('store.uploads.dialog.selectedCount', { count: 2 }),
        );

        grid.vm.$emit('toggle', logo);
        await flushPromises();
        expect(grid.props('selectedIds')).toEqual([2]);

        await button(wrapper, t('store.uploads.dialog.clear')).trigger('click');
        expect(grid.props('selectedIds')).toEqual([]);
    });

    it('uploads on the upload tab, adding a new file of the right kind to the grid and the selection', async () => {
        const wrapper = await mountDialog({ type: 'image' });
        respond(0, paginated([banner], 60));
        await flushPromises();

        await button(wrapper, t('store.uploads.dialog.uploadTab')).trigger(
            'click',
        );
        const uploader = wrapper.getComponent(StoreFileUploader);

        expect(uploader.props('accept')).toBe('image/*');
        expect(uploader.isVisible()).toBe(true);

        uploader.vm.$emit('uploaded', logo);
        uploader.vm.$emit(
            'uploaded',
            storeUpload({ id: 3, type: 'video', extension: 'mp4' }),
        );
        await flushPromises();

        const grid = wrapper.getComponent(StoreFileGrid);
        expect(grid.props('files')).toEqual([logo, banner]);
        expect(grid.props('selectedIds')).toEqual([1]);
    });

    it('accepts any file when no kind is asked for', async () => {
        const wrapper = await mountDialog();

        expect(
            wrapper.getComponent(StoreFileUploader).props('accept'),
        ).toBeUndefined();
    });
});

import { File, FileVideo, PackageSearch } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import StoreFileGrid from '@/components/StoreFileGrid.vue';
import { t } from '@/lib/i18n';
import { storeUpload } from '@/testing/factories';
import type { StoreUpload } from '@/types/store';

function mountGrid(files: StoreUpload[], selectedIds: number[] = []) {
    return mount(StoreFileGrid, {
        props: { files, selectedIds, emptyMessage: 'Nothing here' },
        slots: {
            actions: `<template #actions="{ file }"><button class="action">Act on {{ file.id }}</button></template>`,
        },
    });
}

describe('StoreFileGrid', () => {
    it('shows the empty message when there are no files', () => {
        const wrapper = mountGrid([]);

        expect(wrapper.find('ul').exists()).toBe(false);
        expect(wrapper.text()).toBe('Nothing here');
        expect(wrapper.findComponent(PackageSearch).exists()).toBe(true);
    });

    it('shows an image as a thumbnail with its name, extension and size', () => {
        const wrapper = mountGrid([storeUpload()]);
        const tile = wrapper.get('li');

        expect(tile.get('img').attributes()).toMatchObject({
            src: 'http://localhost/uploads/all/shop-front.png',
            alt: '',
            loading: 'lazy',
        });
        expect(tile.text()).toContain('shop front.png');
        expect(tile.text()).toContain('2 kB');
    });

    it('shows a video and any other file as an icon, and an image without a url too', () => {
        const wrapper = mountGrid([
            storeUpload({ id: 1, type: 'video', extension: 'mp4' }),
            storeUpload({ id: 2, type: 'document', extension: 'pdf' }),
            storeUpload({ id: 3, url: null }),
        ]);
        const tiles = wrapper.findAll('li');

        expect(tiles[0].findComponent(FileVideo).exists()).toBe(true);
        expect(tiles[1].findComponent(File).exists()).toBe(true);
        expect(tiles[2].find('img').exists()).toBe(false);
        expect(tiles[2].findComponent(File).exists()).toBe(true);
    });

    it('labels each checkbox with the file and ticks the selected ones', () => {
        const wrapper = mountGrid(
            [storeUpload({ id: 1 }), storeUpload({ id: 2, name: 'logo' })],
            [2],
        );
        const boxes = wrapper.findAll('input[type="checkbox"]');

        expect(boxes[0].attributes('aria-label')).toBe(
            t('store.uploads.selectFile', { name: 'shop front.png' }),
        );
        expect((boxes[0].element as HTMLInputElement).checked).toBe(false);
        expect((boxes[1].element as HTMLInputElement).checked).toBe(true);
        expect(wrapper.findAll('li')[1].classes()).toContain('ring-2');
        expect(t('store.uploads.selectFile', { name: 'a' })).toBe('Select a');
    });

    it('emits toggle with the file when its checkbox changes', async () => {
        const file = storeUpload();
        const wrapper = mountGrid([file]);

        await wrapper.get('input[type="checkbox"]').trigger('change');

        expect(wrapper.emitted('toggle')).toEqual([[file]]);
    });

    it('renders the actions slot for each file', () => {
        const wrapper = mountGrid([
            storeUpload({ id: 1 }),
            storeUpload({ id: 2 }),
        ]);

        expect(wrapper.findAll('.action').map((b) => b.text())).toEqual([
            'Act on 1',
            'Act on 2',
        ]);
    });
});

import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import StoreFileFilterBar from '@/components/StoreFileFilterBar.vue';
import { t } from '@/lib/i18n';
import type { StoreUploadSort } from '@/types/store';

function mountBar(search = '', sort: StoreUploadSort = 'newest') {
    return mount(StoreFileFilterBar, {
        props: {
            idPrefix: 'files',
            search,
            sort,
            'onUpdate:search': () => undefined,
            'onUpdate:sort': () => undefined,
        },
    });
}

describe('StoreFileFilterBar', () => {
    it('labels the search box and the sort menu, and the search button', () => {
        const wrapper = mountBar();

        expect(wrapper.get('label[for="files-search"]').text()).toBe(
            t('store.uploads.filters.search'),
        );
        expect(wrapper.get('label[for="files-sort"]').text()).toBe(
            t('store.uploads.filters.sort'),
        );
        expect(wrapper.get('button[type="submit"]').text()).toBe(
            t('store.uploads.filters.submit'),
        );
        expect(wrapper.get('#files-search').attributes()).toMatchObject({
            type: 'search',
            maxlength: '255',
            placeholder: t('store.uploads.filters.searchPlaceholder'),
        });
        expect(t('store.uploads.filters.submit')).toBe('Search');
    });

    it('offers the four sorts in order', () => {
        const wrapper = mountBar();

        expect(wrapper.findAll('option').map((o) => o.text())).toEqual([
            t('store.uploads.sort.newest'),
            t('store.uploads.sort.oldest'),
            t('store.uploads.sort.smallest'),
            t('store.uploads.sort.largest'),
        ]);
        expect(
            wrapper.findAll('option').map((o) => o.attributes('value')),
        ).toEqual(['newest', 'oldest', 'smallest', 'largest']);
    });

    it('shows the current search and sort', () => {
        const wrapper = mountBar('logo', 'largest');

        expect(
            (wrapper.get('#files-search').element as HTMLInputElement).value,
        ).toBe('logo');
        expect(
            (wrapper.get('#files-sort').element as HTMLSelectElement).value,
        ).toBe('largest');
    });

    it('updates the models, and submits on search and on a new sort', async () => {
        const wrapper = mountBar();

        await wrapper.get('#files-search').setValue('report');
        expect(wrapper.emitted('update:search')).toEqual([['report']]);

        await wrapper.get('form').trigger('submit');
        expect(wrapper.emitted('submit')).toHaveLength(1);

        await wrapper.get('#files-sort').setValue('oldest');
        expect(wrapper.emitted('update:sort')).toEqual([['oldest']]);
        expect(wrapper.emitted('submit')).toHaveLength(2);
    });
});

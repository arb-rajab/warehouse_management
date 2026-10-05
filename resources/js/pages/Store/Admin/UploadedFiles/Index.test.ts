import { Form, Head, Link } from '@inertiajs/vue3';
import type { DOMWrapper } from '@vue/test-utils';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    bulkDestroy,
    create as uploadedFilesCreate,
    destroy as uploadedFilesDestroy,
    index as uploadedFilesIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/UploadController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreFileFilterBar from '@/components/StoreFileFilterBar.vue';
import StoreFileGrid from '@/components/StoreFileGrid.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import { paginated, storeSharedProps, storeUpload } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type { StoreUpload, StoreUploadFilters } from '@/types/store';
import Index from './Index.vue';

const { formSlotPropsMock, usePageMock, routerGetMock } = vi.hoisted(() => ({
    formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { get: routerGetMock },
    };
});

const logo = storeUpload({ id: 1, name: 'logo' });
const report = storeUpload({
    id: 2,
    name: 'report',
    extension: 'pdf',
    type: 'document',
    uploaded_by: null,
});

function mountPage(
    files: StoreUpload[] = [logo, report],
    filters: Partial<StoreUploadFilters> = {},
) {
    usePageMock.mockReturnValue({
        url: uploadedFilesIndex.url(),
        props: defaultAuthProps({ store: storeSharedProps() }),
    });

    return mount(Index, {
        props: {
            uploads: paginated(files),
            filters: { search: null, sort: 'newest', per_page: 20, ...filters },
        },
    });
}

const panelSelector = `aside[aria-label="${t('store.uploads.info.title')}"]`;

function tile(wrapper: ReturnType<typeof mountPage>, index: number) {
    return wrapper.getComponent(StoreFileGrid).findAll('li')[index];
}

function buttonIn(
    element: {
        findAll: (selector: 'button') => DOMWrapper<HTMLButtonElement>[];
    },
    label: string,
) {
    const found = element
        .findAll('button')
        .find((candidate) => candidate.text().includes(label));

    if (!found) {
        throw new Error(`No button "${label}"`);
    }

    return found;
}

describe('Store uploaded files Index', () => {
    beforeEach(() => {
        resetMocks({ formSlotPropsMock, usePageMock, routerGetMock });
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('renders inside the store layout, titled in the Head and the header', () => {
        const wrapper = mountPage();

        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('store.uploads.index.title'),
        );
        expect(wrapper.getComponent(PageHeader).props('title')).toBe(
            t('store.uploads.index.title'),
        );
        expect(t('store.uploads.index.title')).toBe('All uploaded files');
    });

    it('links the add button to the upload page', () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(AddResourceLink).props()).toEqual({
            href: uploadedFilesCreate(),
            label: t('store.uploads.index.uploadNew'),
        });
    });

    it('hands the files to the grid, nothing ticked, with the empty message', () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(StoreFileGrid).props()).toEqual({
            files: [logo, report],
            selectedIds: [],
            emptyMessage: t('store.uploads.empty'),
        });
    });

    it('shows the empty message when there are no files', () => {
        const wrapper = mountPage([]);

        expect(wrapper.text()).toContain(t('store.uploads.empty'));
        expect(
            (
                wrapper.get('form input[type="checkbox"]')
                    .element as HTMLInputElement
            ).disabled,
        ).toBe(true);
    });

    it('starts the filter bar from the current filters and reloads on submit', async () => {
        const wrapper = mountPage([logo], {
            search: 'logo',
            sort: 'largest',
            per_page: 50,
        });
        const bar = wrapper.getComponent(StoreFileFilterBar);

        expect(bar.props()).toEqual({
            idPrefix: 'uploaded-files',
            search: 'logo',
            sort: 'largest',
        });

        bar.vm.$emit('update:search', 'report');
        bar.vm.$emit('update:sort', 'oldest');
        bar.vm.$emit('submit');

        expect(routerGetMock).toHaveBeenCalledWith(
            uploadedFilesIndex.url(),
            { search: 'report', sort: 'oldest', per_page: 50 },
            { preserveState: true, replace: true },
        );
    });

    it('drops an empty search from the reload', () => {
        const wrapper = mountPage();

        wrapper.getComponent(StoreFileFilterBar).vm.$emit('submit');

        expect(routerGetMock).toHaveBeenCalledWith(
            uploadedFilesIndex.url(),
            { search: undefined, sort: 'newest', per_page: 20 },
            { preserveState: true, replace: true },
        );
    });

    it('pages the grid, and reloads it at a new page size', () => {
        const wrapper = mountPage();
        const pagination = wrapper.getComponent(Pagination);

        expect(pagination.props()).toEqual({ links: [], perPage: 20 });

        pagination.vm.$emit('update:perPage', 50);

        expect(routerGetMock).toHaveBeenCalledWith(
            uploadedFilesIndex.url(),
            { search: undefined, sort: 'newest', per_page: 50 },
            { preserveState: true, replace: true },
        );
    });

    it('ticks files one by one or all at once, and posts the ticked ids to the bulk delete', async () => {
        const wrapper = mountPage();
        const grid = wrapper.getComponent(StoreFileGrid);
        const form = wrapper.getComponent(Form);
        const deleteSelection = buttonIn(
            form,
            t('store.uploads.index.deleteSelection'),
        );

        expect(form.props()).toMatchObject({
            action: bulkDestroy({ mergeQuery: {} }),
            options: { preserveScroll: true },
        });
        expect(form.text()).toContain(t('store.uploads.index.selectAll'));
        expect(deleteSelection.attributes('disabled')).toBeDefined();

        grid.vm.$emit('toggle', report);
        await flushPromises();

        expect(grid.props('selectedIds')).toEqual([2]);
        expect(
            form
                .findAll('input[type="hidden"][name="ids[]"]')
                .map((input) => input.attributes('value')),
        ).toEqual(['2']);
        expect(deleteSelection.attributes('disabled')).toBeUndefined();

        await form.get('input[type="checkbox"]').trigger('change');
        expect(grid.props('selectedIds')).toEqual([1, 2]);
        expect(
            (form.get('input[type="checkbox"]').element as HTMLInputElement)
                .checked,
        ).toBe(true);

        await form.get('input[type="checkbox"]').trigger('change');
        expect(grid.props('selectedIds')).toEqual([]);
    });

    it('asks before a bulk delete and clears the ticks after it', async () => {
        const wrapper = mountPage();
        const grid = wrapper.getComponent(StoreFileGrid);
        grid.vm.$emit('toggle', logo);
        grid.vm.$emit('toggle', report);
        await flushPromises();

        const form = wrapper.getComponent(Form);
        const onBefore = form.props('onBefore') as () => boolean;
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);

        expect(onBefore()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', {
                label: t('store.uploads.index.selection', { count: 2 }),
            }),
        );

        form.vm.$emit('success');
        await flushPromises();

        expect(grid.props('selectedIds')).toEqual([]);
    });

    it('disables the bulk delete while it is processing', () => {
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: true });
        const wrapper = mountPage();

        expect(
            buttonIn(
                wrapper.getComponent(Form),
                t('store.uploads.index.deleteSelection'),
            ).attributes('disabled'),
        ).toBeDefined();
    });

    it('gives each file its details, download, copy-link and delete actions', () => {
        const wrapper = mountPage();
        const first = tile(wrapper, 0);

        expect(
            buttonIn(first, t('store.uploads.index.details')).attributes(
                'aria-label',
            ),
        ).toBe(t('store.uploads.index.detailsFor', { name: 'logo.png' }));
        expect(first.get('a[download]').attributes()).toMatchObject({
            href: logo.url,
            download: 'logo.png',
            target: '_blank',
            rel: 'noopener',
        });
        expect(first.get('a[download]').text()).toBe(
            t('store.uploads.index.download'),
        );
        expect(
            buttonIn(first, t('store.uploads.index.copyLink')).exists(),
        ).toBe(true);
    });

    it('offers no download or copy for a file without a url', () => {
        const wrapper = mountPage([storeUpload({ url: null })]);

        expect(tile(wrapper, 0).find('a[download]').exists()).toBe(false);
        expect(tile(wrapper, 0).text()).not.toContain(
            t('store.uploads.index.copyLink'),
        );
    });

    it('deletes a file through a confirmed DELETE that keeps the page and filters', () => {
        const wrapper = mountPage();
        const deleteLink = tile(wrapper, 0).getComponent(TableActionLink);

        expect(deleteLink.props()).toEqual({
            href: uploadedFilesDestroy(logo, { mergeQuery: {} }),
            variant: 'danger',
        });
        expect(deleteLink.attributes('method')).toBe('delete');
        expect(deleteLink.text()).toBe(t('store.uploads.index.delete'));

        const onBefore = deleteLink
            .getComponent(Link)
            .props('onBefore') as () => boolean;
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        expect(onBefore()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: 'logo.png' }),
        );
    });

    it('copies a link to the clipboard and says so, or says it could not', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.defineProperty(navigator, 'clipboard', {
            value: { writeText },
            configurable: true,
        });
        const wrapper = mountPage();

        await buttonIn(
            tile(wrapper, 0),
            t('store.uploads.index.copyLink'),
        ).trigger('click');
        await flushPromises();

        expect(writeText).toHaveBeenCalledWith(logo.url);
        expect(wrapper.get('[aria-live="polite"]').text()).toBe(
            t('store.uploads.index.linkCopied'),
        );

        writeText.mockRejectedValue(new Error('denied'));
        await buttonIn(
            tile(wrapper, 1),
            t('store.uploads.index.copyLink'),
        ).trigger('click');
        await flushPromises();

        expect(wrapper.get('[aria-live="polite"]').text()).toBe(
            t('store.uploads.index.linkNotCopied'),
        );
    });

    it('shows a file’s details in a side panel, with every label, and closes it', async () => {
        const wrapper = mountPage();

        expect(wrapper.find(panelSelector).exists()).toBe(false);

        await buttonIn(
            tile(wrapper, 0),
            t('store.uploads.index.details'),
        ).trigger('click');
        const panel = wrapper.get(panelSelector);

        expect(panel.attributes('aria-label')).toBe(
            t('store.uploads.info.title'),
        );
        expect(panel.get('h2').text()).toBe(t('store.uploads.info.title'));
        expect(panel.findAll('dt').map((dt) => dt.text())).toEqual([
            t('store.uploads.info.name'),
            t('store.uploads.info.type'),
            t('store.uploads.info.size'),
            t('store.uploads.info.uploadedBy'),
            t('store.uploads.info.uploadedAt'),
        ]);
        expect(panel.findAll('dd').map((dd) => dd.text())).toEqual([
            'logo.png',
            t('store.uploads.kind.image'),
            '2 kB',
            'Sam Staff',
            formatDateTime('2026-10-01T09:30:00+00:00'),
        ]);
        expect(panel.get('a[download]').attributes('href')).toBe(logo.url);
        expect(panel.get('a[download]').text()).toBe(
            t('store.uploads.info.download'),
        );

        await panel
            .get(`button[aria-label="${t('common.close')}"]`)
            .trigger('click');

        expect(wrapper.find(panelSelector).exists()).toBe(false);
    });

    it('says Unknown in the panel for what a file does not record', async () => {
        const wrapper = mountPage([
            storeUpload({ type: null, uploaded_by: null, created_at: null }),
        ]);

        await buttonIn(
            tile(wrapper, 0),
            t('store.uploads.index.details'),
        ).trigger('click');
        const values = wrapper
            .findAll(`${panelSelector} dd`)
            .map((dd) => dd.text());

        expect([values[1], values[3], values[4]]).toEqual([
            t('store.uploads.unknownName'),
            t('store.uploads.unknownName'),
            t('store.uploads.unknownName'),
        ]);
    });
});

import { Form, Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { ComponentPublicInstance } from 'vue';
import {
    destroy,
    edit,
    index as catalogueIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/ColorController';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import Pagination from '@/components/Pagination.vue';
import StoreColorFormFields from '@/components/StoreColorFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { paginated, storeColor, storeSharedProps } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type { StoreColor } from '@/types/storeCatalogue';

import Index from './Index.vue';

const { usePageMock, routerGetMock, routerPostMock, formSlotPropsMock } =
    vi.hoisted(() => ({
        usePageMock: vi.fn(),
        routerGetMock: vi.fn(),
        routerPostMock: vi.fn(),
        formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
    }));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, createFormStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        Form: createFormStub(formSlotPropsMock),
        usePage: usePageMock,
        router: { get: routerGetMock, post: routerPostMock },
    };
});
const permissions = ['view_colors', 'edit_color', 'delete_color'];
function mountPage(rows: StoreColor[] = [], allowed = permissions) {
    usePageMock.mockReturnValue({
        url: catalogueIndex.url(),
        props: defaultAuthProps({
            store: storeSharedProps({ permissions: allowed }),
        }),
    });

    return mount(Index, {
        props: {
            colors: paginated(rows),
            filters: { per_page: 20, search: '' },
        },
    });
}
function action(wrapper: ReturnType<typeof mountPage>, label: string) {
    return wrapper
        .findAllComponents(TableActionLink)
        .find((link) => link.text().includes(label));
}
describe('Store Colors Index', () => {
    beforeEach(() => {
        resetMocks({
            usePageMock,
            routerGetMock,
            routerPostMock,
            formSlotPropsMock,
        });
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });
    afterEach(() => vi.restoreAllMocks());
    it('renders the layout, title, every table label and empty message', () => {
        const wrapper = mountPage();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(
            wrapper
                .getComponent<ComponentPublicInstance<{ title: string }>>(Head)
                .props('title'),
        ).toBe(t('store.colors.index.title'));
        expect(wrapper.get('h1').text()).toBe(t('store.colors.index.title'));
        expect(
            wrapper
                .getComponent<
                    ComponentPublicInstance<{
                        columns: string[];
                        rows: StoreColor[];
                    }>
                >(DataTable)
                .props('columns'),
        ).toEqual([
            t('store.colors.index.columnName'),
            t('store.colors.index.columnCode'),
            t('store.colors.index.columnActions'),
        ]);
        expect(wrapper.text()).toContain(t('store.colors.index.empty'));
        expect(wrapper.text()).toContain(t('store.colors.index.add'));
    });
    it('passes rows and action routes and renders content as plain text', () => {
        const row = storeColor({ name: '<img src=x onerror=alert(1)>' });
        const wrapper = mountPage([row]);
        expect(
            wrapper
                .getComponent<
                    ComponentPublicInstance<{
                        columns: string[];
                        rows: StoreColor[];
                    }>
                >(DataTable)
                .props('rows'),
        ).toEqual([row]);
        expect(wrapper.text()).toContain('<img src=x onerror=alert(1)>');
        expect(wrapper.find('td img').exists()).toBe(false);
        expect(
            action(wrapper, t('store.colors.index.edit'))?.props('href'),
        ).toEqual(edit(row));
        expect(
            action(wrapper, t('store.colors.index.delete'))?.props(),
        ).toMatchObject({
            href: destroy(row, { mergeQuery: {} }),
            variant: 'danger',
        });
    });
    it('gates inline creation and both row mutations with backend permissions', () => {
        const row = storeColor();
        const wrapper = mountPage([row], ['view_colors']);
        expect(wrapper.findComponent(StoreColorFormFields).exists()).toBe(
            false,
        );
        expect(action(wrapper, t('store.colors.index.edit'))).toBeUndefined();
        expect(action(wrapper, t('store.colors.index.delete'))).toBeUndefined();
    });
    it('submits the inline form with errors, processing and reset behavior', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { name: 'Invalid.' },
            processing: true,
        });
        const wrapper = mountPage();
        const form = wrapper.findAllComponents(Form).find((form) => {
            const action = form.props('action');

            return typeof action === 'object' && action?.url === store().url;
        });
        expect(form?.props('action')).toEqual(store());
        expect(form?.props('resetOnSuccess')).toBe(true);
        expect(
            wrapper.getComponent(StoreColorFormFields).props(),
        ).toMatchObject({ errors: { name: 'Invalid.' } });
        const button = wrapper.findAllComponents(SubmitButton).at(-1);
        expect(button?.props()).toMatchObject({
            label: t('store.colors.fields.save'),
            processingLabel: t('store.colors.fields.saving'),
            processing: true,
        });
    });
    it('asks before each deletion and respects cancellation', () => {
        const row = storeColor();
        const wrapper = mountPage([row]);
        const link = action(
            wrapper,
            t('store.colors.index.delete'),
        )?.getComponent(Link);
        const onBefore = link?.props('onBefore') as (() => boolean) | undefined;
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        expect(onBefore?.()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: row.name }),
        );
        confirm.mockReturnValue(true);
        expect(onBefore?.()).toBe(true);
    });
    it('passes pagination props and keeps the current list filters when changing page size', async () => {
        const wrapper = mountPage([storeColor()]);
        expect(wrapper.getComponent(Pagination).props()).toMatchObject({
            links: paginated([]).meta.links,
            perPage: 20,
        });
        await wrapper.getComponent(Pagination).vm.$emit('update:perPage', 10);
        expect(routerGetMock).toHaveBeenCalledWith(
            catalogueIndex().url,
            { search: '', per_page: 10 },
            { preserveState: true, replace: true },
        );
    });

    it('preserves the color search and renders every search label', () => {
        const wrapper = mountPage([]);
        const filter = wrapper.findAllComponents(Form)[0];
        expect(filter.props('action')).toEqual(catalogueIndex());
        expect(
            wrapper
                .findAllComponents(FormField)
                .find((field) => field.props('id') === 'search')!
                .props('label'),
        ).toBe(t('store.colors.index.search'));
        expect(wrapper.get('#search').attributes('maxlength')).toBe('255');
        expect(wrapper.getComponent(SubmitButton).props()).toMatchObject({
            label: t('store.colors.index.searchSubmit'),
            processingLabel: t('store.colors.index.searching'),
        });
    });
});

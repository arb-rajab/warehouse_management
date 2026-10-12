import { Form, Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { ComponentPublicInstance } from 'vue';
import {
    destroy,
    edit,
    index as catalogueIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/AttributeController';
import { index as valuesIndex } from '@/actions/App/Http/Controllers/Store/Admin/AttributeValueController';
import DataTable from '@/components/DataTable.vue';

import Pagination from '@/components/Pagination.vue';
import StoreAttributeFormFields from '@/components/StoreAttributeFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import {
    paginated,
    storeAttribute,
    storeSharedProps,
} from '@/testing/factories';
import { storeAttributeValue } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type { StoreAttribute } from '@/types/storeCatalogue';
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
const permissions = [
    'view_product_attributes',
    'edit_product_attribute',
    'delete_product_attribute',
];
function mountPage(rows: StoreAttribute[] = [], allowed = permissions) {
    usePageMock.mockReturnValue({
        url: catalogueIndex.url(),
        props: defaultAuthProps({
            store: storeSharedProps({ permissions: allowed }),
        }),
    });

    return mount(Index, {
        props: { attributes: paginated(rows), filters: { per_page: 20 } },
    });
}
function action(wrapper: ReturnType<typeof mountPage>, label: string) {
    return wrapper
        .findAllComponents(TableActionLink)
        .find((link) => link.text().includes(label));
}
describe('Store Attributes Index', () => {
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
        ).toBe(t('store.attributes.index.title'));
        expect(wrapper.get('h1').text()).toBe(
            t('store.attributes.index.title'),
        );
        expect(
            wrapper
                .getComponent<
                    ComponentPublicInstance<{
                        columns: string[];
                        rows: StoreAttribute[];
                    }>
                >(DataTable)
                .props('columns'),
        ).toEqual([
            t('store.attributes.index.columnName'),
            t('store.attributes.index.columnValues'),
            t('store.attributes.index.columnActions'),
        ]);
        expect(wrapper.text()).toContain(t('store.attributes.index.empty'));
        expect(wrapper.text()).toContain(t('store.attributes.index.add'));
    });
    it('passes rows and action routes and renders content as plain text', () => {
        const row = storeAttribute({
            name: '<img src=x onerror=alert(1)>',
            values: [storeAttributeValue()],
        });
        const wrapper = mountPage([row]);
        expect(
            wrapper
                .getComponent<
                    ComponentPublicInstance<{
                        columns: string[];
                        rows: StoreAttribute[];
                    }>
                >(DataTable)
                .props('rows'),
        ).toEqual([row]);
        expect(wrapper.text()).toContain('<img src=x onerror=alert(1)>');
        expect(wrapper.find('td img').exists()).toBe(false);
        expect(
            action(wrapper, t('store.attributes.index.edit'))?.props('href'),
        ).toEqual(edit(row));
        expect(
            action(wrapper, t('store.attributes.index.delete'))?.props(),
        ).toMatchObject({
            href: destroy(row, { mergeQuery: {} }),
            variant: 'danger',
        });
        expect(wrapper.text()).toContain('Large');
    });
    it('gates inline creation and both row mutations with backend permissions', () => {
        const row = storeAttribute();
        const wrapper = mountPage([row], ['view_product_attributes']);
        expect(wrapper.findComponent(StoreAttributeFormFields).exists()).toBe(
            false,
        );
        expect(
            action(wrapper, t('store.attributes.index.edit')),
        ).toBeUndefined();
        expect(
            action(wrapper, t('store.attributes.index.delete')),
        ).toBeUndefined();
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
            wrapper.getComponent(StoreAttributeFormFields).props(),
        ).toMatchObject({ errors: { name: 'Invalid.' } });
        const button = wrapper.findAllComponents(SubmitButton).at(-1);
        expect(button?.props()).toMatchObject({
            label: t('store.attributes.fields.save'),
            processingLabel: t('store.attributes.fields.saving'),
            processing: true,
        });
    });
    it('asks before each deletion and respects cancellation', () => {
        const row = storeAttribute();
        const wrapper = mountPage([row]);
        const link = action(
            wrapper,
            t('store.attributes.index.delete'),
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
        const wrapper = mountPage([storeAttribute()]);
        expect(wrapper.getComponent(Pagination).props()).toMatchObject({
            links: paginated([]).meta.links,
            perPage: 20,
        });
        await wrapper.getComponent(Pagination).vm.$emit('update:perPage', 10);
        expect(routerGetMock).toHaveBeenCalledWith(
            catalogueIndex().url,
            { per_page: 10 },
            { preserveState: true, replace: true },
        );
    });

    it('gates attribute-value navigation with its own permission', () => {
        const row = storeAttribute();
        const wrapper = mountPage(
            [row],
            [...permissions, 'view_product_attribute_values'],
        );
        expect(
            action(wrapper, t('store.attributes.index.manageValues'))?.props(
                'href',
            ),
        ).toEqual(valuesIndex(row));
        expect(
            action(
                mountPage([row], permissions),
                t('store.attributes.index.manageValues'),
            ),
        ).toBeUndefined();
    });
});

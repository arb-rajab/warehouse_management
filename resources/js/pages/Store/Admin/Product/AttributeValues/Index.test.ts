import { Form, Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { ComponentPublicInstance } from 'vue';
import {
    destroy,
    edit,
    index as catalogueIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/AttributeValueController';
import DataTable from '@/components/DataTable.vue';

import Pagination from '@/components/Pagination.vue';
import StoreAttributeValueFormFields from '@/components/StoreAttributeValueFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import {
    paginated,
    storeAttributeValue,
    storeAttribute,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type { StoreAttributeValue } from '@/types/storeCatalogue';

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
    'view_product_attribute_values',
    'edit_product_attribute_value',
    'delete_product_attribute_value',
];
function mountPage(rows: StoreAttributeValue[] = [], allowed = permissions) {
    usePageMock.mockReturnValue({
        url: catalogueIndex.url({ id: 7 }),
        props: defaultAuthProps({
            store: storeSharedProps({ permissions: allowed }),
        }),
    });

    return mount(Index, {
        props: {
            values: paginated(rows),
            filters: { per_page: 20 },
            attribute: storeAttribute({ id: 7, name: 'Size' }),
        },
    });
}
function action(wrapper: ReturnType<typeof mountPage>, label: string) {
    return wrapper
        .findAllComponents(TableActionLink)
        .find((link) => link.text().includes(label));
}
describe('Store AttributeValues Index', () => {
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
        ).toBe(t('store.attributeValues.index.title', { name: 'Size' }));
        expect(wrapper.get('h1').text()).toBe(
            t('store.attributeValues.index.title', { name: 'Size' }),
        );
        expect(
            wrapper
                .getComponent<
                    ComponentPublicInstance<{
                        columns: string[];
                        rows: StoreAttributeValue[];
                    }>
                >(DataTable)
                .props('columns'),
        ).toEqual([
            t('store.attributeValues.index.columnValue'),
            t('store.attributeValues.index.columnActions'),
        ]);
        expect(wrapper.text()).toContain(
            t('store.attributeValues.index.empty'),
        );
        expect(wrapper.text()).toContain(t('store.attributeValues.index.add'));
    });
    it('passes rows and action routes and renders content as plain text', () => {
        const row = storeAttributeValue({
            value: '<img src=x onerror=alert(1)>',
        });
        const wrapper = mountPage([row]);
        expect(
            wrapper
                .getComponent<
                    ComponentPublicInstance<{
                        columns: string[];
                        rows: StoreAttributeValue[];
                    }>
                >(DataTable)
                .props('rows'),
        ).toEqual([row]);
        expect(wrapper.text()).toContain('<img src=x onerror=alert(1)>');
        expect(wrapper.find('td img').exists()).toBe(false);
        expect(
            action(wrapper, t('store.attributeValues.index.edit'))?.props(
                'href',
            ),
        ).toEqual(edit(row));
        expect(
            action(wrapper, t('store.attributeValues.index.delete'))?.props(),
        ).toMatchObject({
            href: destroy(row, { mergeQuery: {} }),
            variant: 'danger',
        });
    });
    it('gates inline creation and both row mutations with backend permissions', () => {
        const row = storeAttributeValue();
        const wrapper = mountPage([row], ['view_product_attribute_values']);
        expect(
            wrapper.findComponent(StoreAttributeValueFormFields).exists(),
        ).toBe(false);
        expect(
            action(wrapper, t('store.attributeValues.index.edit')),
        ).toBeUndefined();
        expect(
            action(wrapper, t('store.attributeValues.index.delete')),
        ).toBeUndefined();
    });
    it('submits the inline form with errors, processing and reset behavior', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { value: 'Invalid.' },
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
            wrapper.getComponent(StoreAttributeValueFormFields).props(),
        ).toMatchObject({ errors: { value: 'Invalid.' }, attributeId: 7 });
        const button = wrapper.findAllComponents(SubmitButton).at(-1);
        expect(button?.props()).toMatchObject({
            label: t('store.attributeValues.fields.save'),
            processingLabel: t('store.attributeValues.fields.saving'),
            processing: true,
        });
    });
    it('asks before each deletion and respects cancellation', () => {
        const row = storeAttributeValue();
        const wrapper = mountPage([row]);
        const link = action(
            wrapper,
            t('store.attributeValues.index.delete'),
        )?.getComponent(Link);
        const onBefore = link?.props('onBefore') as (() => boolean) | undefined;
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        expect(onBefore?.()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: row.value }),
        );
        confirm.mockReturnValue(true);
        expect(onBefore?.()).toBe(true);
    });
    it('passes pagination props and keeps the current list filters when changing page size', async () => {
        const wrapper = mountPage([storeAttributeValue()]);
        expect(wrapper.getComponent(Pagination).props()).toMatchObject({
            links: paginated([]).meta.links,
            perPage: 20,
        });
        await wrapper.getComponent(Pagination).vm.$emit('update:perPage', 10);
        expect(routerGetMock).toHaveBeenCalledWith(
            catalogueIndex({ id: 7 }).url,
            { per_page: 10 },
            { preserveState: true, replace: true },
        );
    });
});

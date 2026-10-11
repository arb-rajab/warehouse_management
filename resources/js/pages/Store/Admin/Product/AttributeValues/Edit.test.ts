import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    edit,
    index as catalogueIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/AttributeValueController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreAttributeValueFormFields from '@/components/StoreAttributeValueFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeAttributeValue, storeSharedProps } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import Edit from './Edit.vue';

const { usePageMock, routerPostMock, formSlotPropsMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
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
        router: { post: routerPostMock },
    };
});
const record = storeAttributeValue();
function mountPage() {
    usePageMock.mockReturnValue({
        url: edit.url(record),
        props: defaultAuthProps({ store: storeSharedProps() }),
    });

    return mount(Edit, { props: { value: record } });
}
describe('Store AttributeValues Edit', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, routerPostMock, formSlotPropsMock });
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });
    it('passes the layout, labels, routes and cancel target to the resource form', () => {
        const wrapper = mountPage();
        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            title: t('store.attributeValues.edit.title'),
            action: update(record),
            submitLabel: t('store.attributeValues.fields.save'),
            submittingLabel: t('store.attributeValues.fields.saving'),
            cancelHref: catalogueIndex(record.attribute_id),
            layout: StoreAdminLayout,
        });
        expect(wrapper.get('h1').text()).toBe(
            t('store.attributeValues.edit.title'),
        );
        expect(wrapper.text()).toContain(t('common.cancel'));
        expect(wrapper.getComponent(Form).props('action')).toEqual(
            update(record),
        );
        expect(
            wrapper.getComponent(StoreAttributeValueFormFields).props(),
        ).toMatchObject({
            value: record.value,
            attributeId: record.attribute_id,
        });
    });
    it('forwards validation errors and processing to the fields and submit button', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { value: 'Invalid.' },
            processing: true,
        });
        const wrapper = mountPage();
        expect(
            wrapper.getComponent(StoreAttributeValueFormFields).props('errors'),
        ).toEqual({ value: 'Invalid.' });
        expect(wrapper.getComponent(SubmitButton).props()).toMatchObject({
            processing: true,
            processingLabel: t('store.attributeValues.fields.saving'),
        });
    });
});

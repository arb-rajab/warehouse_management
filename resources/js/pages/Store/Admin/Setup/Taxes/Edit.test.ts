import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    index,
    update,
    edit,
} from '@/actions/App/Http/Controllers/Store/Admin/TaxController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreTaxFormFields from '@/components/StoreTaxFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeTax, storeSharedProps } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import Edit from './Edit.vue';
const {
    usePageMock,
    routerGetMock,
    routerPostMock,
    formSlotPropsMock,
    submitMock,
} = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
    routerPostMock: vi.fn(),
    formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
    submitMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, createUseFormStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        useForm: createUseFormStub(submitMock),
        usePage: usePageMock,
        router: { get: routerGetMock, post: routerPostMock },
    };
});

function mountPage() {
    usePageMock.mockReturnValue({
        url: edit(storeTax()).url,
        props: defaultAuthProps({
            store: storeSharedProps({ permissions: ['vat_&_tax_setup'] }),
        }),
    });

    return mount(Edit, { props: { tax: storeTax() } });
}
describe('Store Taxes Edit', () => {
    beforeEach(() => {
        resetMocks({
            usePageMock,
            routerPostMock,
            routerGetMock,
            formSlotPropsMock,
            submitMock,
        });
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });
    it('uses the store layout, localized labels and the correct bound action', () => {
        const wrapper = mountPage();
        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            layout: StoreAdminLayout,
            title: t('store.taxes.edit'),
            submitLabel: t('store.reference.save'),
            submittingLabel: t('store.reference.saving'),
            cancelHref: index(),
        });
        expect(wrapper.getComponent(Form).props('action')).toEqual(
            update(storeTax()),
        );
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
    });
    it('forwards errors and disables submission while processing', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { name: 'Name required' },
            processing: true,
        });
        const wrapper = mountPage();
        expect(
            wrapper.getComponent(StoreTaxFormFields).props('errors'),
        ).toEqual({ name: 'Name required' });
        expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(
            true,
        );
    });
    it('fills the edited record', () => {
        expect(
            mountPage().getComponent(StoreTaxFormFields).props('tax'),
        ).toEqual(storeTax());
    });
});

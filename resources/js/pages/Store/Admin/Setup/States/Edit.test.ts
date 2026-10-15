import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    index,
    update,
    edit,
} from '@/actions/App/Http/Controllers/Store/Admin/StateController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreStateFormFields from '@/components/StoreStateFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeState, storeSharedProps } from '@/testing/factories';
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
        url: edit(storeState()).url,
        props: defaultAuthProps({
            store: storeSharedProps({
                permissions: ['manage_shipping_states'],
            }),
        }),
    });

    return mount(Edit, {
        props: {
            state: storeState(),
            countries: [{ id: 11, name: 'Netherlands' }],
        },
    });
}
describe('Store States Edit', () => {
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
            title: t('store.states.edit'),
            submitLabel: t('store.reference.save'),
            submittingLabel: t('store.reference.saving'),
            cancelHref: index(),
        });
        expect(wrapper.getComponent(Form).props('action')).toEqual(
            update(storeState()),
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
            wrapper.getComponent(StoreStateFormFields).props('errors'),
        ).toEqual({ name: 'Name required' });
        expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(
            true,
        );
    });
    it('fills the edited record', () => {
        expect(
            mountPage().getComponent(StoreStateFormFields).props('state'),
        ).toEqual(storeState());
    });
});

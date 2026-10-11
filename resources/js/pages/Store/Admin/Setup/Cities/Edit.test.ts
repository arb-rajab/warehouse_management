import { Form, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    index,
    update,
    edit,
} from '@/actions/App/Http/Controllers/Store/Admin/CityController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreCityFormFields from '@/components/StoreCityFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeCity, storeSharedProps } from '@/testing/factories';
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
        url: edit(storeCity()).url,
        props: defaultAuthProps({
            store: storeSharedProps({
                permissions: ['manage_shipping_cities'],
            }),
        }),
    });

    return mount(Edit, {
        props: {
            city: storeCity(),
            states: [{ id: 12, name: 'North Holland' }],
            lang: 'ar',
            languages: [
                { code: 'en', name: 'English' },
                { code: 'ar', name: 'Arabic' },
            ],
            name: 'Arabic name',
        },
    });
}
describe('Store Cities Edit', () => {
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
            title: t('store.cities.edit'),
            submitLabel: t('store.reference.save'),
            submittingLabel: t('store.reference.saving'),
            cancelHref: index(),
        });
        expect(wrapper.getComponent(Form).props('action')).toEqual(
            update(storeCity()),
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
            wrapper.getComponent(StoreCityFormFields).props('errors'),
        ).toEqual({ name: 'Name required' });
        expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(
            true,
        );
    });
    it('fills the edited record', () => {
        expect(
            mountPage().getComponent(StoreCityFormFields).props('city'),
        ).toEqual(storeCity());
    });
    it('links translation tabs and posts the chosen language', () => {
        const wrapper = mountPage();
        expect(wrapper.get('input[name=lang]').attributes('value')).toBe('ar');
        expect(
            wrapper
                .findAllComponents(Link)
                .some(
                    (link) =>
                        JSON.stringify(link.props('href')) ===
                        JSON.stringify(
                            edit(storeCity(), { query: { lang: 'en' } }),
                        ),
                ),
        ).toBe(true);
        expect(wrapper.get('#name').element).toHaveProperty(
            'value',
            'Arabic name',
        );
    });
});

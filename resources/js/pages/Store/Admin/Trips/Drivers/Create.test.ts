import { Form, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    create,
    index as driversIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/DriverController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreDriverFormFields from '@/components/StoreDriverFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Create from './Create.vue';

const { formSlotPropsMock, usePageMock, routerPostMock } = vi.hoisted(() => ({
    formSlotPropsMock: vi.fn(),
    usePageMock: vi.fn(),
    routerPostMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { post: routerPostMock },
    };
});
const countries = [{ id: 1, name: 'Netherlands' }];
function mountPage() {
    usePageMock.mockReturnValue({
        url: create.url(),
        props: defaultAuthProps({ store: storeSharedProps() }),
    });

    return mount(Create, { props: { countries } });
}

describe('Store driver Create', () => {
    afterEach(() => {
        i18n.global.locale.value = 'en';
    });
    beforeEach(() => {
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });
    it.each(['en', 'ar'] as const)(
        'renders translated title submit labels and store layout in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountPage();
            expect(
                wrapper.getComponent(ResourceFormPage).props(),
            ).toMatchObject({
                title: t('store.drivers.create.title'),
                submitLabel: t('store.drivers.create.submit'),
                submittingLabel: t('store.drivers.create.submitting'),
                layout: StoreAdminLayout,
            });
            expect(wrapper.get('h1').text()).toBe(
                t('store.drivers.create.title'),
            );
            expect(wrapper.getComponent(SubmitButton).text()).toBe(
                t('store.drivers.create.submit'),
            );
            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        },
    );
    it('submits to driver creation and cancels to the driver list', () => {
        const wrapper = mountPage();
        expect(wrapper.getComponent(Form).props('action')).toEqual(store());
        expect(
            wrapper
                .findAllComponents(Link)
                .find((link) => link.text() === t('common.cancel'))
                ?.props('href'),
        ).toEqual(driversIndex());
    });
    it('passes countries and errors to fields in creation mode', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { email: 'Taken' },
            processing: false,
        });
        expect(
            mountPage().getComponent(StoreDriverFormFields).props(),
        ).toMatchObject({
            countries,
            errors: { email: 'Taken' },
            editing: false,
        });
    });
});

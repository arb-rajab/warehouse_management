import { Form, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    edit,
    index as driversIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/DriverController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreDriverFormFields from '@/components/StoreDriverFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import { storeDriver, storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Edit from './Edit.vue';
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
const driver = storeDriver();
const countries = [{ id: 1, name: 'Netherlands' }];
function mountPage() {
    usePageMock.mockReturnValue({
        url: edit.url(driver),
        props: defaultAuthProps({ store: storeSharedProps() }),
    });

    return mount(Edit, { props: { driver, countries } });
}

describe('Store driver Edit', () => {
    afterEach(() => {
        i18n.global.locale.value = 'en';
    });
    beforeEach(() => {
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });
    it.each(['en', 'ar'] as const)(
        'renders translated title and labels inside store layout in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountPage();
            expect(
                wrapper.getComponent(ResourceFormPage).props(),
            ).toMatchObject({
                title: t('store.drivers.edit.title', { name: driver.name }),
                submitLabel: t('store.drivers.edit.submit'),
                submittingLabel: t('store.drivers.edit.submitting'),
                layout: StoreAdminLayout,
            });
            expect(wrapper.get('h1').text()).toBe(
                t('store.drivers.edit.title', { name: driver.name }),
            );
            expect(wrapper.getComponent(SubmitButton).text()).toBe(
                t('store.drivers.edit.submit'),
            );
            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        },
    );
    it('submits PUT to the bound driver and cancels to the list', () => {
        const wrapper = mountPage();
        expect(wrapper.getComponent(Form).props('action')).toEqual(
            update(driver),
        );
        expect(
            wrapper
                .findAllComponents(Link)
                .find((link) => link.text() === t('common.cancel'))
                ?.props('href'),
        ).toEqual(driversIndex());
    });
    it('forwards all contact data and validation errors to the editing fields', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { name: 'Required' },
            processing: false,
        });
        expect(
            mountPage().getComponent(StoreDriverFormFields).props(),
        ).toMatchObject({
            name: driver.name,
            email: driver.email,
            phone: driver.phone,
            country: driver.country,
            countries,
            errors: { name: 'Required' },
            editing: true,
        });
    });
    it('shows one legend with the rendered required fields and no marks on optional controls', async () => {
        const wrapper = mountPage();
        await wrapper.vm.$nextTick();
        expect(wrapper.findAll('form [data-required-legend]')).toHaveLength(1);
        expect(
            wrapper.find('label[for="country"] [data-required-mark]').exists(),
        ).toBe(true);
        expect(wrapper.findAll('[data-required-mark]')).toHaveLength(3);
        wrapper.unmount();
    });
});

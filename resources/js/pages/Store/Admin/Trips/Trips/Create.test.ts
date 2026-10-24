import { Form, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    create,
    index as tripsIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/TripController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreTripFormFields from '@/components/StoreTripFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Create from './Create.vue';

const { formSlotPropsMock, usePageMock, getMock } = vi.hoisted(() => ({
    formSlotPropsMock: vi.fn(),
    usePageMock: vi.fn(),
    getMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        usePage: usePageMock,
        useHttp: () => ({ processing: false, get: getMock }),
        router: { post: vi.fn() },
    };
});
const orders = [{ id: 77, code: '20261013-0042', customer: 'Alma Customer' }];
const customers = [{ id: 9, name: 'Warehouse' }];
function mountPage() {
    usePageMock.mockReturnValue({
        url: create.url(),
        props: defaultAuthProps({ store: storeSharedProps() }),
    });

    return mount(Create, { props: { orders, customers } });
}

describe('Store trip Create', () => {
    afterEach(() => {
        i18n.global.locale.value = 'en';
    });
    beforeEach(() => {
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
        getMock.mockReset();
    });
    it.each(['en', 'ar'] as const)(
        'renders the translated title and submit inside the wide store layout in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountPage();
            expect(
                wrapper.getComponent(ResourceFormPage).props(),
            ).toMatchObject({
                title: t('store.trips.create.title'),
                submitLabel: t('store.trips.create.submit'),
                submittingLabel: t('store.trips.create.submitting'),
                layout: StoreAdminLayout,
                wide: true,
            });
            expect(wrapper.get('h1').text()).toBe(
                t('store.trips.create.title'),
            );
            expect(wrapper.getComponent(SubmitButton).text()).toBe(
                t('store.trips.create.submit'),
            );
            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        },
    );
    it('posts to the store action and cancels to the trip list', () => {
        const wrapper = mountPage();
        expect(wrapper.getComponent(Form).props('action')).toEqual(store());
        expect(
            wrapper
                .findAllComponents(Link)
                .find((link) => link.text() === t('common.cancel'))
                ?.props('href'),
        ).toEqual(tripsIndex());
    });
    it('passes the choices and errors to empty trip fields', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { truck_id: 'Truck taken' },
            processing: false,
        });
        const fields = mountPage().getComponent(StoreTripFormFields);
        expect(fields.props()).toEqual({
            trip: undefined,
            orders,
            customers,
            errors: { truck_id: 'Truck taken' },
        });
        expect(getMock).not.toHaveBeenCalled();
    });
    it('shows one legend with the rendered required fields and no marks on optional controls', async () => {
        const wrapper = mountPage();
        await wrapper.vm.$nextTick();
        expect(wrapper.findAll('form [data-required-legend]')).toHaveLength(1);
        expect(
            wrapper.find('label[for="truck_id"] [data-required-mark]').exists(),
        ).toBe(true);
        expect(wrapper.findAll('[data-required-mark]')).toHaveLength(3);
        wrapper.unmount();
    });
});

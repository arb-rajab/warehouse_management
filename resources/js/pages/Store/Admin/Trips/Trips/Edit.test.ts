import { Form, Link } from '@inertiajs/vue3';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    edit,
    index as tripsIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/TripController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreTripFormFields from '@/components/StoreTripFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import { storeSharedProps, storeTripForm } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Edit from './Edit.vue';

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
const trip = storeTripForm();
const orders = [{ id: 78, code: '20261013-0041', customer: 'Bram Customer' }];
const customers = [{ id: 9, name: 'Warehouse' }];
function mountPage() {
    usePageMock.mockReturnValue({
        url: edit.url(trip),
        props: defaultAuthProps({ store: storeSharedProps() }),
    });

    return mount(Edit, { props: { trip, orders, customers } });
}

describe('Store trip Edit', () => {
    afterEach(() => {
        i18n.global.locale.value = 'en';
    });
    beforeEach(() => {
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
        getMock.mockReset();
        getMock.mockResolvedValue({ drivers: [], trucks: [] });
    });
    it.each(['en', 'ar'] as const)(
        'renders the translated title with the trip code inside the wide store layout in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountPage();
            expect(
                wrapper.getComponent(ResourceFormPage).props(),
            ).toMatchObject({
                title: t('store.trips.edit.title', { code: trip.code }),
                submitLabel: t('store.trips.edit.submit'),
                submittingLabel: t('store.trips.edit.submitting'),
                layout: StoreAdminLayout,
                wide: true,
            });
            expect(wrapper.get('h1').text()).toBe(
                t('store.trips.edit.title', { code: trip.code }),
            );
            expect(wrapper.getComponent(SubmitButton).text()).toBe(
                t('store.trips.edit.submit'),
            );
        },
    );
    it('submits PUT to the bound trip and cancels to the trip list', () => {
        const wrapper = mountPage();
        expect(wrapper.getComponent(Form).props('action')).toEqual(
            update(trip),
        );
        expect(
            wrapper
                .findAllComponents(Link)
                .find((link) => link.text() === t('common.cancel'))
                ?.props('href'),
        ).toEqual(tripsIndex());
    });
    it('passes the trip, its choices and errors to the fields, which load its vehicles', async () => {
        formSlotPropsMock.mockReturnValue({
            errors: { due_date: 'Required' },
            processing: false,
        });
        const wrapper = mountPage();
        await flushPromises();
        expect(wrapper.getComponent(StoreTripFormFields).props()).toEqual({
            trip,
            orders,
            customers,
            errors: { due_date: 'Required' },
        });
        expect(getMock).toHaveBeenCalledTimes(1);
        expect(wrapper.findAll('[data-testid="trip-checkpoint"]')).toHaveLength(
            2,
        );
    });
});

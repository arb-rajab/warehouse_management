import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import {
    index as trucksIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/TruckController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreTruckFormFields from '@/components/StoreTruckFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeSharedProps, storeTruck, truckPhoto } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Edit from './Edit.vue';

vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(() => ({ errors: {}, processing: true })),
        Link: createLinkStub(),
        usePage: () => ({
            url: '',
            props: defaultAuthProps({ store: storeSharedProps() }),
        }),
        useHttp: () => ({ processing: false, get: vi.fn(), cancel: vi.fn() }),
        router: { post: vi.fn() },
    };
});

describe('edit truck', () => {
    it('passes the bound truck, images and update action and reflects processing state', () => {
        const truck = storeTruck();
        const photos = [truckPhoto()];
        const wrapper = mount(Edit, { props: { truck, photos } });
        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            title: t('store.trucks.edit.title', { name: truck.name }),
            action: update(truck),
            layout: StoreAdminLayout,
            cancelHref: trucksIndex(),
        });
        expect(wrapper.getComponent(Form).props('action')).toEqual(
            update(truck),
        );
        expect(
            wrapper.getComponent(StoreTruckFormFields).props(),
        ).toMatchObject({ truck, photos, errors: {} });
        expect(wrapper.get('input[name="photos"]').attributes('value')).toBe(
            '7',
        );
        expect(wrapper.get('button[type="submit"]').text()).toContain(
            t('store.trucks.submitting'),
        );
        expect(
            wrapper.get('button[type="submit"]').attributes('disabled'),
        ).toBeDefined();
    });
});

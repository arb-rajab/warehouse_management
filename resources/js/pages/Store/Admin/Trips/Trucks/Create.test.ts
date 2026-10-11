import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import {
    index as trucksIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/TruckController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreTruckFormFields from '@/components/StoreTruckFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeSharedProps, truckPhoto } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Create from './Create.vue';

vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(() => ({
            errors: { name: 'Required' },
            processing: false,
        })),
        Link: createLinkStub(),
        usePage: () => ({
            url: '',
            props: defaultAuthProps({ store: storeSharedProps() }),
        }),
        useHttp: () => ({ processing: false, get: vi.fn() }),
        router: { post: vi.fn() },
    };
});

describe('create truck', () => {
    it('mounts the real form and passes route, labels, photos and errors to shared components', () => {
        const photos = [truckPhoto()];
        const wrapper = mount(Create, { props: { photos } });
        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            title: t('store.trucks.create.title'),
            action: store(),
            layout: StoreAdminLayout,
            cancelHref: trucksIndex(),
            submitLabel: t('store.trucks.submit'),
            submittingLabel: t('store.trucks.submitting'),
        });
        expect(wrapper.getComponent(Form).props('action')).toEqual(store());
        expect(
            wrapper.getComponent(StoreTruckFormFields).props(),
        ).toMatchObject({ photos, errors: { name: 'Required' } });
        expect(wrapper.get('h1').text()).toBe(t('store.trucks.create.title'));
        expect(wrapper.get('button[type="submit"]').text()).toBe(
            t('store.trucks.submit'),
        );
    });
});

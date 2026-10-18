import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, expect, it, vi } from 'vitest';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/GoogleMapController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreGoogleMapFormFields from '@/components/StoreGoogleMapFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Index from './Index.vue';

vi.mock(
    'leaflet',
    async () => (await import('@/testing/leafletMock')).leafletMock,
);
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(() => ({ errors: {}, processing: false })),
        Link: createLinkStub(),
        usePage: () => ({
            url: '/temp/admin/google-map',
            props: defaultAuthProps({
                store: storeSharedProps({
                    permissions: ['google_map_setting'],
                }),
            }),
        }),
        router: { get: vi.fn(), post: vi.fn() },
    };
});
afterEach(() => {
    i18n.global.locale.value = 'en';
});
it.each(['en', 'ar'] as const)(
    'full mounts the page shell and form in %s',
    (locale) => {
        i18n.global.locale.value = locale;
        const settings = {
            google_map: false,
            google_map_latitude: '33.513',
            google_map_longtitude: '36.291',
        };
        const wrapper = mount(Index, { props: { settings } });
        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            title: t('store.googleMap.title'),
            layout: StoreAdminLayout,
            action: update(),
            wide: true,
        });
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(Form).props('action')).toEqual(update());
        expect(wrapper.getComponent(StoreGoogleMapFormFields).props()).toEqual({
            settings,
            errors: {},
        });
        expect(wrapper.getComponent(SubmitButton).props()).toMatchObject({
            label: t('store.settings.save'),
            processingLabel: t('store.settings.saving'),
            processing: false,
        });
        expect(wrapper.text()).toContain(t('store.googleMap.title'));
        wrapper.unmount();
    },
);

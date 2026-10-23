import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteHomeController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreWebsiteHomeFormFields from '@/components/StoreWebsiteHomeFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import {
    storeSharedProps,
    storeWebsiteHomeSettings,
} from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import Home from './Home.vue';
const { formSlotPropsMock, usePageMock, routerPostMock } = vi.hoisted(() => ({
    formSlotPropsMock: vi.fn(() => ({
        errors: {} as Record<string, string>,
        processing: false,
    })),
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
        useHttp: () =>
            reactive({
                processing: false,
                cancel: () => {},
                get: () => Promise.resolve(),
                post: () => Promise.resolve(),
            }),
    };
});
beforeEach(() => {
    resetMocks({ formSlotPropsMock, usePageMock, routerPostMock });
    formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    usePageMock.mockReturnValue({
        url: '/temp/admin/website/custom-pages/edit/home',
        props: defaultAuthProps({
            store: storeSharedProps({ permissions: ['edit_website_page'] }),
        }),
    });
});
describe('Website home', () => {
    function mountPage() {
        return mount(Home, {
            props: {
                settings: storeWebsiteHomeSettings(),
                categories: [{ id: 17, name: 'Food' }],
                couponEnabled: true,
            },
        });
    }
    it('uses the store layout, live field group and page-specific endpoint', () => {
        const wrapper = mountPage();
        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            title: t('store.website.home.title'),
            submitLabel: t('store.website.home.save'),
            submittingLabel: t('store.website.header.saving'),
            wide: true,
            layout: StoreAdminLayout,
        });
        expect(wrapper.getComponent(Form).props('action')).toEqual(update());
        expect(
            wrapper.getComponent(StoreWebsiteHomeFormFields).props(),
        ).toMatchObject({
            categories: [{ id: 17, name: 'Food' }],
            couponEnabled: true,
        });
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
    });
    it('shows exactly one legend while category rows exist and none after the last is removed', async () => {
        const wrapper = mountPage();
        await wrapper.vm.$nextTick();
        expect(wrapper.findAll('[data-required-legend]')).toHaveLength(1);
        await wrapper
            .get('button[aria-label="Remove category row 1"]')
            .trigger('click');
        expect(wrapper.find('[data-required-legend]').exists()).toBe(false);
        wrapper.unmount();
    });
    it('submits edited ordered arrays and forwards errors without losing entered text', async () => {
        const wrapper = mountPage();
        await wrapper.get('#cupon_title').setValue('Edited coupon');
        const transform = wrapper.getComponent(Form).props('transform') as (
            data: unknown,
        ) => unknown;
        expect(transform({ header_script: 'forged' })).toEqual({
            settings: storeWebsiteHomeSettings({
                cupon_title: 'Edited coupon',
            }),
        });
        formSlotPropsMock.mockReturnValue({
            errors: { 'settings.cupon_title': 'Invalid title' },
            processing: true,
        });
        await wrapper.setProps({ settings: storeWebsiteHomeSettings() });
        expect(
            wrapper.getComponent(StoreWebsiteHomeFormFields).props('errors'),
        ).toEqual({ 'settings.cupon_title': 'Invalid title' });
        expect(wrapper.get('#cupon_title').element).toHaveProperty(
            'value',
            'Edited coupon',
        );
    });
});

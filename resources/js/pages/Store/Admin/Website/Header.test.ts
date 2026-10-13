import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteHeaderController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreWebsiteHeaderFormFields from '@/components/StoreWebsiteHeaderFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import {
    storeSharedProps,
    storeWebsiteHeaderSettings,
} from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import Header from './Header.vue';

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
        url: '/temp/admin/website/header',
        props: defaultAuthProps({
            store: storeSharedProps({ permissions: ['header_setup'] }),
        }),
    });
});

describe('Website header', () => {
    it('uses the store form layout, translated labels, and its page-specific action', () => {
        const wrapper = mount(Header, {
            props: { settings: storeWebsiteHeaderSettings() },
        });
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            title: t('store.website.header.title'),
            submitLabel: t('store.website.header.save'),
            submittingLabel: t('store.website.header.saving'),
            layout: StoreAdminLayout,
            wide: true,
        });
        expect(wrapper.getComponent(Form).props('action')).toEqual(update());
        expect(wrapper.getComponent(SubmitButton).text()).toBe(
            t('store.website.header.save'),
        );
    });

    it('submits the complete edited settings, including unchecked switches and empty menus', async () => {
        const initial = storeWebsiteHeaderSettings();
        const wrapper = mount(Header, { props: { settings: initial } });
        await wrapper.get('#header_stikcy').setValue(false);
        await wrapper.get('#helpline_number').setValue('555');
        await wrapper
            .get('button[aria-label="Remove menu row 1"]')
            .trigger('click');
        const transform = wrapper.getComponent(Form).props('transform') as (
            data: unknown,
        ) => unknown;
        expect(transform({ types: ['forged'] })).toEqual({
            settings: {
                ...initial,
                header_stikcy: false,
                helpline_number: '555',
                header_menu_labels: [],
                header_menu_links: [],
            },
        });
        expect(initial.header_menu_labels).toEqual(['Offers']);
    });

    it('forwards validation errors, disables processing and retains user input', async () => {
        const wrapper = mount(Header, {
            props: { settings: storeWebsiteHeaderSettings() },
        });
        await wrapper.get('#helpline_number').setValue('555');
        formSlotPropsMock.mockReturnValue({
            errors: { 'settings.helpline_number': 'Invalid phone' },
            processing: true,
        });
        await wrapper.setProps({ settings: storeWebsiteHeaderSettings() });
        expect(
            wrapper.getComponent(StoreWebsiteHeaderFormFields).props('errors'),
        ).toEqual({ 'settings.helpline_number': 'Invalid phone' });
        expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(
            true,
        );
        expect(wrapper.get('#helpline_number').element).toHaveProperty(
            'value',
            '555',
        );
    });
});

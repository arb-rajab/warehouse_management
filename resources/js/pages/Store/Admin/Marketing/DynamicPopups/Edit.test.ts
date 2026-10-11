import { mount } from '@vue/test-utils';
import { expect, it, vi } from 'vitest';
import {
    index as popupsIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/DynamicPopupController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreDynamicPopupFormFields from '@/components/StoreDynamicPopupFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Page from './Edit.vue';
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(() => ({
            errors: { title: 'Invalid title' },
            processing: false,
        })),
        Link: createLinkStub(),
        usePage: () => ({
            url: '/temp/admin/dynamic-popups',
            props: defaultAuthProps({ store: storeSharedProps() }),
        }),
        router: { post: vi.fn() },
    };
});
vi.mock('@/components/StoreUploadField.vue', () => ({
    default: {
        props: ['id', 'label', 'name', 'modelValue', 'error'],
        template: '<div>{{label}} {{error}}</div>',
    },
}));
it('renders the store form shell and passes server data and errors into the popup fields', () => {
    const popup = {
        id: 1,
        title: 'Title',
        summary: 'Summary',
        banner: '12',
        btn_text: 'Open',
        delay_seconds: 5,
        offer_id: 2,
        show_page: 0,
    };
    const options = { offers: [{ id: 2, name: 'Offer' }], categories: [] };
    const wrapper = mount(Page, { props: { ...options, popup } });
    expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
        title: t('store.dynamicPopups.edit.title'),
        action: update(popup),
        submitLabel: t('store.dynamicPopups.edit.submit'),
        submittingLabel: t('store.dynamicPopups.edit.submitting'),
        cancelHref: popupsIndex(),
        layout: StoreAdminLayout,
        wide: true,
    });
    expect(
        wrapper.getComponent(StoreDynamicPopupFormFields).props(),
    ).toMatchObject({ ...options, popup, errors: { title: 'Invalid title' } });
    expect(wrapper.text()).toContain('Invalid title');
    expect(wrapper.text()).toContain(t('store.dynamicPopups.edit.submit'));
});

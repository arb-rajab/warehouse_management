import { mount } from '@vue/test-utils';
import { expect, it, vi } from 'vitest';
import {
    index as popupsIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/DynamicPopupController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreDynamicPopupFormFields from '@/components/StoreDynamicPopupFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Page from './Create.vue';
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
    const options = { offers: [{ id: 2, name: 'Offer' }], categories: [] };
    const wrapper = mount(Page, { props: { ...options } });
    expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
        title: t('store.dynamicPopups.create.title'),
        action: store(),
        submitLabel: t('store.dynamicPopups.create.submit'),
        submittingLabel: t('store.dynamicPopups.create.submitting'),
        cancelHref: popupsIndex(),
        layout: StoreAdminLayout,
        wide: true,
    });
    expect(
        wrapper.getComponent(StoreDynamicPopupFormFields).props(),
    ).toMatchObject({ ...options, errors: { title: 'Invalid title' } });
    expect(wrapper.text()).toContain('Invalid title');
    expect(wrapper.text()).toContain(t('store.dynamicPopups.create.submit'));
});

import { Form, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    index as sellersIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/SellerController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreSellerFormFields from '@/components/StoreSellerFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Create from './Create.vue';

const { usePageMock, slotProps } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    slotProps: vi.fn(() => ({
        errors: {} as Record<string, string>,
        processing: false,
    })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Form: createFormStub(slotProps),
        Link: createLinkStub(),
        Head: headStub,
        usePage: usePageMock,
        router: { post: vi.fn() },
    };
});
function mountPage() {
    return mount(Create, { props: {} });
}
describe('seller Create', () => {
    beforeEach(() => {
        usePageMock.mockReturnValue({
            url: '/temp/admin/sellers',
            props: defaultAuthProps({ store: storeSharedProps() }),
        });
        slotProps.mockReturnValue({ errors: {}, processing: false });
    });
    it('fully mounts its store layout form fields route and labels', () => {
        const wrapper = mountPage();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            title: t('store.sellers.add'),
            action: store(),
            cancelHref: sellersIndex(),
            submitLabel: t('store.sellers.save'),
            submittingLabel: t('store.sellers.saving'),
            layout: StoreAdminLayout,
        });
        expect(wrapper.getComponent(Form).props('action')).toEqual(store());
        expect(
            wrapper.getComponent(StoreSellerFormFields).props(),
        ).toMatchObject({ passwordRequired: true });
        expect(wrapper.getComponent(SubmitButton).text()).toBe(
            t('store.sellers.save'),
        );
        expect(
            wrapper
                .findAllComponents(Link)
                .some(
                    (link) =>
                        JSON.stringify(link.props('href')) ===
                        JSON.stringify(sellersIndex()),
                ),
        ).toBe(true);
    });
    it('forwards validation errors to the real account fields', () => {
        slotProps.mockReturnValue({
            errors: { email: 'Already used' },
            processing: false,
        });
        expect(
            mountPage().getComponent(StoreSellerFormFields).props('errors'),
        ).toEqual({ email: 'Already used' });
    });
});

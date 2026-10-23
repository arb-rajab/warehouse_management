import { Form, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    index as sellersIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/SellerController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreSellerFormFields from '@/components/StoreSellerFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Edit from './Edit.vue';

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
    return mount(Edit, {
        props: { seller: { id: 17, name: 'Sam', email: 'sam@example.com' } },
    });
}
describe('seller Edit', () => {
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
            title: t('store.sellers.edit'),
            action: update({ id: 17 }),
            cancelHref: sellersIndex(),
            submitLabel: t('store.sellers.save'),
            submittingLabel: t('store.sellers.saving'),
            layout: StoreAdminLayout,
        });
        expect(wrapper.getComponent(Form).props('action')).toEqual(
            update({ id: 17 }),
        );
        expect(
            wrapper.getComponent(StoreSellerFormFields).props(),
        ).toMatchObject({
            name: 'Sam',
            email: 'sam@example.com',
            passwordRequired: false,
        });
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

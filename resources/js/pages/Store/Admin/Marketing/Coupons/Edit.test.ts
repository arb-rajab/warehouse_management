import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    index as couponIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/CouponController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreCouponFormFields from '@/components/StoreCouponFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeCoupon, storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Edit from './Edit.vue';

const { usePageMock, formSlotPropsMock, httpGetMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    formSlotPropsMock: vi.fn(),
    httpGetMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { post: vi.fn() },
        useHttp: () => ({
            get: httpGetMock,
            processing: false,
            errors: {},
            q: '',
        }),
    };
});
const coupon = storeCoupon();
const selectedProducts = [{ id: 12, name: 'Translated product' }];
function mountPage() {
    return mount(Edit, { props: { coupon, selectedProducts } });
}

describe('Store coupon Edit', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        usePageMock.mockReturnValue({
            url: couponIndex.url(),
            props: defaultAuthProps({ store: storeSharedProps() }),
        });
        formSlotPropsMock.mockReturnValue({
            errors: { code: 'Already taken' },
            processing: false,
        });
    });
    it('renders in the store layout and supplies the form action labels and cancel route', () => {
        const wrapper = mountPage();
        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            title: t('store.coupons.edit.title'),
            submitLabel: t('store.coupons.edit.submit'),
            submittingLabel: t('store.coupons.edit.submitting'),
            layout: StoreAdminLayout,
            cancelHref: couponIndex(),
        });
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(Form).props('action')).toEqual(
            update(coupon),
        );
        expect(wrapper.get('h1').text()).toBe(t('store.coupons.edit.title'));
        expect(
            wrapper.getComponent(StoreCouponFormFields).props(),
        ).toMatchObject({
            selectedProducts,
            errors: { code: 'Already taken' },
            coupon,
        });
    });
});

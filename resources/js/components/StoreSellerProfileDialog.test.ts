import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { profile } from '@/actions/App/Http/Controllers/Store/Admin/SellerController';
import FilterDialog from '@/components/FilterDialog.vue';
import { t } from '@/lib/i18n';
import { storeSellerProfile } from '@/testing/factories';
import StoreSellerProfileDialog from './StoreSellerProfileDialog.vue';

const { postMock, cancelMock, processingMock } = vi.hoisted(() => ({
    postMock: vi.fn(),
    cancelMock: vi.fn(),
    processingMock: vi.fn(() => false),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createUseHttpStub } = await import('@/testing/inertiaStubs');

    return {
        useHttp: (data: object) =>
            Object.assign(createUseHttpStub(data), {
                post: postMock,
                processing: processingMock(),
                cancel: cancelMock,
            }),
    };
});
describe('seller profile dialog', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        postMock.mockResolvedValue(storeSellerProfile());
    });
    it('loads only when open and renders all escaped profile and payout fields', async () => {
        const wrapper = mount(StoreSellerProfileDialog, {
            props: { open: false, sellerId: 17 },
        });
        expect(postMock).not.toHaveBeenCalled();
        await wrapper.setProps({ open: true });
        await flushPromises();
        expect(postMock).toHaveBeenCalledWith(profile.url());
        expect(wrapper.getComponent(FilterDialog).props()).toMatchObject({
            title: t('store.sellers.profile'),
            closeLabel: t('common.close'),
        });

        for (const field of [
            'payoutInfo',
            'bank_name',
            'bank_acc_name',
            'bank_acc_no',
            'bank_routing_no',
            'products_count',
            'orders_count',
            'sold_amount',
            'wallet_balance',
            'facebook',
            'twitter',
            'google',
        ]) {
            expect(wrapper.text()).toContain(t(`store.sellers.${field}`));
        }

        expect(wrapper.text()).toContain('Sam Seller');
        expect(wrapper.get('img').attributes()).toMatchObject({
            src: '/avatar.png',
            alt: 'Sam Seller',
        });
        expect(wrapper.get('a[href="/temp/shop/acme"]').text()).toBe(
            'Acme shop',
        );
        await wrapper.get('button[aria-label="Close"]').trigger('click');
        expect(wrapper.emitted('update:open')).toEqual([[false]]);
        wrapper.unmount();
        expect(cancelMock).toHaveBeenCalled();
    });
    it('renders seller-supplied text as text and tolerates absent URLs', async () => {
        postMock.mockResolvedValue(
            storeSellerProfile({
                name: '<img onerror=alert(1)>',
                bank_name: '<script>bad</script>',
                avatar_url: null,
                shop_url: null,
                social: { facebook: null, twitter: null, google: null },
            }),
        );
        const wrapper = mount(StoreSellerProfileDialog, {
            props: { open: true, sellerId: 17 },
        });
        await flushPromises();
        expect(wrapper.text()).toContain('<script>bad</script>');
        expect(wrapper.find('script').exists()).toBe(false);
        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.find('a').exists()).toBe(false);
    });
    it('announces loading while the request is in flight', () => {
        processingMock.mockReturnValueOnce(true);
        postMock.mockReturnValue(new Promise(() => {}));
        const wrapper = mount(StoreSellerProfileDialog, {
            props: { open: true, sellerId: 17 },
        });
        expect(wrapper.get('[role="status"]').text()).toBe(
            t('store.sellers.loading'),
        );
        wrapper.unmount();
    });
    it('reports both HTTP errors and validation responses without stale details', async () => {
        postMock.mockRejectedValue(new Error('refused'));
        const wrapper = mount(StoreSellerProfileDialog, {
            props: { open: true, sellerId: 17 },
        });
        await flushPromises();
        expect(wrapper.text()).toContain(t('store.sellers.profileError'));
        postMock.mockResolvedValue(undefined);
        await wrapper.setProps({ sellerId: 18 });
        await flushPromises();
        expect(wrapper.text()).toContain(t('store.sellers.profileError'));
        expect(wrapper.text()).not.toContain('Sam Seller');
    });
    it('ignores responses for a prior seller after the dialog changes', async () => {
        let resolveOld!: (value: ReturnType<typeof storeSellerProfile>) => void;
        postMock.mockImplementationOnce(
            () =>
                new Promise((resolve) => {
                    resolveOld = resolve;
                }),
        );
        const wrapper = mount(StoreSellerProfileDialog, {
            props: { open: true, sellerId: 17 },
        });
        await wrapper.setProps({ sellerId: 18 });
        await flushPromises();
        resolveOld(storeSellerProfile({ name: 'Stale person' }));
        await flushPromises();
        expect(wrapper.text()).not.toContain('Stale person');
    });
});

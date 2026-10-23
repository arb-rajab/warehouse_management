import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    approve,
    reject,
    index as sellersIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/SellerController';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeSellerVerification, storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Verification from './Verification.vue';

const { usePageMock } = vi.hoisted(() => ({ usePageMock: vi.fn() }));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');

    return {
        Link: createLinkStub(),
        Head: headStub,
        usePage: usePageMock,
        router: { post: vi.fn() },
    };
});
function mountPage(allowed = true, seller = storeSellerVerification()) {
    usePageMock.mockReturnValue({
        url: '/temp/admin/sellers/view/17/verification',
        props: defaultAuthProps({
            store: storeSharedProps({
                permissions: allowed ? ['edit_seller'] : [],
            }),
        }),
    });

    return mount(Verification, { props: { seller } });
}
describe('seller verification', () => {
    beforeEach(() => vi.clearAllMocks());
    it('fully mounts the layout and renders labels user shop and document details', () => {
        const wrapper = mountPage();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);

        for (const field of [
            'verification',
            'back',
            'userInfo',
            'shopInfo',
            'verificationInfo',
            'name',
            'email',
            'address',
            'phone',
            'download',
            'accept',
            'reject',
        ]) {
            expect(wrapper.text()).toContain(t(`store.sellers.${field}`));
        }

        for (const value of [
            'Sam Seller',
            'sam@example.com',
            'Shop street',
            'User street',
            'Acme shop',
            'Company',
            'Acme',
        ]) {
            expect(wrapper.text()).toContain(value);
        }

        expect(
            wrapper.get('main a[target="_blank"]').attributes(),
        ).toMatchObject({
            href: '/temp/admin/sellers/verification-file/certificate.pdf',
            rel: 'noopener',
        });
        const links = wrapper
            .findAllComponents(TableActionLink)
            .map((link) => link.props('href'));
        expect(links).toEqual([
            sellersIndex(),
            approve({ id: 17 }),
            reject({ id: 17 }),
        ]);
    });
    it('gates both transitions by the server permission and pending verification state', () => {
        for (const [allowed, seller] of [
            [false, storeSellerVerification()],
            [true, storeSellerVerification({ approved: true })],
            [true, storeSellerVerification({ has_verification: false })],
        ] as const) {
            const wrapper = mountPage(allowed, seller);
            expect(wrapper.findAllComponents(TableActionLink)).toHaveLength(1);
        }
    });
    it('escapes all seller-supplied text and labels', () => {
        const wrapper = mountPage(
            true,
            storeSellerVerification({
                name: '<script>shop</script>',
                verification: [
                    {
                        label: '<img onerror=bad>',
                        value: '<script>value</script>',
                        file_url: null,
                    },
                ],
            }),
        );
        expect(wrapper.find('script').exists()).toBe(false);
        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).toContain('<script>value</script>');
        expect(wrapper.text()).toContain('<img onerror=bad>');
    });
});

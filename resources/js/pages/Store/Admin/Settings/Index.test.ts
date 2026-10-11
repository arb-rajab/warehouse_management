import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    updateActivation,
    updateFacebookChat,
    updateFacebookComments,
    updateAnalytics,
    updateRecaptcha,
    updateFirebase,
    updatePayments,
    testSmtp,
} from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreBooleanField from '@/components/StoreBooleanField.vue';
import { i18n, t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Index from './Index.vue';
const { usePageMock, formSlotPropsMock, routerGetMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
    routerGetMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { get: routerGetMock, post: vi.fn() },
    };
});
beforeEach(() => {
    vi.clearAllMocks();
    formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    usePageMock.mockReturnValue({
        url: '/temp/admin/languages',
        props: defaultAuthProps({
            store: storeSharedProps({ permissions: ['language_setup'] }),
        }),
    });
});

const pages = {
    activation: {
        permission: 'features_activation',
        keys: [
            'disable_image_optimization',
            'vendor_system_activation',
            'classified_product',
            'wallet_system',
            'coupon_system',
            'pickup_point',
            'conversation_system',
            'product_manage_by_admin',
            'product_approve_by_admin',
            'email_verification',
            'product_query_activation',
            'In_House_Products_Section',
            'Digital_Products_Section',
            'Product_Reviews_Section',
            'In_House_Orders_Section',
            'Pickup_Orders_Section',
            'Payouts_Section',
            'Payout_request_Section',
            'Seller_Commission_Section',
            'Seller_Verify_Form_Section',
            'Shop_Ratings',
            'Reports_Section',
            'In_House_Sales_Report',
            'Products_Stock_Report',
            'Products_Wishlist_Report',
            'User_Searches_Report',
            'Commission_History_Report',
            'Wallet_Recharge_History',
            'Blog_Section',
            'Marketing_Section',
            'Flash_deals',
            'Newsletters',
            'Support_Section',
            'website_setup',
            'website_setup_header',
            'website_setup_footer',
            'website_setup_pages',
            'website_setup_appearance',
            'Setup_configuration_section',
            'Languages_section',
            'currency_section',
            'VAT_TAX_section',
            'Setup_pickup_point_section',
            'SMTP_Section',
            'Order_configuration_section',
            'Socail_Media_logins_section',
            'Facebook_section',
            'Setup_Shipping_section',
            'qr_connection_system',
            'Staffs_section',
            'sslcommerz_payment',
            'voguepay',
            'payhere',
            'ngenius',
            'iyzico',
            'bkash',
            'nagad',
            'aamarpay',
            'cash_payment',
            'facebook_login',
            'google_login',
            'twitter_login',
            'apple_login',
        ],
    },
    smtp: { permission: 'smtp_settings', keys: [] },
    socialLogin: { permission: 'social_media_logins', keys: [] },
    facebookChat: { permission: 'facebook_chat', keys: ['facebook_chat'] },
    facebookComments: {
        permission: 'facebook_comment',
        keys: ['facebook_comment'],
    },
    analytics: {
        permission: 'analytics_tools_configuration',
        keys: ['google_analytics', 'facebook_pixel'],
    },
    recaptcha: {
        permission: 'google_recaptcha_configuration',
        keys: ['google_recaptcha'],
    },
    firebase: {
        permission: 'google_firebase_setting',
        keys: ['google_firebase'],
    },
    payments: {
        permission: 'payment_methods_configurations',
        keys: [
            'bkash_sandbox',
            'sslcommerz_sandbox',
            'aamarpay_sandbox',
            'iyzico_sandbox',
            'payhere_sandbox',
            'voguepay_sandbox',
        ],
    },
    filesystem: { permission: 'file_system_&_cache_configuration', keys: [] },
};
type Section = keyof typeof pages;
const actions = {
    activation: updateActivation,
    facebookChat: updateFacebookChat,
    facebookComments: updateFacebookComments,
    analytics: updateAnalytics['/temp/admin/google-analytics'],
    recaptcha: updateRecaptcha,
    firebase: updateFirebase,
    payments: updatePayments,
};
function mountPage(
    section: Section,
    permissions = [pages[section].permission],
    isRaizerAdmin = true,
) {
    usePageMock.mockReturnValue({
        url: '/temp/admin/settings',
        props: defaultAuthProps({
            store: storeSharedProps({ permissions, isRaizerAdmin }),
        }),
    });

    return mount(Index, {
        props: {
            section,
            settings: Object.fromEntries(
                pages[section].keys.map((key) => [key, true]),
            ),
        },
    });
}
describe('Store settings', () => {
    it.each(Object.keys(pages) as Section[])(
        'renders %s with all English and Arabic labels and the environment notice',
        (section) => {
            const wrapper = mountPage(section);
            expect(wrapper.get('h1').text()).toBe(
                t(`store.settings.titles.${section}`),
            );
            expect(wrapper.text()).toContain(
                t('store.settings.environmentReadOnly'),
            );
            expect(wrapper.find('input[type="password"]').exists()).toBe(false);

            for (const locale of ['en', 'ar'] as const) {
                expect(
                    i18n.global.te(`store.settings.titles.${section}`, locale),
                ).toBe(true);

                for (const key of pages[section].keys) {
                    expect(
                        i18n.global.te(`store.settings.keys.${key}`, locale),
                    ).toBe(true);
                }
            }

            for (const key of pages[section].keys) {
                expect(wrapper.text()).toContain(
                    t(`store.settings.keys.${key}`),
                );
            }

            expect(
                wrapper
                    .findAllComponents(StoreBooleanField)
                    .map((field) => field.props('name')),
            ).toEqual(pages[section].keys.map((key) => `settings[${key}]`));

            if (section in actions) {
                expect(wrapper.getComponent(Form).props('action')).toEqual(
                    actions[section as keyof typeof actions](),
                );
                expect(
                    wrapper.getComponent(ResourceFormPage).props('wide'),
                ).toBe(true);
            } else {
                expect(wrapper.findComponent(ResourceFormPage).exists()).toBe(
                    false,
                );
                expect(wrapper.findAllComponents(Form)).toHaveLength(0);
            }
        },
    );
    it.each(Object.keys(actions) as (keyof typeof actions)[])(
        'hides %s writes when the page permission is absent',
        (section) => {
            expect(mountPage(section, []).findComponent(Form).exists()).toBe(
                false,
            );
        },
    );
    it('keeps activation vendor-only', () => {
        expect(
            mountPage('activation', ['features_activation'], false)
                .findComponent(Form)
                .exists(),
        ).toBe(false);
    });
    it('renders SMTP testing only with both permissions and mirrors recipient validation', () => {
        const wrapper = mountPage('smtp', ['smtp_settings', 'send_newsletter']);
        expect(wrapper.getComponent(Form).props('action')).toEqual(testSmtp());
        expect(wrapper.get('#email').attributes()).toMatchObject({
            type: 'email',
            maxlength: '255',
            required: '',
        });
        expect(wrapper.text()).toContain(t('store.settings.testEmail'));
        expect(wrapper.text()).toContain(t('store.settings.sendTest'));
        expect(
            mountPage('smtp', ['send_newsletter']).findComponent(Form).exists(),
        ).toBe(false);
    });
    it('displays server validation errors for settings and SMTP', () => {
        formSlotPropsMock.mockReturnValue({
            errors: {
                settings: 'Unknown keys',
                'settings.google_recaptcha': 'Bad boolean',
                email: 'Invalid email',
            },
            processing: false,
        });
        expect(mountPage('recaptcha').text()).toContain('Unknown keys');
        expect(mountPage('recaptcha').text()).toContain('Bad boolean');
        expect(
            mountPage('smtp', ['smtp_settings', 'send_newsletter']).text(),
        ).toContain('Invalid email');
    });
});

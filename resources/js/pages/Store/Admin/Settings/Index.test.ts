import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { clearCache } from '@/actions/App/Http/Controllers/Store/Admin/DashboardController';
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
) {
    usePageMock.mockReturnValue({
        url: '/temp/admin/settings',
        props: defaultAuthProps({
            store: storeSharedProps({ permissions }),
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
// Keep full layout mounting while distinguishing its cache action from page writes.
function pageForms(wrapper: ReturnType<typeof mountPage>) {
    return wrapper.findAllComponents(Form).filter((form) => {
        const action = form.props('action');

        return (
            (typeof action === 'string' ? action : action?.url) !==
            clearCache.url()
        );
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
                expect(pageForms(wrapper)[0].props('action')).toEqual(
                    actions[section as keyof typeof actions](),
                );
                expect(
                    wrapper.getComponent(ResourceFormPage).props('wide'),
                ).toBe(true);
            } else {
                expect(wrapper.findComponent(ResourceFormPage).exists()).toBe(
                    false,
                );
                expect(pageForms(wrapper)).toHaveLength(0);
            }
        },
    );
    it.each(Object.keys(actions) as (keyof typeof actions)[])(
        'hides %s writes when the page permission is absent',
        (section) => {
            expect(pageForms(mountPage(section, []))).toHaveLength(0);
        },
    );
    it('highlights the option the admin was sent to change, and no other', () => {
        const wrapper = mountPage('activation', ['features_activation']);
        expect(wrapper.findAll('[data-highlighted]')).toHaveLength(0);
        wrapper.unmount();
        usePageMock.mockReturnValue({
            url: '/temp/admin/activation?highlight=cash_payment',
            props: defaultAuthProps({
                store: storeSharedProps({
                    permissions: ['features_activation'],
                }),
            }),
        });
        const highlighted = mount(Index, {
            props: {
                section: 'activation',
                highlight: 'cash_payment',
                settings: Object.fromEntries(
                    pages.activation.keys.map((key) => [key, false]),
                ),
            },
        });
        const marked = highlighted.findAll('[data-highlighted]');
        expect(marked).toHaveLength(1);
        expect(marked[0].find('select').attributes('name')).toBe(
            'settings[cash_payment]',
        );
        highlighted.unmount();
    });
    it.each([
        [
            'smtp',
            {
                mailer: 'smtp',
                host: 'mail.example.com',
                username_set: false,
                password_set: true,
            },
            'mail_credentials',
            [
                ['mailer', 'smtp'],
                ['host', 'mail.example.com'],
                ['username', 'store.settings.server.notSet'],
                ['password', 'store.settings.server.set'],
            ],
        ],
        [
            'firebase',
            { project_set: true, credentials_found: false },
            'firebase_server',
            [
                ['project', 'store.settings.server.set'],
                ['credentials', 'store.settings.server.notFound'],
            ],
        ],
    ] as const)(
        'shows whether each %s server value is set, never the value, and highlights it for a setup checklist link',
        async (section, server, anchor, rows) => {
            Element.prototype.scrollIntoView = vi.fn();
            usePageMock.mockReturnValue({
                url: `/temp/admin/settings?highlight=${anchor}`,
                props: defaultAuthProps({
                    store: storeSharedProps({
                        permissions: [pages[section].permission],
                    }),
                }),
            });
            const wrapper = mount(Index, {
                props: {
                    section,
                    server,
                    highlight: anchor,
                    settings: Object.fromEntries(
                        pages[section].keys.map((key) => [key, true]),
                    ),
                },
                attachTo: document.body,
            });
            await wrapper.vm.$nextTick();
            await wrapper.vm.$nextTick();
            const block = wrapper.get(`[data-section="${anchor}"]`);
            expect(block.attributes('data-highlighted')).toBeDefined();
            expect(wrapper.findAll('[data-highlighted]')).toHaveLength(1);
            expect(block.text()).toContain(
                t(`store.settings.server.${section}.title`),
            );
            for (const [label, value] of rows) {
                expect(block.text()).toContain(
                    t(`store.settings.server.${section}.${label}`),
                );
                expect(block.text()).toContain(
                    value.startsWith('store.') ? t(value) : value,
                );
            }
            expect(document.activeElement).toBe(block.element);
            wrapper.unmount();

            const plain = mountPage(section);
            expect(plain.find(`[data-section="${anchor}"]`).exists()).toBe(
                false,
            );
            plain.unmount();
        },
    );
    it('lets an admin holding the permission write activation', () => {
        expect(
            pageForms(mountPage('activation', ['features_activation'])),
        ).toHaveLength(1);
    });
    it('renders SMTP testing only with both permissions and mirrors recipient validation', () => {
        const wrapper = mountPage('smtp', ['smtp_settings', 'send_newsletter']);
        expect(pageForms(wrapper)[0].props('action')).toEqual(testSmtp());
        expect(wrapper.get('#email').attributes()).toMatchObject({
            type: 'email',
            maxlength: '255',
            required: '',
        });
        expect(wrapper.text()).toContain(t('store.settings.testEmail'));
        expect(wrapper.text()).toContain(t('store.settings.sendTest'));
        expect(pageForms(mountPage('smtp', ['send_newsletter']))).toHaveLength(
            0,
        );
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

import { Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { index as categories } from '@/actions/App/Http/Controllers/Store/Admin/CategoryController';
import { index as currencies } from '@/actions/App/Http/Controllers/Store/Admin/CurrencyController';
import { edit as generalSettings } from '@/actions/App/Http/Controllers/Store/Admin/GeneralSettingsController';
import { index as googleMap } from '@/actions/App/Http/Controllers/Store/Admin/GoogleMapController';
import { index as languages } from '@/actions/App/Http/Controllers/Store/Admin/LanguageController';
import { all as allProducts } from '@/actions/App/Http/Controllers/Store/Admin/ProductController';
import {
    activation,
    firebase,
    payments,
    smtp,
} from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import { index as shipping } from '@/actions/App/Http/Controllers/Store/Admin/ShippingConfigurationController';
import { index as taxes } from '@/actions/App/Http/Controllers/Store/Admin/TaxController';
import { edit as websiteFooter } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteFooterController';
import { edit as websiteHeader } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteHeaderController';
import { i18n, t } from '@/lib/i18n';
import type {
    StoreSetupChecklist,
    StoreSetupChecklistKey,
    StoreSetupChecklistReason,
} from '@/types/store';
import type { RouteDefinition } from '@/wayfinder';
import StoreSetupChecklistCard from './StoreSetupChecklist.vue';

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub } = await import('@/testing/inertiaStubs');

    return { Link: createLinkStub() };
});

const KEYS: StoreSetupChecklistKey[] = [
    'smtp',
    'paymentMethod',
    'paymentGateways',
    'cashOnDeliveryProducts',
    'shipping',
    'firebasePush',
    'googleMaps',
    'currency',
    'vatTax',
    'catalog',
    'siteBasics',
    'language',
];

/**
 * Every reason SetupChecklist::TARGETS can give, with the page and highlight
 * it links to and the href the card must build for it.
 */
const REASONS: [StoreSetupChecklistReason, RouteDefinition<'get'>][] = [
    [
        {
            key: 'smtp.mailer_log',
            route: 'smtp_settings.index',
            highlight: 'mail_mailer',
        },
        smtp({ query: { highlight: 'mail_mailer' } }),
    ],
    [
        {
            key: 'smtp.host',
            route: 'smtp_settings.index',
            highlight: 'mail_host',
        },
        smtp({ query: { highlight: 'mail_host' } }),
    ],
    [
        {
            key: 'smtp.username_and_password',
            route: 'smtp_settings.index',
            highlight: 'mail_credentials',
        },
        smtp({ query: { highlight: 'mail_credentials' } }),
    ],
    [
        {
            key: 'paymentMethod.none_enabled',
            route: 'activation.index',
            highlight: 'cash_payment',
        },
        activation({ query: { highlight: 'cash_payment' } }),
    ],
    [
        {
            key: 'cashOnDeliveryProducts.no_product_allows_cod',
            route: 'products.all',
            highlight: null,
        },
        allProducts(),
    ],
    [
        {
            key: 'shipping.type_unset',
            route: 'shipping_configuration.index',
            highlight: 'shipping_type',
        },
        shipping({ query: { highlight: 'shipping_type' } }),
    ],
    [
        {
            key: 'shipping.flat_rate_cost',
            route: 'shipping_configuration.index',
            highlight: 'flat_rate_shipping_cost',
        },
        shipping({ query: { highlight: 'flat_rate_shipping_cost' } }),
    ],
    [
        {
            key: 'shipping.seller_admin_cost',
            route: 'shipping_configuration.index',
            highlight: 'shipping_cost_admin',
        },
        shipping({ query: { highlight: 'shipping_cost_admin' } }),
    ],
    [
        {
            key: 'shipping.area_no_active_city_with_cost',
            route: 'shipping_configuration.index',
            highlight: 'cities',
        },
        shipping({ query: { highlight: 'cities' } }),
    ],
    [
        {
            key: 'shipping.carrier_none_usable',
            route: 'shipping_configuration.index',
            highlight: 'carriers',
        },
        shipping({ query: { highlight: 'carriers' } }),
    ],
    [
        {
            key: 'firebasePush.project_unset',
            route: 'google-firebase.index',
            highlight: 'firebase_server',
        },
        firebase({ query: { highlight: 'firebase_server' } }),
    ],
    [
        {
            key: 'firebasePush.credentials_missing',
            route: 'google-firebase.index',
            highlight: 'firebase_server',
        },
        firebase({ query: { highlight: 'firebase_server' } }),
    ],
    [
        {
            key: 'googleMaps.api_key_unset',
            route: 'google-map.index',
            highlight: 'google_map_api_key',
        },
        googleMap({ query: { highlight: 'google_map_api_key' } }),
    ],
    [
        {
            key: 'currency.default_unset',
            route: 'currency.index',
            highlight: 'system_default_currency',
        },
        currencies({ query: { highlight: 'system_default_currency' } }),
    ],
    [
        {
            key: 'currency.default_missing',
            route: 'currency.index',
            highlight: 'system_default_currency',
        },
        currencies({ query: { highlight: 'system_default_currency' } }),
    ],
    [
        { key: 'vatTax.no_active_tax', route: 'tax.index', highlight: null },
        taxes(),
    ],
    [
        {
            key: 'catalog.no_published_product',
            route: 'products.all',
            highlight: null,
        },
        allProducts(),
    ],
    [
        {
            key: 'catalog.no_category',
            route: 'categories.index',
            highlight: null,
        },
        categories(),
    ],
    [
        {
            key: 'siteBasics.site_name',
            route: 'general_setting.index',
            highlight: 'site_name',
        },
        generalSettings({ query: { highlight: 'site_name' } }),
    ],
    [
        {
            key: 'siteBasics.contact_email',
            route: 'website.footer',
            highlight: 'contact_email',
        },
        websiteFooter({ query: { highlight: 'contact_email' } }),
    ],
    [
        {
            key: 'siteBasics.contact_phone',
            route: 'website.footer',
            highlight: 'contact_phone',
        },
        websiteFooter({ query: { highlight: 'contact_phone' } }),
    ],
    [
        {
            key: 'siteBasics.header_logo',
            route: 'website.header',
            highlight: 'header_logo',
        },
        websiteHeader({ query: { highlight: 'header_logo' } }),
    ],
    [
        {
            key: 'language.default_missing',
            route: 'languages.index',
            highlight: 'default_language',
            params: { code: 'nl' },
        },
        languages({ query: { highlight: 'default_language' } }),
    ],
    [
        {
            key: 'language.default_inactive',
            route: 'languages.index',
            highlight: 'default_language',
            params: { code: 'nl' },
        },
        languages({ query: { highlight: 'default_language' } }),
    ],
];

/**
 * The Payment methods page's reasons: every required field of a gateway with
 * a payment flow (SetupChecklist::TARGETS), highlighted as `<gateway>.<field>`.
 */
const PAYMENT_GATEWAY_FIELDS: Record<string, string[]> = {
    bkash: ['app_key', 'app_secret', 'username', 'password', 'currency'],
    nagad: ['merchant_id', 'pg_public_key', 'merchant_private_key', 'mode'],
    sslcommerz: ['store_id', 'store_password', 'currency'],
    iyzico: ['api_key', 'secret_key', 'currency'],
    payhere: ['merchant_id', 'secret'],
    aamarpay: ['store_id', 'signature_key', 'currency'],
    ngenius: ['outlet_id', 'api_key', 'currency', 'mode'],
    voguepay: ['merchant_id'],
};

for (const [gateway, fields] of Object.entries(PAYMENT_GATEWAY_FIELDS)) {
    for (const field of fields) {
        REASONS.push([
            {
                key: `paymentGateways.${gateway}_${field}`,
                route: 'payment_method.index',
                highlight: `${gateway}.${field}`,
            },
            payments({ query: { highlight: `${gateway}.${field}` } }),
        ]);
    }
}

/** Each to-do item carries every reason of its check (two for site basics and firebase, say). */
function checklist(doneKeys: StoreSetupChecklistKey[]): StoreSetupChecklist {
    return {
        items: KEYS.map((key) => {
            const done = doneKeys.includes(key);

            return {
                key,
                done,
                reasons: done
                    ? []
                    : REASONS.map(([reason]) => reason).filter((reason) =>
                          reason.key.startsWith(`${key}.`),
                      ),
            };
        }),
        done: doneKeys.length,
        total: KEYS.length,
    };
}

function row(wrapper: ReturnType<typeof mount>, key: StoreSetupChecklistKey) {
    return wrapper.get(`[data-key="${key}"]`);
}

afterEach(() => {
    i18n.global.locale.value = 'en';
});

describe('StoreSetupChecklist', () => {
    it('renders nothing when there are no items or everything is done', () => {
        const empty = mount(StoreSetupChecklistCard, {
            props: { checklist: { items: [], done: 0, total: 0 } },
        });
        expect(empty.find('section').exists()).toBe(false);
        expect(empty.text()).toBe('');

        const complete = mount(StoreSetupChecklistCard, {
            props: { checklist: checklist(KEYS) },
        });
        expect(complete.find('section').exists()).toBe(false);
    });

    it.each(['en', 'ar'] as const)(
        'shows the title, the progress and a progress bar in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mount(StoreSetupChecklistCard, {
                props: { checklist: checklist(KEYS.slice(0, 8)) },
            });
            const progress = t('store.dashboard.checklist.progress', {
                done: 8,
                total: 12,
            });
            expect(wrapper.get('h2').text()).toBe(
                t('store.dashboard.checklist.title'),
            );
            expect(wrapper.text()).toContain(progress);
            const bar = wrapper.get('[role="progressbar"]');
            expect(bar.attributes()).toMatchObject({
                'aria-valuenow': '8',
                'aria-valuemin': '0',
                'aria-valuemax': '12',
                'aria-valuetext': progress,
            });
            expect(bar.get('div').attributes('style')).toContain('width: 67%');
        },
    );

    it('renders done rows without a reason or link and to-do rows with the exact missing setting and a fix link', () => {
        const wrapper = mount(StoreSetupChecklistCard, {
            props: { checklist: checklist(['smtp', 'paymentMethod']) },
        });
        expect(wrapper.findAll('[data-key]')).toHaveLength(12);
        const done = row(wrapper, 'smtp');
        expect(done.attributes('data-done')).toBe('true');
        expect(done.classes()).toContain('text-muted');
        expect(done.text()).toContain(
            t('store.dashboard.checklist.items.smtp.label'),
        );
        expect(done.find('[data-reason]').exists()).toBe(false);
        expect(done.find('a').exists()).toBe(false);

        const todo = row(wrapper, 'shipping');
        expect(todo.attributes('data-done')).toBe('false');
        expect(todo.text()).toContain(
            t('store.dashboard.checklist.items.shipping.label'),
        );
        const reason = todo.get('[data-reason="shipping.flat_rate_cost"]');
        const text = t(
            'store.dashboard.checklist.reasons.shipping.flat_rate_cost',
        );
        expect(reason.text()).toContain(text);
        expect(reason.get('a').text()).toBe(t('store.dashboard.checklist.fix'));
        expect(reason.get('a').attributes('aria-label')).toBe(
            t('store.dashboard.checklist.fixReason', { reason: text }),
        );
        expect(
            wrapper
                .findAll('svg')
                .every((icon) => icon.attributes('aria-hidden') === 'true'),
        ).toBe(true);
    });

    it('links every reason to the page that sets it, highlighting the exact setting', () => {
        const wrapper = mount(StoreSetupChecklistCard, {
            props: { checklist: checklist([]) },
        });
        expect(wrapper.findAllComponents(Link)).toHaveLength(REASONS.length);

        for (const [reason, target] of REASONS) {
            expect(
                wrapper
                    .get(`[data-reason="${reason.key}"] a`)
                    .attributes('href'),
            ).toBe(target.url);
        }
    });

    it('lists one line and link per missing part when a check has several', () => {
        const wrapper = mount(StoreSetupChecklistCard, {
            props: { checklist: checklist([]) },
        });
        const reasons = row(wrapper, 'siteBasics').findAll('[data-reason]');
        expect(
            reasons.map((reason) => reason.attributes('data-reason')),
        ).toEqual([
            'siteBasics.site_name',
            'siteBasics.contact_email',
            'siteBasics.contact_phone',
            'siteBasics.header_logo',
        ]);
        expect(reasons.every((reason) => reason.find('a').exists())).toBe(true);
    });

    it.each(['en', 'ar'] as const)(
        'names the language in both language reasons in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mount(StoreSetupChecklistCard, {
                props: { checklist: checklist([]) },
            });

            for (const key of ['default_missing', 'default_inactive']) {
                expect(
                    wrapper.get(`[data-reason="language.${key}"]`).text(),
                ).toContain('nl');
                expect(
                    wrapper.get(`[data-reason="language.${key}"]`).text(),
                ).not.toContain('{code}');
            }
        },
    );

    it.each(['en', 'ar'] as const)(
        'words every reason in %s, never falling back to its key',
        (locale) => {
            i18n.global.locale.value = locale;

            for (const [reason] of REASONS) {
                const path = `store.dashboard.checklist.reasons.${reason.key}`;
                expect(i18n.global.te(path, locale)).toBe(true);
                expect(t(path)).not.toBe(path);
            }
        },
    );

    it('collapses and expands the list with an accessible toggle button', async () => {
        const wrapper = mount(StoreSetupChecklistCard, {
            props: { checklist: checklist(['smtp']) },
        });
        const button = wrapper.get('button');
        const list = wrapper.get('ul');
        expect(button.attributes('type')).toBe('button');
        expect(button.attributes('aria-expanded')).toBe('true');
        expect(button.attributes('aria-controls')).toBe(list.attributes('id'));
        expect(button.attributes('aria-label')).toBe(
            t('store.dashboard.checklist.collapse'),
        );
        expect(list.element.style.display).not.toBe('none');

        await button.trigger('click');
        expect(button.attributes('aria-expanded')).toBe('false');
        expect(button.attributes('aria-label')).toBe(
            t('store.dashboard.checklist.expand'),
        );
        expect(list.element.style.display).toBe('none');
        expect(wrapper.find('[role="progressbar"]').exists()).toBe(true);

        await button.trigger('click');
        expect(button.attributes('aria-expanded')).toBe('true');
        expect(list.element.style.display).not.toBe('none');
    });
});

import type { UrlMethodPair } from '@inertiajs/core';
import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import {
    activation,
    updatePayments,
} from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import { i18n, t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type {
    StorePaymentGateway,
    StorePaymentGatewayAuditEntry,
    StorePaymentGatewayField,
    StorePaymentGatewaySettings,
} from '@/types/store';
import Payments from './Payments.vue';

const { usePageMock, formSlotPropsMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { get: vi.fn(), post: vi.fn() },
    };
});

function field(
    key: string,
    overrides: Partial<StorePaymentGatewayField> = {},
): StorePaymentGatewayField {
    return {
        key,
        secret: false,
        required: true,
        textarea: false,
        max: 100,
        options: null,
        env: key.toUpperCase(),
        value: null,
        set: false,
        unreadable: false,
        environment: null,
        ...overrides,
    };
}

function bkash(
    overrides: Partial<StorePaymentGateway> = {},
): StorePaymentGateway {
    return {
        key: 'bkash',
        name: 'bKash',
        flow: true,
        identifying: 'app_key',
        source: 'admin',
        enabled: false,
        offered_setting: 'bkash',
        offered: true,
        mode_kind: 'setting',
        mode: 'sandbox',
        fixed_currency: null,
        currency_without_rate: null,
        fields: [
            field('app_key', { value: 'ADMINKEY' }),
            field('app_secret', { secret: true, set: true }),
            field('username', { value: 'admin-user' }),
            field('password', { secret: true, set: false }),
            field('currency', { value: 'BDT', environment: 'BDT' }),
        ],
        effective: {
            app_key: { value: 'ADMINKEY', source: 'admin' },
            app_secret: { set: true, source: 'admin' },
            username: { value: 'admin-user', source: 'admin' },
            password: { set: false, source: 'admin' },
            currency: { value: 'BDT', source: 'admin' },
        },
        missing: ['password'],
        env_only: [],
        live_unconfirmed: false,
        ...overrides,
    };
}

function stripe(): StorePaymentGateway {
    return {
        key: 'stripe',
        name: 'Stripe',
        flow: false,
        identifying: 'publishable_key',
        source: 'env',
        enabled: false,
        offered_setting: null,
        offered: null,
        mode_kind: 'derived',
        mode: null,
        fixed_currency: null,
        currency_without_rate: null,
        fields: [
            field('publishable_key'),
            field('secret_key', { secret: true }),
            field('currency', { required: false }),
        ],
        effective: {
            publishable_key: { value: null, source: 'env' },
            secret_key: { set: false, source: 'env' },
            currency: { value: null, source: 'env' },
        },
        missing: ['publishable_key', 'secret_key'],
        env_only: [],
        live_unconfirmed: false,
    };
}

function nagad(): StorePaymentGateway {
    return {
        key: 'nagad',
        name: 'Nagad',
        flow: true,
        identifying: 'merchant_id',
        source: 'env',
        enabled: false,
        offered_setting: 'nagad',
        offered: false,
        mode_kind: 'field',
        mode: 'sandbox',
        fixed_currency: 'BDT',
        currency_without_rate: null,
        fields: [
            field('merchant_id', { environment: '683002007104225' }),
            field('merchant_private_key', { secret: true, textarea: true }),
            field('mode', {
                options: ['sandbox', 'live'],
                environment: 'sandbox',
            }),
        ],
        effective: {
            merchant_id: { value: '683002007104225', source: 'env' },
            merchant_private_key: { set: true, source: 'env' },
            mode: { value: 'sandbox', source: 'env' },
        },
        missing: [],
        env_only: [],
        live_unconfirmed: false,
    };
}

/** iyzico: an `.env`-only identity number the page shows as set or not set. */
function iyzico(
    overrides: Partial<StorePaymentGateway> = {},
): StorePaymentGateway {
    return {
        ...bkash({
            key: 'iyzico',
            name: 'iyzico',
            offered_setting: 'iyzico',
            fields: [field('api_key'), field('secret_key', { secret: true })],
            effective: {},
            missing: [],
        }),
        env_only: [
            {
                key: 'identity_number',
                env: 'IYZICO_IDENTITY_NUMBER',
                set: false,
            },
        ],
        ...overrides,
    };
}

/** N-Genius and AamarPay: the gateways whose live setup the provider has not confirmed. */
function unconfirmed(
    key: 'ngenius' | 'aamarpay',
    overrides: Partial<StorePaymentGateway> = {},
): StorePaymentGateway {
    return bkash({
        key,
        name: key === 'ngenius' ? 'N-Genius' : 'AamarPay',
        offered_setting: key,
        fields: [field('currency')],
        effective: {},
        missing: [],
        mode: 'live',
        ...overrides,
    });
}

function settings(
    overrides: Partial<StorePaymentGatewaySettings> = {},
): StorePaymentGatewaySettings {
    return {
        enabledSource: 'env',
        environmentEnabled: ['payhere'],
        gateways: [bkash(), nagad(), stripe()],
        ...overrides,
    };
}

function mountPage(
    payments: StorePaymentGatewaySettings = settings(),
    options: {
        permissions?: string[];
        highlight?: string | null;
        attach?: boolean;
        auditEntries?: StorePaymentGatewayAuditEntry[];
    } = {},
) {
    usePageMock.mockReturnValue({
        url: '/temp/admin/payment-method',
        props: defaultAuthProps({
            store: storeSharedProps({
                permissions: options.permissions ?? [
                    'payment_methods_configurations',
                    'features_activation',
                ],
            }),
        }),
    });

    return mount(Payments, {
        props: {
            payments,
            highlight: options.highlight ?? null,
            auditEntries: options.auditEntries ?? [],
        },
        attachTo: options.attach ? document.body : undefined,
    });
}

function byName(wrapper: ReturnType<typeof mountPage>, name: string) {
    return wrapper.get(`[name="${name}"]`);
}

beforeEach(() => {
    vi.clearAllMocks();
    formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    Element.prototype.scrollIntoView = vi.fn();
    i18n.global.locale.value = 'en';
});

describe('Store payment methods page', () => {
    it('posts to the payments update route and empties the secrets and the current password after every submit', () => {
        const form = mountPage().findComponent(Form);

        expect((form.props('action') as UrlMethodPair).url).toBe(
            updatePayments.url(),
        );
        expect(form.props('resetOnSuccess')).toEqual([
            'current_password',
            'gateways[bkash][app_secret]',
            'gateways[bkash][password]',
            'gateways[nagad][merchant_private_key]',
            'gateways[stripe][secret_key]',
        ]);
        expect(form.props('resetOnError')).toEqual(
            form.props('resetOnSuccess'),
        );
    });

    it('shows saved public values and never a secret, with "leave empty to keep"', () => {
        const wrapper = mountPage();

        expect(
            (
                byName(wrapper, 'gateways[bkash][app_key]')
                    .element as HTMLInputElement
            ).value,
        ).toBe('ADMINKEY');
        const secret = byName(wrapper, 'gateways[bkash][app_secret]');
        expect(secret.attributes('type')).toBe('password');
        expect((secret.element as HTMLInputElement).value).toBe('');
        expect(secret.attributes('placeholder')).toBe(
            t('store.settings.payments.secretKeep'),
        );
        expect(
            wrapper.find('[name="gateways[bkash][app_secret_clear]"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[name="gateways[bkash][password_clear]"]').exists(),
        ).toBe(false);
    });

    it('offers the .env list or the switches, and locks the switches while .env is followed', async () => {
        const wrapper = mountPage();
        const source = wrapper.get('#enabled_source');

        expect(source.findAll('option')[0].text()).toBe(
            t('store.settings.payments.enabledSources.env', {
                list: 'payhere',
            }),
        );
        expect(
            byName(wrapper, 'gateways[bkash][enabled]').attributes('disabled'),
        ).toBeDefined();

        await source.setValue('admin');
        expect(
            byName(wrapper, 'gateways[bkash][enabled]').attributes('disabled'),
        ).toBeUndefined();
        // Stripe has no payment flow: its switch stays locked, with the note.
        expect(
            byName(wrapper, 'gateways[stripe][enabled]').attributes('disabled'),
        ).toBeDefined();
        expect(wrapper.get('[data-gateway="stripe"]').text()).toContain(
            t('store.settings.payments.noFlow'),
        );
    });

    it('warns inline, naming the field, when an enabled gateway lacks a required credential', () => {
        const wrapper = mountPage(
            settings({ gateways: [bkash({ enabled: true, offered: false })] }),
        );
        const warning = wrapper.get('[data-missing="password"]');

        expect(warning.attributes('role')).toBe('alert');
        expect(warning.text()).toBe(
            t('store.settings.payments.missing', {
                gateway: 'bKash',
                field: t('store.settings.payments.fields.password'),
            }),
        );
        expect(wrapper.get('[data-warning="not-offered"]').text()).toBe(
            t('store.settings.payments.notOffered', { gateway: 'bKash' }),
        );
    });

    it("names iyzico's identity number in red while it is enabled and unset in .env, and shows it read-only", () => {
        const wrapper = mountPage(
            settings({ gateways: [iyzico({ enabled: true })] }),
        );
        const alert = wrapper.get(
            '[data-missing-env="iyzico.identity_number"]',
        );

        expect(alert.attributes('role')).toBe('alert');
        expect(alert.classes()).toContain('text-danger');
        expect(alert.text()).toBe(
            t('store.settings.payments.envOnlyMissing', {
                gateway: 'iyzico',
                env: 'IYZICO_IDENTITY_NUMBER',
            }),
        );
        const row = wrapper.get('[data-field="iyzico.identity_number"]');
        expect(row.text()).toContain(
            t('store.settings.payments.envOnlyFields.identity_number', {
                env: 'IYZICO_IDENTITY_NUMBER',
            }),
        );
        expect(row.text()).toContain(
            t('store.settings.payments.effective.notSet'),
        );
        expect(row.find('input').exists()).toBe(false);
    });

    it('shows the identity number as set, without an alert, once .env has it, and without the alert while iyzico is off', () => {
        const set = mountPage(
            settings({
                gateways: [
                    iyzico({
                        enabled: true,
                        env_only: [
                            {
                                key: 'identity_number',
                                env: 'IYZICO_IDENTITY_NUMBER',
                                set: true,
                            },
                        ],
                    }),
                ],
            }),
        );
        expect(set.find('[data-missing-env]').exists()).toBe(false);
        expect(
            set.get('[data-field="iyzico.identity_number"]').text(),
        ).toContain(t('store.settings.payments.effective.set'));

        const off = mountPage(settings({ gateways: [iyzico()] }));
        expect(off.find('[data-missing-env]').exists()).toBe(false);
        expect(off.find('[data-field="iyzico.identity_number"]').exists()).toBe(
            true,
        );
    });

    it('opens the iyzico tab of a checklist link and marks only the identity number', () => {
        const wrapper = mountPage(
            settings({ gateways: [bkash(), iyzico({ enabled: true })] }),
            { highlight: 'iyzico.identity_number', attach: true },
        );

        expect(
            wrapper.get('[data-tab="iyzico"]').attributes('aria-selected'),
        ).toBe('true');
        const marked = wrapper.findAll('[data-highlighted]');
        expect(marked).toHaveLength(1);
        expect(marked[0].attributes('data-field')).toBe(
            'iyzico.identity_number',
        );
        wrapper.unmount();
    });

    it.each([
        ['ngenius', 'NGENIUS_LIVE_IDENTITY_URL'],
        ['aamarpay', 'AamarPay'],
    ] as const)(
        'shows the unconfirmed-live note of %s only while the server says so, in both languages',
        async (key, mention) => {
            const on = mountPage(
                settings({
                    gateways: [
                        unconfirmed(key, {
                            enabled: true,
                            live_unconfirmed: true,
                        }),
                    ],
                }),
            );
            const note = on.get(`[data-note="unconfirmed-live-${key}"]`);

            expect(note.attributes('role')).toBe('note');
            expect(note.classes()).toContain('text-warning');
            expect(note.text()).toBe(
                t(`store.settings.payments.liveUnconfirmed.${key}`),
            );
            expect(note.text()).toContain(mention);
            // The first page re-renders with the locale, so keep its English text.
            const english = note.text();

            i18n.global.locale.value = 'ar';
            const ar = mountPage(
                settings({
                    gateways: [
                        unconfirmed(key, {
                            enabled: true,
                            live_unconfirmed: true,
                        }),
                    ],
                }),
            );
            await nextTick();
            expect(ar.get(`[data-note="unconfirmed-live-${key}"]`).text()).toBe(
                t(`store.settings.payments.liveUnconfirmed.${key}`),
            );
            expect(
                ar.get(`[data-note="unconfirmed-live-${key}"]`).text(),
            ).not.toBe(english);

            i18n.global.locale.value = 'en';
            const off = mountPage(
                settings({
                    gateways: [unconfirmed(key, { enabled: true })],
                }),
            );
            expect(off.find('[data-note^="unconfirmed-live"]').exists()).toBe(
                false,
            );
        },
    );

    it('does not warn about a disabled gateway', () => {
        expect(mountPage().find('[data-missing]').exists()).toBe(false);
    });

    it('marks a required field exactly while another field of its group is typed, and a secret only when none is kept', async () => {
        const wrapper = mountPage(
            settings({
                gateways: [
                    bkash({
                        fields: [
                            field('app_key'),
                            field('app_secret', { secret: true, set: true }),
                            field('username'),
                            field('password', { secret: true }),
                            field('currency'),
                        ],
                    }),
                ],
            }),
        );
        const username = byName(wrapper, 'gateways[bkash][username]');
        const password = byName(wrapper, 'gateways[bkash][password]');
        const secret = byName(wrapper, 'gateways[bkash][app_secret]');

        expect(username.attributes('required')).toBeUndefined();
        await byName(wrapper, 'gateways[bkash][app_key]').setValue('ADMINKEY');
        expect(username.attributes('required')).toBeDefined();
        expect(password.attributes('required')).toBeDefined();
        // The saved app secret is kept, so it is not required ...
        expect(secret.attributes('required')).toBeUndefined();
        // ... until "remove" is ticked.
        await byName(wrapper, 'gateways[bkash][app_secret_clear]').setValue(
            true,
        );
        await nextTick();
        expect(secret.attributes('required')).toBeDefined();
        expect(secret.attributes('disabled')).toBeDefined();

        await byName(wrapper, 'gateways[bkash][app_key]').setValue('');
        expect(username.attributes('required')).toBeUndefined();
    });

    it('lists the effective configuration with its source, secrets only as set or not set', () => {
        const list = mountPage().get('[data-section="effective-bkash"]').text();

        expect(list).toContain(
            t('store.settings.payments.effective.title', { gateway: 'bKash' }),
        );
        expect(list).toContain(
            t('store.settings.payments.effective.sources.admin'),
        );
        expect(list).toContain('ADMINKEY');
        expect(list).toContain(t('store.settings.payments.effective.set'));
        expect(list).toContain(t('store.settings.payments.effective.notSet'));
    });

    it('shows the Features activation state with a link that highlights the switch', () => {
        const wrapper = mountPage();
        const offered = wrapper.get('[data-section="offered"]');

        expect(offered.text()).toContain(
            t('store.settings.payments.offered', {
                state: t('store.settings.payments.offeredStates.on'),
            }),
        );
        expect(offered.get('a').attributes('href')).toBe(
            activation({ query: { highlight: 'bkash' } }).url,
        );
    });

    it('hides the activation link from staff who may not open that page', () => {
        const wrapper = mountPage(settings(), {
            permissions: ['payment_methods_configurations'],
        });

        expect(wrapper.get('[data-section="offered"]').find('a').exists()).toBe(
            false,
        );
    });

    it('opens the tab of a highlighted field and marks only that field', () => {
        const wrapper = mountPage(settings(), {
            highlight: 'nagad.merchant_private_key',
            attach: true,
        });

        expect(
            wrapper.get('[data-tab="nagad"]').attributes('aria-selected'),
        ).toBe('true');
        const marked = wrapper.findAll('[data-highlighted]');
        expect(marked).toHaveLength(1);
        expect(
            marked[0]
                .find('[name="gateways[nagad][merchant_private_key]"]')
                .exists(),
        ).toBe(true);
        wrapper.unmount();
    });

    it('marks nothing without a highlight and starts on the first tab', () => {
        const wrapper = mountPage();

        expect(wrapper.findAll('[data-highlighted]')).toHaveLength(0);
        expect(
            wrapper.get('[data-tab="bkash"]').attributes('aria-selected'),
        ).toBe('true');
    });

    it("shows Nagad's fixed currency and its .env mode as the empty choice", () => {
        const panel = mountPage().get('[data-gateway="nagad"]');

        expect(panel.text()).toContain(
            t('store.settings.payments.currencyFixed', { code: 'BDT' }),
        );
        expect(
            panel
                .get('[name="gateways[nagad][mode]"]')
                .findAll('option')[0]
                .text(),
        ).toBe(
            t('store.settings.payments.modeFromEnv', {
                mode: t('store.settings.payments.modes.sandbox'),
            }),
        );
    });

    it('asks for the current password, required', () => {
        const password = mountPage().get('#current_password');

        expect(password.attributes('type')).toBe('password');
        expect(password.attributes('required')).toBeDefined();
        expect(password.attributes('autocomplete')).toBe('current-password');
    });

    it('lists recent changes by field and kind, and refused passwords, never a value', () => {
        const wrapper = mountPage(settings(), {
            auditEntries: [
                {
                    id: 2,
                    action: 'payment_gateway_settings.password_failed',
                    changed_fields: {},
                    user_name: 'Store Admin',
                    user_email: 'admin@example.com',
                    user_deleted: false,
                    user_id: 5,
                    ip_address: '203.0.113.9',
                    created_at: '2026-11-12T10:00:00+00:00',
                },
                {
                    id: 1,
                    action: 'payment_gateway_settings.updated',
                    changed_fields: {
                        'bkash.app_secret': 'changed',
                        'bkash.enabled': 'enabled',
                        enabled_source: 'set',
                    },
                    user_name: 'Store Admin',
                    user_email: 'admin@example.com',
                    user_deleted: false,
                    user_id: 5,
                    ip_address: '203.0.113.9',
                    created_at: '2026-11-12T09:00:00+00:00',
                },
            ],
        });
        const audit = wrapper.get('[data-section="payment_audit"]').text();

        expect(audit).toContain(
            t('store.settings.payments.audit.passwordFailed'),
        );
        expect(audit).toContain(
            [
                t('store.settings.payments.audit.fieldChange', {
                    field: t('store.settings.payments.audit.gatewayField', {
                        gateway: 'bKash',
                        field: t('store.settings.payments.fields.app_secret'),
                    }),
                    change: t('store.settings.payments.audit.kinds.changed'),
                }),
                t('store.settings.payments.audit.fieldChange', {
                    field: 'bKash',
                    change: t('store.settings.payments.audit.kinds.enabled'),
                }),
                t('store.settings.payments.audit.fieldChange', {
                    field: t('store.settings.payments.audit.enabledSource'),
                    change: t('store.settings.payments.audit.kinds.set'),
                }),
            ].join(t('store.settings.payments.audit.separator')),
        );
    });

    it.each(['en', 'ar'] as const)(
        'has every text of the page in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountPage();

            expect(wrapper.text()).not.toContain('store.settings.payments');
        },
    );
});

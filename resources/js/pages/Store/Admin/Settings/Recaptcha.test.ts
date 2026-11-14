import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { updateRecaptcha } from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import { i18n, t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type {
    StoreRecaptchaEffective,
    StoreRecaptchaSettings,
} from '@/types/store';
import Recaptcha from './Recaptcha.vue';

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

const SITE_KEY = '6LeAbCdEfGhIjKlMnOpQrStUvWxYz0123456789';
const SECRET = 'S3cret-recaptcha-secret-value-123456';

function saved(
    overrides: Partial<StoreRecaptchaSettings> = {},
): StoreRecaptchaSettings {
    return {
        site_key: SITE_KEY,
        secret_set: true,
        secret_unreadable: false,
        ...overrides,
    };
}

const none: StoreRecaptchaSettings = {
    site_key: null,
    secret_set: false,
    secret_unreadable: false,
};

function effective(
    overrides: Partial<StoreRecaptchaEffective> = {},
): StoreRecaptchaEffective {
    return {
        site_key: { value: SITE_KEY, source: 'admin' },
        secret: { set: true, source: 'admin' },
        ...overrides,
    };
}

function mountPage(
    recaptcha: StoreRecaptchaSettings = saved(),
    options: {
        enabled?: boolean;
        effective?: StoreRecaptchaEffective;
        highlight?: string | null;
        attach?: boolean;
    } = {},
) {
    usePageMock.mockReturnValue({
        url: '/temp/admin/google-recaptcha',
        props: defaultAuthProps({
            store: storeSharedProps({
                permissions: ['google_recaptcha_configuration'],
            }),
        }),
    });

    return mount(Recaptcha, {
        props: {
            settings: { google_recaptcha: options.enabled ?? true },
            recaptcha,
            effective: options.effective ?? effective(),
            highlight: options.highlight ?? null,
        },
        attachTo: options.attach ? document.body : undefined,
    });
}

beforeEach(() => {
    vi.clearAllMocks();
    formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    Element.prototype.scrollIntoView = vi.fn();
});

describe('reCAPTCHA settings page', () => {
    it('posts the switch, site key and secret to the reCAPTCHA update route', () => {
        const wrapper = mountPage();

        expect(wrapper.findComponent(Form).props('action')).toEqual(
            updateRecaptcha(),
        );
        expect(wrapper.get('select').attributes('name')).toBe(
            'settings[google_recaptcha]',
        );
    });

    it('shows the site key in full, never the secret, with "leave empty to keep"', () => {
        const wrapper = mountPage();
        const secret = wrapper.get('#recaptcha_secret_key');

        expect(
            (wrapper.get('#recaptcha_site_key').element as HTMLInputElement)
                .value,
        ).toBe(SITE_KEY);
        expect(secret.attributes('type')).toBe('password');
        expect((secret.element as HTMLInputElement).value).toBe('');
        expect(secret.attributes('placeholder')).toBe(
            t('store.settings.recaptcha.secretKeep'),
        );
        expect(wrapper.text()).toContain(
            t('store.settings.recaptcha.secretSaved'),
        );
        expect(wrapper.html()).not.toContain(SECRET);
    });

    it('leaves an empty form empty, with no remove control and no marks', () => {
        const wrapper = mountPage(none, {
            enabled: false,
            effective: effective({
                site_key: { value: null, source: 'env' },
                secret: { set: false, source: 'env' },
            }),
        });

        expect(
            (wrapper.get('#recaptcha_site_key').element as HTMLInputElement)
                .value,
        ).toBe('');
        expect(
            wrapper.get('#recaptcha_secret_key').attributes('placeholder'),
        ).toBeUndefined();
        expect(wrapper.find('#recaptcha_secret_clear').exists()).toBe(false);
        expect(wrapper.text()).toContain(
            t('store.settings.recaptcha.secretNotSaved'),
        );
        expect(
            wrapper.get('#recaptcha_site_key').attributes('required'),
        ).toBeUndefined();
        expect(
            wrapper.get('#recaptcha_secret_key').attributes('required'),
        ).toBeUndefined();
    });

    it('marks the secret required while a new or changed site key is typed, the key while a secret is', async () => {
        const wrapper = mountPage(none, { enabled: false });

        await wrapper.get('#recaptcha_site_key').setValue(SITE_KEY);
        expect(
            wrapper.get('#recaptcha_secret_key').attributes('required'),
        ).toBeDefined();
        expect(wrapper.text()).toContain(t('common.requiredLegend'));
        await wrapper.get('#recaptcha_site_key').setValue('');
        await wrapper.get('#recaptcha_secret_key').setValue(SECRET);
        expect(
            wrapper.get('#recaptcha_site_key').attributes('required'),
        ).toBeDefined();
    });

    it('does not require the secret again for the saved key, only for a changed one', async () => {
        const wrapper = mountPage();

        expect(
            wrapper.get('#recaptcha_secret_key').attributes('required'),
        ).toBeUndefined();
        await wrapper.get('#recaptcha_site_key').setValue(`${SITE_KEY}x`);
        expect(
            wrapper.get('#recaptcha_secret_key').attributes('required'),
        ).toBeDefined();
    });

    it('disables the secret field while the saved secret is being removed', async () => {
        const wrapper = mountPage();

        await wrapper.get('#recaptcha_secret_clear').setValue(true);
        expect(
            wrapper.get('#recaptcha_secret_key').attributes('disabled'),
        ).toBeDefined();
        expect(wrapper.get('#recaptcha_secret_clear').attributes('name')).toBe(
            'recaptcha_secret_clear',
        );
    });

    it('asks for the secret again when the saved one cannot be read', () => {
        const wrapper = mountPage(saved({ secret_unreadable: true }));

        expect(wrapper.text()).toContain(
            t('store.settings.recaptcha.secretUnreadable'),
        );
        expect(
            wrapper.get('#recaptcha_secret_key').attributes('placeholder'),
        ).toBeUndefined();
        expect(
            wrapper.get('#recaptcha_secret_key').attributes('required'),
        ).toBeDefined();
    });

    it('warns by field when reCAPTCHA is on without a site key or a secret', () => {
        const wrapper = mountPage(none, {
            effective: effective({
                site_key: { value: null, source: 'env' },
                secret: { set: false, source: 'env' },
            }),
        });

        expect(
            wrapper
                .findAll('[role="alert"]')
                .map((alert) => alert.attributes('data-warning')),
        ).toEqual(['missingSiteKey', 'missingSecret']);
        expect(wrapper.text()).toContain(
            t('store.settings.recaptcha.missingSecret'),
        );
    });

    it('does not warn when it is off, or when both values are in effect', () => {
        expect(mountPage().findAll('[role="alert"]')).toHaveLength(0);
        expect(
            mountPage(none, {
                enabled: false,
                effective: effective({
                    secret: { set: false, source: 'env' },
                }),
            }).findAll('[role="alert"]'),
        ).toHaveLength(0);
    });

    it('lists the effective values and their source, the secret only as set or not set', () => {
        const wrapper = mountPage();
        const list = wrapper.get('[data-section="recaptcha_effective"]').text();
        const admin = t('store.settings.recaptcha.effective.sources.admin');

        expect(list).toContain(
            t('store.settings.recaptcha.effective.withSource', {
                value: SITE_KEY,
                source: admin,
            }),
        );
        expect(list).toContain(
            t('store.settings.recaptcha.effective.withSource', {
                value: t('store.settings.recaptcha.effective.set'),
                source: admin,
            }),
        );
        expect(list).not.toContain(SECRET);
    });

    it.each([
        ['google_recaptcha', 'select'],
        ['recaptcha_site_key', '#recaptcha_site_key'],
        ['recaptcha_secret_key', '#recaptcha_secret_key'],
    ])(
        'marks and focuses %s for a checklist link, and nothing else',
        async (highlight, control) => {
            const wrapper = mountPage(saved(), { highlight, attach: true });
            await wrapper.vm.$nextTick();
            await wrapper.vm.$nextTick();

            const marked = wrapper.findAll('[data-highlighted]');
            expect(marked).toHaveLength(1);
            expect(marked[0].find(control).exists()).toBe(true);
            wrapper.unmount();
        },
    );

    it('marks nothing without a highlight', () => {
        expect(mountPage().findAll('[data-highlighted]')).toHaveLength(0);
    });

    it("checks the form in the browser first, with the server's wording", async () => {
        const wrapper = mountPage(none, { enabled: false, attach: true });
        const form = wrapper.findComponent(Form);
        const onBefore = form.props('onBefore') as () => boolean;

        expect(form.attributes('novalidate')).toBeDefined();
        expect(onBefore()).toBe(true);

        await wrapper.get('#recaptcha_site_key').setValue('short"><b>');
        expect(onBefore()).toBe(false);
        await wrapper.vm.$nextTick();
        expect(
            wrapper.get('#recaptcha_site_key').element.parentElement
                ?.textContent,
        ).toContain('The reCAPTCHA site key field format is invalid.');

        await wrapper.get('#recaptcha_site_key').setValue('');
        await wrapper.get('#recaptcha_secret_key').setValue(SECRET);
        expect(onBefore()).toBe(false);
        await wrapper.vm.$nextTick();
        expect(
            wrapper.get('#recaptcha_site_key').element.parentElement
                ?.textContent,
        ).toContain('The reCAPTCHA site key field is required when');

        await wrapper.get('#recaptcha_site_key').setValue(SITE_KEY);
        expect(onBefore()).toBe(true);
        wrapper.unmount();
    });

    it('shows server errors on their fields', () => {
        formSlotPropsMock.mockReturnValue({
            errors: {
                recaptcha_secret_key: 'Enter the reCAPTCHA secret key.',
                recaptcha_site_key: 'Bad site key.',
            },
            processing: false,
        });
        const text = mountPage().text();

        expect(text).toContain('Enter the reCAPTCHA secret key.');
        expect(text).toContain('Bad site key.');
    });

    it('has every reCAPTCHA string in English and Arabic', () => {
        const keys = [
            'intro',
            'siteKey',
            'siteKeyHelp',
            'secretKey',
            'secretKeep',
            'secretSaved',
            'secretNotSaved',
            'secretUnreadable',
            'secretHelp',
            'removeSecret',
            'missingSiteKey',
            'missingSecret',
            'effective.title',
            'effective.withSource',
            'effective.siteKey',
            'effective.secretKey',
            'effective.notSet',
            'effective.set',
            'effective.sources.admin',
            'effective.sources.env',
        ];

        for (const locale of ['en', 'ar'] as const) {
            for (const key of keys) {
                expect(
                    i18n.global.te(`store.settings.recaptcha.${key}`, locale),
                    `${locale}: store.settings.recaptcha.${key}`,
                ).toBe(true);
            }
        }
    });
});

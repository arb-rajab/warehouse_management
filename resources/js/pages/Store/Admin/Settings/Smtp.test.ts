import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    testSmtp,
    updateSmtp,
} from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import { i18n, t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type {
    StoreSmtpEffectiveSettings,
    StoreSmtpSettings,
} from '@/types/store';
import Smtp from './Smtp.vue';

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

const SECRET = 'S3cret-smtp-pass!';

function saved(overrides: Partial<StoreSmtpSettings> = {}): StoreSmtpSettings {
    return {
        mailer: 'smtp',
        host: 'smtp.example.com',
        port: 587,
        scheme: null,
        username: 'shop@example.com',
        from_address: 'shop@example.com',
        from_name: null,
        password_set: true,
        password_unreadable: false,
        environment_mailer: 'log',
        ...overrides,
    };
}

function empty(): StoreSmtpSettings {
    return saved({
        mailer: null,
        host: null,
        port: null,
        username: null,
        from_address: null,
        password_set: false,
    });
}

function effective(): StoreSmtpEffectiveSettings {
    return {
        mailer: { value: 'smtp', source: 'admin' },
        host: { value: 'smtp.example.com', source: 'admin' },
        port: { value: '587', source: 'admin' },
        scheme: { value: null, source: 'admin' },
        username: { set: true, source: 'admin' },
        password: { set: true, source: 'admin' },
        from_address: { value: 'shop@example.com', source: 'admin' },
        from_name: { value: 'Env Shop', source: 'env' },
    };
}

function mountPage(
    smtp: StoreSmtpSettings = saved(),
    options: {
        permissions?: string[];
        highlight?: string | null;
        attach?: boolean;
    } = {},
) {
    usePageMock.mockReturnValue({
        url: '/temp/admin/smtp-settings',
        props: defaultAuthProps({
            store: storeSharedProps({
                permissions: options.permissions ?? ['smtp_settings'],
            }),
        }),
    });

    return mount(Smtp, {
        props: {
            smtp,
            effective: effective(),
            highlight: options.highlight ?? null,
        },
        attachTo: options.attach ? document.body : undefined,
    });
}

function settingsForm(wrapper: ReturnType<typeof mountPage>) {
    return wrapper
        .findAllComponents(Form)
        .find((form) => form.props('action')?.url === updateSmtp.url())!;
}

beforeEach(() => {
    vi.clearAllMocks();
    formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    Element.prototype.scrollIntoView = vi.fn();
});

describe('SMTP settings page', () => {
    it('posts the settings form to the SMTP update route', () => {
        expect(settingsForm(mountPage()).props('action')).toEqual(updateSmtp());
    });

    it('shows the saved values, never a password, with "leave empty to keep"', () => {
        const wrapper = mountPage();

        expect((wrapper.get('#host').element as HTMLInputElement).value).toBe(
            'smtp.example.com',
        );
        expect((wrapper.get('#port').element as HTMLInputElement).value).toBe(
            '587',
        );
        expect(
            (wrapper.get('#username').element as HTMLInputElement).value,
        ).toBe('shop@example.com');
        const password = wrapper.get('#password');
        expect(password.attributes('type')).toBe('password');
        expect((password.element as HTMLInputElement).value).toBe('');
        expect(password.attributes('placeholder')).toBe(
            t('store.settings.smtp.passwordKeep'),
        );
        expect(wrapper.text()).toContain(
            t('store.settings.smtp.passwordSaved'),
        );
        expect(wrapper.html()).not.toContain(SECRET);
        expect(
            (wrapper.get('#mailer').element as HTMLSelectElement).value,
        ).toBe('smtp');
    });

    it('offers the server mailer, SMTP and log only, and leaves an empty form empty', () => {
        const wrapper = mountPage(empty());
        const options = wrapper.findAll('#mailer option');

        expect(options.map((option) => option.attributes('value'))).toEqual([
            '',
            'smtp',
            'log',
        ]);
        expect(options[0].text()).toBe(
            t('store.settings.smtp.mailerServer', { mailer: 'log' }),
        );
        expect(
            (wrapper.get('#mailer').element as HTMLSelectElement).value,
        ).toBe('');
        expect((wrapper.get('#host').element as HTMLInputElement).value).toBe(
            '',
        );
        expect(wrapper.find('#password').attributes('placeholder')).toBe(
            undefined,
        );
        expect(wrapper.find('#password_clear').exists()).toBe(false);
        expect(wrapper.text()).toContain(
            t('store.settings.smtp.passwordNotSaved'),
        );
    });

    it('marks the port required exactly while a host is entered', async () => {
        const wrapper = mountPage(empty());

        expect(wrapper.get('#port').attributes('required')).toBeUndefined();
        await wrapper.get('#host').setValue('smtp.example.com');
        expect(wrapper.get('#port').attributes('required')).toBeDefined();
        expect(wrapper.text()).toContain(t('common.requiredLegend'));
        await wrapper.get('#host').setValue('');
        expect(wrapper.get('#port').attributes('required')).toBeUndefined();
    });

    it('disables the password field while the saved password is being removed', async () => {
        const wrapper = mountPage();

        expect(wrapper.get('#password').attributes('disabled')).toBeUndefined();
        await wrapper.get('#password_clear').setValue(true);
        expect(wrapper.get('#password').attributes('disabled')).toBeDefined();
        expect(wrapper.get('#password_clear').attributes('name')).toBe(
            'password_clear',
        );
    });

    it('asks for the password again when the saved one cannot be read', () => {
        const wrapper = mountPage(saved({ password_unreadable: true }));

        expect(wrapper.get('[role="alert"]').text()).toBe(
            t('store.settings.smtp.passwordUnreadable'),
        );
        expect(wrapper.get('#password').attributes('placeholder')).toBe(
            undefined,
        );
    });

    it('lists where each effective value comes from, secrets only as set or not set', () => {
        const wrapper = mountPage();
        const list = wrapper.get('[data-section="mail_effective"]').text();
        const admin = t('store.settings.smtp.effective.sources.admin');
        const env = t('store.settings.smtp.effective.sources.env');

        expect(list).toContain(t('store.settings.smtp.effective.title'));
        expect(list).toContain(
            t('store.settings.smtp.effective.withSource', {
                value: 'smtp.example.com',
                source: admin,
            }),
        );
        expect(list).toContain(
            t('store.settings.smtp.effective.withSource', {
                value: 'Env Shop',
                source: env,
            }),
        );
        expect(list).toContain(
            t('store.settings.smtp.effective.withSource', {
                value: t('store.settings.server.set'),
                source: admin,
            }),
        );
        expect(list).toContain(
            t('store.settings.smtp.effective.withSource', {
                value: t('store.settings.smtp.effective.automatic'),
                source: admin,
            }),
        );
    });

    it.each([
        ['mail_mailer', '#mailer'],
        ['mail_host', '#host'],
        ['mail_credentials', '#username'],
    ])(
        'marks and focuses the field a %s checklist link is about, and no other',
        async (highlight, control) => {
            const wrapper = mountPage(saved(), { highlight, attach: true });
            await wrapper.vm.$nextTick();
            await wrapper.vm.$nextTick();

            const marked = wrapper.findAll('[data-highlighted]');
            expect(marked).toHaveLength(1);
            expect(marked[0].find(control).exists()).toBe(true);
            expect(document.activeElement).toBe(wrapper.get(control).element);
            wrapper.unmount();
        },
    );

    it('marks nothing without a highlight', () => {
        expect(mountPage().findAll('[data-highlighted]')).toHaveLength(0);
    });

    it("checks the form in the browser first, with the server's wording", async () => {
        const wrapper = mountPage(empty(), { attach: true });
        const onBefore = settingsForm(wrapper).props(
            'onBefore',
        ) as () => boolean;

        expect(settingsForm(wrapper).attributes('novalidate')).toBeDefined();
        expect(onBefore()).toBe(true);

        await wrapper.get('#host').setValue('smtp://evil.example.com/x');
        await wrapper.get('#port').setValue('70000');
        expect(onBefore()).toBe(false);
        await wrapper.vm.$nextTick();
        expect(
            wrapper.get('#host').element.parentElement?.textContent,
        ).toContain('The host field format is invalid.');
        expect(
            wrapper.get('#port').element.parentElement?.textContent,
        ).toContain('The port field must not be greater than 65535.');

        await wrapper.get('#host').setValue('smtp.example.com');
        await wrapper.get('#port').setValue('');
        expect(onBefore()).toBe(false);
        await wrapper.vm.$nextTick();
        expect(
            wrapper.get('#port').element.parentElement?.textContent,
        ).toContain('The port field is required when');

        await wrapper.get('#port').setValue('587');
        expect(onBefore()).toBe(true);
        wrapper.unmount();
    });

    it('shows server errors on their fields', () => {
        formSlotPropsMock.mockReturnValue({
            errors: {
                password: 'Enter the SMTP password again.',
                from_name: 'The from name field format is invalid.',
            },
            processing: false,
        });
        const text = mountPage().text();

        expect(text).toContain('Enter the SMTP password again.');
        expect(text).toContain('The from name field format is invalid.');
    });

    it('offers the test email only to staff who may also send newsletters', () => {
        const without = mountPage();
        expect(
            without
                .findAllComponents(Form)
                .some((form) => form.props('action')?.url === testSmtp.url()),
        ).toBe(false);

        const withTest = mountPage(saved(), {
            permissions: ['smtp_settings', 'send_newsletter'],
        });
        const test = withTest
            .findAllComponents(Form)
            .find((form) => form.props('action')?.url === testSmtp.url())!;
        expect(test.props('action')).toEqual(testSmtp());
        expect(test.get('#email').attributes()).toMatchObject({
            type: 'email',
            maxlength: '255',
            required: '',
        });
        expect(test.text()).toContain(t('common.requiredLegend'));
    });

    it('has every SMTP string in English and Arabic', () => {
        const keys = [
            'intro',
            'passwordUnreadable',
            'mailer',
            'mailerServer',
            'mailerHelp',
            'mailers.smtp',
            'mailers.log',
            'host',
            'hostHelp',
            'port',
            'portHelp',
            'scheme',
            'schemeAutomatic',
            'schemes.smtp',
            'schemes.smtps',
            'username',
            'password',
            'passwordKeep',
            'passwordSaved',
            'passwordNotSaved',
            'passwordHelp',
            'removePassword',
            'fromAddress',
            'fromName',
            'fromHelp',
            'testTitle',
            'testHelp',
            'effective.title',
            'effective.withSource',
            'effective.sources.admin',
            'effective.sources.env',
            'effective.automatic',
            ...[
                'mailer',
                'host',
                'port',
                'scheme',
                'username',
                'password',
                'from_address',
                'from_name',
            ].map((key) => `effective.${key}`),
        ];

        for (const locale of ['en', 'ar'] as const) {
            for (const key of keys) {
                expect(
                    i18n.global.te(`store.settings.smtp.${key}`, locale),
                    `${locale}: store.settings.smtp.${key}`,
                ).toBe(true);
            }
        }
    });
});

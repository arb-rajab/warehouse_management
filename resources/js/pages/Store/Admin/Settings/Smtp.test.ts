import type { UrlMethodPair } from '@inertiajs/core';
import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import {
    testSmtp,
    updateSmtp,
} from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import { i18n, t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type {
    StoreSmtpAuditEntry,
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

function auditEntry(
    overrides: Partial<StoreSmtpAuditEntry> = {},
): StoreSmtpAuditEntry {
    return {
        id: 1,
        action: 'smtp_settings.updated',
        changed_fields: { host: 'changed', password: 'removed' },
        user_name: 'Store Admin',
        user_email: 'store-admin@example.com',
        user_deleted: false,
        user_id: 5,
        ip_address: '203.0.113.7',
        created_at: '2026-11-11T14:32:00+00:00',
        ...overrides,
    };
}

function mountPage(
    smtp: StoreSmtpSettings = saved(),
    options: {
        permissions?: string[];
        highlight?: string | null;
        attach?: boolean;
        auditEntries?: StoreSmtpAuditEntry[];
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
            auditEntries: options.auditEntries ?? [],
        },
        attachTo: options.attach ? document.body : undefined,
    });
}

function settingsForm(wrapper: ReturnType<typeof mountPage>) {
    return wrapper
        .findAllComponents(Form)
        .find(
            (form) =>
                (form.props('action') as UrlMethodPair).url ===
                updateSmtp.url(),
        )!;
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
        // The current password is required on every save.
        expect(onBefore()).toBe(false);
        await wrapper.vm.$nextTick();
        expect(
            wrapper.get('#current_password').element.parentElement?.textContent,
        ).toContain('The current password field is required.');
        await wrapper.get('#current_password').setValue('my-own-password');
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
                current_password: 'The password is incorrect.',
            },
            processing: false,
        });
        const wrapper = mountPage();
        const text = wrapper.text();

        expect(text).toContain('Enter the SMTP password again.');
        expect(text).toContain('The from name field format is invalid.');
        expect(
            wrapper.get('#current_password').element.parentElement?.textContent,
        ).toContain('The password is incorrect.');
    });

    it('asks for the current password, required, never filled in, and empties it and the SMTP password after every submit', async () => {
        const wrapper = mountPage();
        await nextTick();
        const field = wrapper.get('#current_password');

        expect(field.attributes()).toMatchObject({
            type: 'password',
            name: 'current_password',
            autocomplete: 'current-password',
            required: '',
            'aria-describedby': 'current_password_help',
        });
        expect((field.element as HTMLInputElement).value).toBe('');
        expect(wrapper.get('label[for="current_password"]').text()).toContain(
            t('store.settings.smtp.currentPassword'),
        );
        expect(wrapper.get('#current_password_help').text()).toBe(
            t('store.settings.smtp.currentPasswordHelp'),
        );
        expect(wrapper.text()).toContain(t('common.requiredLegend'));
        expect(settingsForm(wrapper).props('resetOnSuccess')).toEqual([
            'current_password',
            'password',
        ]);
        expect(settingsForm(wrapper).props('resetOnError')).toEqual([
            'current_password',
            'password',
        ]);
    });

    it('lists the recent changes: when, who, the IP and which fields, never a value', () => {
        const wrapper = mountPage(saved(), {
            auditEntries: [
                auditEntry(),
                auditEntry({
                    id: 2,
                    action: 'smtp_settings.password_failed',
                    changed_fields: {},
                    ip_address: '198.51.100.4',
                }),
                auditEntry({
                    id: 3,
                    user_name: 'Former Admin',
                    user_email: 'former@example.com',
                    user_deleted: true,
                    user_id: 12,
                    changed_fields: { from_address: 'set' },
                    ip_address: null,
                }),
            ],
        });
        const section = wrapper.get('[data-section="smtp_audit"]');

        expect(section.get('h2').text()).toBe(
            t('store.settings.smtp.audit.title'),
        );
        expect(section.findAll('th').map((th) => th.text())).toEqual([
            t('store.settings.smtp.audit.when'),
            t('store.settings.smtp.audit.who'),
            t('store.settings.smtp.audit.ip'),
            t('store.settings.smtp.audit.what'),
        ]);
        const rows = section.findAll('tbody tr');
        expect(rows).toHaveLength(3);
        expect(rows[0].text()).toContain('Store Admin');
        expect(rows[0].text()).toContain('store-admin@example.com');
        expect(rows[0].text()).toContain('203.0.113.7');
        expect(rows[0].text()).toContain(
            'SMTP host (changed), Password (removed)',
        );
        expect(rows[1].text()).toContain(
            t('store.settings.smtp.audit.passwordFailed'),
        );
        expect(rows[2].text()).toContain('Former Admin');
        expect(rows[2].text()).toContain(
            t('store.settings.smtp.audit.deletedUser', { id: 12 }),
        );
        expect(rows[2].text()).toContain('From address (set)');
        expect(rows[2].findAll('td')[2].text()).toBe('-');
        expect(section.text()).not.toContain('Pest browser');
    });

    it('says when no change has been recorded yet', () => {
        expect(mountPage().get('[data-section="smtp_audit"]').text()).toContain(
            t('store.settings.smtp.audit.empty'),
        );
    });

    it('offers the test email only to staff who may also send newsletters', async () => {
        const without = mountPage();
        expect(
            without
                .findAllComponents(Form)
                .some(
                    (form) =>
                        (form.props('action') as UrlMethodPair).url ===
                        testSmtp.url(),
                ),
        ).toBe(false);

        const withTest = mountPage(saved(), {
            permissions: ['smtp_settings', 'send_newsletter'],
        });
        const test = withTest
            .findAllComponents(Form)
            .find(
                (form) =>
                    (form.props('action') as UrlMethodPair).url ===
                    testSmtp.url(),
            )!;
        expect(test.props('action')).toEqual(testSmtp());
        expect(test.get('#email').attributes()).toMatchObject({
            type: 'email',
            maxlength: '255',
            required: '',
        });
        await nextTick();
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
            'currentPassword',
            'currentPasswordHelp',
            'audit.title',
            'audit.help',
            'audit.when',
            'audit.who',
            'audit.ip',
            'audit.what',
            'audit.empty',
            'audit.passwordFailed',
            'audit.deletedUser',
            'audit.fieldChange',
            'audit.separator',
            'audit.kinds.set',
            'audit.kinds.changed',
            'audit.kinds.removed',
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

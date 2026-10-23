import { Form, Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/GeneralSettingsController';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import {
    storeGeneralSettings,
    storeSharedProps,
    storeUpload,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Index from './Index.vue';

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
        useHttp: () => ({ processing: false, get: vi.fn(), cancel: vi.fn() }),
        usePage: usePageMock,
        router: { get: vi.fn(), post: vi.fn() },
    };
});

const timezones = ['Africa/Cairo', 'Asia/Damascus', 'Europe/Amsterdam', 'UTC'];
const whiteLogo = storeUpload({ id: 1, name: 'White logo' });
const initialFiles = {
    system_logo_white: [whiteLogo],
    system_logo_black: [],
    admin_login_background: [],
};
function page(permissions = ['general_settings', 'view_uploads']) {
    usePageMock.mockReturnValue({
        url: update.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, {
        props: { settings: storeGeneralSettings(), timezones, initialFiles },
    });
}

describe('general settings page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });
    afterEach(() => {
        i18n.global.locale.value = 'en';
    });

    it.each(['en', 'ar'] as const)(
        'renders the store form shell, every label and hint in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = page();

            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
            expect(
                wrapper.getComponent(ResourceFormPage).props(),
            ).toMatchObject({
                title: t('store.generalSettings.title'),
                action: update(),
                submitLabel: t('store.settings.save'),
                submittingLabel: t('store.settings.saving'),
                layout: StoreAdminLayout,
                wide: false,
            });
            expect(wrapper.getComponent(Head).props('title')).toBe(
                t('store.generalSettings.title'),
            );
            expect(wrapper.getComponent(Form).props('action')).toEqual(
                update(),
            );
            expect(wrapper.getComponent(SubmitButton).props()).toMatchObject({
                label: t('store.settings.save'),
                processingLabel: t('store.settings.saving'),
                processing: false,
            });

            for (const key of [
                'title',
                'siteName',
                'logoWhite',
                'logoWhiteHint',
                'logoBlack',
                'logoBlackHint',
                'loginBackground',
                'timezone',
                'timezoneHint',
            ]) {
                const label = t(`store.generalSettings.${key}`);
                expect(label).not.toBe(`store.generalSettings.${key}`);

                if (key !== 'title') {
                    expect(wrapper.text()).toContain(label);
                }
            }

            expect(wrapper.text()).toContain(t('store.settings.save'));
        },
    );

    it('passes the stored name and every upload field its key, stored id and preview', () => {
        const wrapper = page();

        const form = wrapper.getComponent(Form);

        expect(form.findAllComponents(FormField)).toHaveLength(1);
        expect(form.getComponent(FormField).props()).toMatchObject({
            id: 'site_name',
            label: t('store.generalSettings.siteName'),
            value: 'Al Baraka',
        });
        const name = wrapper.get('#site_name');
        expect(name.attributes('name')).toBe('settings[site_name]');
        expect(name.attributes('maxlength')).toBe('255');
        expect(
            wrapper.findAllComponents(StoreUploadField).map((field) => ({
                ...field.props(),
            })),
        ).toEqual([
            expect.objectContaining({
                id: 'system_logo_white',
                name: 'settings[system_logo_white]',
                label: t('store.generalSettings.logoWhite'),
                type: 'image',
                modelValue: '1',
                initialFiles: [whiteLogo],
                multiple: false,
                required: false,
            }),
            expect.objectContaining({
                id: 'system_logo_black',
                name: 'settings[system_logo_black]',
                label: t('store.generalSettings.logoBlack'),
                type: 'image',
                modelValue: '',
                initialFiles: [],
            }),
            expect.objectContaining({
                id: 'admin_login_background',
                name: 'settings[admin_login_background]',
                label: t('store.generalSettings.loginBackground'),
                type: 'image',
                modelValue: '',
                initialFiles: [],
            }),
        ]);
        expect(
            form
                .findAll('input[type=hidden]')
                .map((input) => [
                    input.attributes('name'),
                    input.attributes('value'),
                ]),
        ).toEqual([
            ['settings[system_logo_white]', '1'],
            ['settings[system_logo_black]', ''],
            ['settings[admin_login_background]', ''],
        ]);
    });

    it('keeps a picked logo in the posted field', async () => {
        const wrapper = page();
        const black = wrapper.findAllComponents(StoreUploadField)[1];

        black.vm.$emit('update:modelValue', '9');
        await wrapper.vm.$nextTick();

        expect(black.props('modelValue')).toBe('9');
        expect(
            wrapper
                .get('input[name="settings[system_logo_black]"]')
                .attributes('value'),
        ).toBe('9');
    });

    it('offers every timezone with the configured one selected and required', async () => {
        const wrapper = page();
        await wrapper.vm.$nextTick();
        const select = wrapper.get('select#timezone');

        expect(select.attributes('name')).toBe('settings[timezone]');
        expect(select.attributes('required')).toBeDefined();
        expect(select.findAll('option').map((option) => option.text())).toEqual(
            timezones,
        );
        expect((select.element as HTMLSelectElement).value).toBe('UTC');
        expect(wrapper.get('label[for=timezone]').text()).toBe(
            `${t('store.generalSettings.timezone')}*${t('common.required')}`,
        );
        expect(
            wrapper
                .get('label[for=timezone]')
                .findComponent(RequiredMark)
                .exists(),
        ).toBe(true);
        expect(wrapper.findAllComponents(RequiredMark)).toHaveLength(1);
        expect(wrapper.findAll('[data-required-legend]')).toHaveLength(1);
        expect(
            wrapper.get('#site_name').attributes('required'),
        ).toBeUndefined();
    });

    it('shows each backend error under its field', () => {
        formSlotPropsMock.mockReturnValue({
            errors: {
                settings: 'Unexpected input',
                'settings.site_name': 'Too long',
                'settings.system_logo_white': 'Not yours',
                'settings.system_logo_black': 'Missing',
                'settings.admin_login_background': 'Not an image',
                'settings.timezone': 'Managed by the server',
            },
            processing: true,
        });
        const wrapper = page();

        expect(
            wrapper
                .findAllComponents(FieldError)
                .map((error) => error.props('message'))
                .filter(Boolean),
        ).toEqual([
            'Unexpected input',
            'Too long',
            'Not yours',
            'Missing',
            'Not an image',
            'Managed by the server',
        ]);
        expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(
            true,
        );
    });

    it('offers the upload library only to a user who may view uploads', () => {
        const choose = t('store.uploads.choose');
        const library = page();
        expect(
            library.findAll('button').filter((b) => b.text() === choose),
        ).toHaveLength(3);

        const own = page(['general_settings']);
        expect(
            own.findAll('button').filter((b) => b.text() === choose),
        ).toHaveLength(0);
        expect(own.findAllComponents(StoreUploadField)).toHaveLength(3);
        expect(own.text()).toContain('White logo');
    });
});

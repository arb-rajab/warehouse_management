import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { update as updateScripts } from '@/actions/App/Http/Controllers/Store/Admin/LegacyWebsiteScriptsController';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteAppearanceController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreWebsiteAppearanceFormFields from '@/components/StoreWebsiteAppearanceFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import {
    paginated,
    storeSharedProps,
    storeUpload,
    storeWebsiteAppearanceSettings,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import { createUseHttpStub } from '@/testing/inertiaStubs';
import Appearance from './Appearance.vue';

const { formSlotPropsMock, usePageMock, useHttpMock } = vi.hoisted(() => ({
    formSlotPropsMock: vi.fn(),
    usePageMock: vi.fn(),
    useHttpMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        usePage: usePageMock,
        useHttp: useHttpMock,
        router: { post: vi.fn() },
    };
});

const scripts = {
    header_script: '<script>track("header")</script>',
    footer_script: '',
};
const initialFiles = {
    site_icon: [storeUpload({ id: 21, name: 'Icon' })],
    login_page_image: [],
    register_page_image: [],
    flash_deal_banner: [],
    flash_deal_banner_small: [],
    meta_image: [],
};
const wrappers: ReturnType<typeof mount>[] = [];

function mountPage(settings = storeWebsiteAppearanceSettings()) {
    const wrapper = mount(Appearance, {
        props: { settings, scripts, initialFiles },
    });
    wrappers.push(wrapper);

    return wrapper;
}

beforeEach(() => {
    vi.clearAllMocks();
    formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    usePageMock.mockReturnValue({
        url: '/temp/admin/website/appearance',
        props: defaultAuthProps({
            store: storeSharedProps({
                permissions: ['website_appearance', 'view_uploads'],
            }),
        }),
    });
    useHttpMock.mockImplementation((data) =>
        createUseHttpStub(data, paginated([])),
    );
});
afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    i18n.global.locale.value = 'en';
});

describe('Website appearance', () => {
    it.each(['en', 'ar'] as const)(
        'uses the store form page with the typed writer and a separate scripts form in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountPage();
            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
            expect(
                wrapper.getComponent(ResourceFormPage).props(),
            ).toMatchObject({
                title: t('store.website.appearance.title'),
                submitLabel: t('store.website.appearance.save'),
                submittingLabel: t('store.website.saving'),
                layout: StoreAdminLayout,
                wide: true,
            });
            const [settingsForm, scriptsForm] = wrapper.findAllComponents(Form);
            expect(settingsForm.props('action')).toEqual(update());
            expect(update().method).toBe('put');
            expect(scriptsForm.props('action')).toEqual(updateScripts());
            expect(updateScripts().method).toBe('post');

            for (const key of ['title', 'hint', 'header', 'footer', 'save']) {
                expect(scriptsForm.text()).toContain(
                    t(`store.website.scripts.${key}`),
                );
            }

            expect(
                wrapper
                    .findAllComponents(SubmitButton)
                    .map((button) => button.text()),
            ).toEqual([
                t('store.website.appearance.save'),
                t('store.website.scripts.save'),
            ]);
        },
    );

    it('passes the fields their props and submits only the appearance settings', async () => {
        const settings = storeWebsiteAppearanceSettings();
        const wrapper = mountPage(settings);
        expect(
            wrapper.getComponent(StoreWebsiteAppearanceFormFields).props(),
        ).toEqual({ modelValue: settings, errors: {}, initialFiles });
        await wrapper.get('#website_name').setValue('Holland Store');
        await wrapper.get('#show_subscribe_form').setValue(true);
        const transform = wrapper
            .findAllComponents(Form)[0]
            .props('transform') as (data: unknown) => unknown;
        expect(
            transform({ header_script: 'forged', types: ['forged'] }),
        ).toEqual({
            settings: {
                ...settings,
                website_name: 'Holland Store',
                show_subscribe_form: true,
            },
        });
        expect(settings.website_name).toBe('Store');
    });

    it('keeps the scripts as raw code in plain text areas, never the editor', async () => {
        const wrapper = mountPage();
        const scriptsForm = wrapper.findAllComponents(Form)[1];
        expect(scriptsForm.findAllComponents(StoreRichTextField)).toHaveLength(
            0,
        );
        expect(
            scriptsForm
                .findAll('input[type="hidden"][name="types[]"]')
                .map((input) => (input.element as HTMLInputElement).value),
        ).toEqual(['header_script', 'footer_script']);
        const header = scriptsForm.get('textarea#header_script');
        expect(header.attributes()).toMatchObject({
            name: 'header_script',
            dir: 'ltr',
            maxlength: '100000',
            spellcheck: 'false',
        });
        expect((header.element as HTMLTextAreaElement).value).toBe(
            '<script>track("header")</script>',
        );
        expect(
            scriptsForm.get('textarea#footer_script').attributes('name'),
        ).toBe('footer_script');
        await header.setValue('<meta name="verify" content="1">');
        expect((header.element as HTMLTextAreaElement).value).toBe(
            '<meta name="verify" content="1">',
        );
        expect(scriptsForm.find('script').exists()).toBe(false);
    });

    it('shows each form its own server errors', async () => {
        formSlotPropsMock.mockReturnValue({
            errors: {
                'settings.base_color': 'Bad colour',
                header_script: 'Script too long',
                types: 'Bad types',
            },
            processing: false,
        });
        const wrapper = mountPage();
        expect(
            wrapper
                .getComponent(StoreWebsiteAppearanceFormFields)
                .props('errors'),
        ).toMatchObject({ 'settings.base_color': 'Bad colour' });
        const scriptsForm = wrapper.findAllComponents(Form)[1];
        expect(scriptsForm.text()).toContain('Script too long');
        expect(scriptsForm.text()).toContain('Bad types');
    });
});

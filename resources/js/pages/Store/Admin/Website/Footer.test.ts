import { Form, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    edit,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/WebsiteFooterController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreLanguageTabs from '@/components/StoreLanguageTabs.vue';
import StoreWebsiteFooterFormFields from '@/components/StoreWebsiteFooterFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import {
    paginated,
    storeSharedProps,
    storeUpload,
    storeWebsiteFooterSettings,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import { createUseHttpStub } from '@/testing/inertiaStubs';
import Footer from './Footer.vue';

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

const initialFiles = {
    footer_logo: [storeUpload({ id: 12, name: 'Footer logo' })],
    payment_method_images: [],
};
const languages = [
    { code: 'en', name: 'English' },
    { code: 'ar', name: 'العربية' },
];
const wrappers: ReturnType<typeof mount>[] = [];

function mountPage(settings = storeWebsiteFooterSettings()) {
    const wrapper = mount(Footer, {
        props: { settings, lang: 'ar', languages, initialFiles },
    });
    wrappers.push(wrapper);

    return wrapper;
}

beforeEach(() => {
    vi.clearAllMocks();
    formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    usePageMock.mockReturnValue({
        url: '/temp/admin/website/footer?lang=ar',
        props: defaultAuthProps({
            store: storeSharedProps({
                permissions: ['footer_setup', 'view_uploads'],
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

describe('Website footer', () => {
    it.each(['en', 'ar'] as const)(
        'uses the store form page with its labels and typed action in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountPage();
            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
            expect(
                wrapper.getComponent(ResourceFormPage).props(),
            ).toMatchObject({
                title: t('store.website.footer.title'),
                submitLabel: t('store.website.footer.save'),
                submittingLabel: t('store.website.saving'),
                layout: StoreAdminLayout,
                wide: true,
            });
            expect(wrapper.getComponent(Form).props('action')).toEqual(
                update(),
            );
            expect(update().method).toBe('put');
            expect(wrapper.get('h1').text()).toBe(
                t('store.website.footer.title'),
            );
            expect(wrapper.getComponent(SubmitButton).text()).toBe(
                t('store.website.footer.save'),
            );
        },
    );

    it('switches language through the footer route and passes the fields their props', () => {
        const settings = storeWebsiteFooterSettings();
        const wrapper = mountPage(settings);
        expect(wrapper.getComponent(StoreLanguageTabs).props('lang')).toBe(
            'ar',
        );
        expect(
            wrapper
                .findAllComponents(Link)
                .filter((link) => link.text() === 'English')
                .map((link) => link.props('href')),
        ).toEqual([edit({ query: { lang: 'en' } })]);
        const fields = wrapper.getComponent(StoreWebsiteFooterFormFields);
        expect(fields.props()).toEqual({
            modelValue: settings,
            errors: {},
            initialFiles,
        });
        expect(fields.props('modelValue')).not.toBe(settings);
    });

    it('submits the complete edited settings with the edited language and leaves the props untouched', async () => {
        const settings = storeWebsiteFooterSettings();
        const wrapper = mountPage(settings);
        await wrapper.get('#contact_phone').setValue('+31 6 0000 0000');
        await wrapper.get('#show_social_links').setValue(false);
        await wrapper.get('#footer-link-label-0').setValue('Support');
        const transform = wrapper.getComponent(Form).props('transform') as (
            data: unknown,
        ) => unknown;
        expect(transform({ types: ['forged'] })).toEqual({
            settings: {
                ...settings,
                contact_phone: '+31 6 0000 0000',
                show_social_links: false,
                widget_one_labels: ['Support'],
            },
            lang: 'ar',
        });
        expect(settings.widget_one_labels).toEqual(['Help']);
        expect(settings.contact_phone).toBe('+31 20 123 4567');
    });

    it('forwards server errors and the processing state', async () => {
        const wrapper = mountPage();
        formSlotPropsMock.mockReturnValue({
            errors: { 'settings.contact_email': 'Invalid email' },
            processing: true,
        });
        await wrapper.setProps({ lang: 'ar' });
        await wrapper.setProps({ settings: storeWebsiteFooterSettings() });
        expect(
            wrapper.getComponent(StoreWebsiteFooterFormFields).props('errors'),
        ).toEqual({ 'settings.contact_email': 'Invalid email' });
        expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(
            true,
        );
        expect(wrapper.text()).toContain('Invalid email');
    });
});

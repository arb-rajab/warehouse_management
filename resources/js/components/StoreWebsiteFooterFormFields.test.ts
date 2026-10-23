import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import FieldError from '@/components/FieldError.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { i18n, t } from '@/lib/i18n';
import {
    paginated,
    storeSharedProps,
    storeUpload,
    storeWebsiteFooterSettings,
} from '@/testing/factories';
import { createUseHttpStub } from '@/testing/inertiaStubs';
import type { StoreWebsiteFooterSettings } from '@/types/store';
import Fields from './StoreWebsiteFooterFormFields.vue';

const { usePageMock, useHttpMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    useHttpMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub } = await import('@/testing/inertiaStubs');

    return {
        usePage: usePageMock,
        useHttp: useHttpMock,
        Link: createLinkStub(),
        router: { post: vi.fn() },
    };
});

const logo = storeUpload({ id: 12, name: 'Footer logo' });
const visa = storeUpload({ id: 13, name: 'Visa' });
const wrappers: VueWrapper[] = [];

function mountFields(
    overrides: Partial<StoreWebsiteFooterSettings> = {},
    errors: Record<string, string> = {},
) {
    const settings = reactive(storeWebsiteFooterSettings(overrides));
    const wrapper = mount(Fields, {
        props: {
            modelValue: settings,
            errors,
            initialFiles: {
                footer_logo: [logo],
                payment_method_images: [visa],
            },
        },
    });
    wrappers.push(wrapper);

    return { wrapper, settings };
}

beforeEach(() => {
    vi.clearAllMocks();
    usePageMock.mockReturnValue({
        props: { store: storeSharedProps({ permissions: ['view_uploads'] }) },
    });
    useHttpMock.mockImplementation((data) =>
        createUseHttpStub(data, paginated([])),
    );
});
afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    i18n.global.locale.value = 'en';
});

describe('StoreWebsiteFooterFormFields', () => {
    it('marks each link row and leaves nullable settings and boolean switches unmarked', () => {
        const { wrapper } = mountFields();

        for (const id of ['footer-link-label-0', 'footer-link-url-0']) {
            expect(wrapper.get(`#${id}`).attributes('required')).toBeDefined();
            expect(
                wrapper
                    .get(`label[for=${id}]`)
                    .findComponent(RequiredMark)
                    .exists(),
            ).toBe(true);
        }

        expect(wrapper.findAllComponents(RequiredMark)).toHaveLength(2);
        expect(wrapper.findAll('input[type=checkbox][required]')).toHaveLength(
            0,
        );
        expect(
            wrapper.get('#contact_email').attributes('required'),
        ).toBeUndefined();

        for (const field of wrapper.findAllComponents(StoreRichTextField)) {
            expect(field.props('required')).toBe(false);
        }
    });
    it.each(['en', 'ar'] as const)(
        'labels every section and field in %s',
        async (locale) => {
            i18n.global.locale.value = locale;
            const { wrapper } = mountFields({
                seller_app_link: '',
                delivery_boy_app_link: '',
            });
            await flushPromises();

            for (const key of [
                'translatableHint',
                'about',
                'contact',
                'links',
                'linkLabel',
                'linkUrl',
                'addLink',
                'bottom',
                'social',
                'apps',
                'payment',
                'tutorial',
            ]) {
                expect(wrapper.text()).toContain(
                    t(`store.website.footer.${key}`),
                );
            }

            for (const key of [
                'footer_logo',
                'about_us_description',
                'play_store_link',
                'app_store_link',
                'contact_address',
                'contact_phone',
                'contact_email',
                'widget_one',
                'frontend_copyright_text',
                'show_social_links',
                'facebook_link',
                'twitter_link',
                'instagram_link',
                'youtube_link',
                'linkedin_link',
                'seller_app_link',
                'delivery_boy_app_link',
                'registration_tutorial_button',
                'registration_tutorial_text',
                'registration_tutorial_link',
            ]) {
                expect(wrapper.text()).toContain(
                    t(`store.website.fields.${key}`),
                );
            }

            expect(
                wrapper
                    .find(
                        `button[aria-label="${t('store.website.footer.removeLink', { row: 1 })}"]`,
                    )
                    .exists(),
            ).toBe(true);
        },
    );

    it('passes the canonical uploader and the shared editor their fields', () => {
        const { wrapper, settings } = mountFields();
        const [logoField, paymentField] =
            wrapper.findAllComponents(StoreUploadField);
        expect(logoField.props()).toMatchObject({
            id: 'footer_logo',
            name: 'settings[footer_logo]',
            label: t('store.website.fields.footer_logo'),
            type: 'image',
            multiple: false,
            modelValue: '12',
            initialFiles: [logo],
        });
        expect(paymentField.props()).toMatchObject({
            id: 'payment_method_images',
            name: 'settings[payment_method_images]',
            label: t('store.website.footer.payment'),
            type: 'image',
            multiple: true,
            modelValue: '',
            initialFiles: [visa],
        });
        expect(
            wrapper
                .findAllComponents(StoreRichTextField)
                .map((field) => field.props()),
        ).toEqual([
            {
                id: 'about_us_description',
                name: 'settings[about_us_description]',
                label: t('store.website.fields.about_us_description'),
                modelValue: settings.about_us_description,
                required: false,
                error: undefined,
            },
            {
                id: 'frontend_copyright_text',
                name: 'settings[frontend_copyright_text]',
                label: t('store.website.fields.frontend_copyright_text'),
                modelValue: settings.frontend_copyright_text,
                required: false,
                error: undefined,
            },
        ]);
        logoField.vm.$emit('update:modelValue', '44');
        paymentField.vm.$emit('update:modelValue', '45,46');
        expect(settings.footer_logo).toBe('44');
        expect(settings.payment_method_images).toBe('45,46');
    });

    it('mirrors the request rules with native constraints', () => {
        const { wrapper } = mountFields({ seller_app_link: '' });
        const linkPattern = '(?:[Hh][Tt][Tt][Pp][Ss]?://\\S+|/(?!/)\\S*|#\\S*)';

        for (const id of [
            'play_store_link',
            'app_store_link',
            'facebook_link',
            'twitter_link',
            'instagram_link',
            'youtube_link',
            'linkedin_link',
            'seller_app_link',
            'registration_tutorial_link',
            'footer-link-url-0',
        ]) {
            expect(wrapper.get(`#${id}`).attributes()).toMatchObject({
                maxlength: '2048',
                pattern: linkPattern,
            });
        }

        for (const id of [
            'contact_address',
            'contact_phone',
            'contact_email',
            'widget_one',
            'registration_tutorial_text',
            'footer-link-label-0',
        ]) {
            expect(wrapper.get(`#${id}`).attributes('maxlength')).toBe('255');
        }

        expect(wrapper.get('#contact_email').attributes('type')).toBe('email');
        expect(wrapper.get('#footer-link-label-0').attributes()).toHaveProperty(
            'required',
        );
        expect(wrapper.get('#footer-link-url-0').attributes()).toHaveProperty(
            'required',
        );
    });

    it('edits texts, switches and paired link rows in the model', async () => {
        const { wrapper, settings } = mountFields();
        await wrapper.get('#contact_phone').setValue('+31 6 1234 5678');
        await wrapper.get('#show_social_links').setValue(false);
        await wrapper.get('#registration_tutorial_button').setValue(true);
        await wrapper
            .findAll('button')
            .find(
                (button) => button.text() === t('store.website.footer.addLink'),
            )!
            .trigger('click');
        await wrapper.get('#footer-link-label-1').setValue('Terms');
        await wrapper.get('#footer-link-url-1').setValue('/terms');
        await wrapper
            .get(
                `button[aria-label="${t('store.website.footer.removeLink', { row: 1 })}"]`,
            )
            .trigger('click');

        expect(settings.contact_phone).toBe('+31 6 1234 5678');
        expect(settings.show_social_links).toBe(false);
        expect(settings.registration_tutorial_button).toBe(true);
        expect(settings.widget_one_labels).toEqual(['Terms']);
        expect(settings.widget_one_links).toEqual(['/terms']);
    });

    it('limits the link rows to the request maximum', () => {
        const { wrapper } = mountFields({
            widget_one_labels: Array(50).fill('Label'),
            widget_one_links: Array(50).fill('/link'),
        });
        const add = wrapper
            .findAll('button')
            .find(
                (button) => button.text() === t('store.website.footer.addLink'),
            )!;
        expect(add.attributes()).toHaveProperty('disabled');
    });

    it('shows the app links only for the keys the server sends', () => {
        expect(mountFields().wrapper.find('#seller_app_link').exists()).toBe(
            false,
        );
        expect(mountFields().wrapper.text()).not.toContain(
            t('store.website.footer.apps'),
        );
        const { wrapper } = mountFields({
            delivery_boy_app_link: 'https://example.com/delivery',
        });
        expect(wrapper.find('#seller_app_link').exists()).toBe(false);
        expect(
            (wrapper.get('#delivery_boy_app_link').element as HTMLInputElement)
                .value,
        ).toBe('https://example.com/delivery');
    });

    it('shows every server error beside its field', () => {
        const errors = {
            settings: 'Unexpected settings',
            'settings.footer_logo': 'Bad logo',
            'settings.about_us_description': 'Bad about',
            'settings.contact_email': 'Bad email',
            'settings.widget_one_labels.0': 'Bad label',
            'settings.widget_one_links.0': 'Bad link',
            'settings.widget_one_links': 'Unpaired links',
            'settings.show_social_links': 'Bad switch',
            'settings.payment_method_images': 'Bad payment images',
        };
        const { wrapper } = mountFields({}, errors);
        const messages = wrapper
            .findAllComponents(FieldError)
            .map((error) => error.props('message'))
            .filter(Boolean);

        for (const message of Object.values(errors)) {
            expect(messages).toContain(message);
        }
    });
});

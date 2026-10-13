import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import FieldError from '@/components/FieldError.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { i18n, t } from '@/lib/i18n';
import {
    paginated,
    storeSharedProps,
    storeUpload,
    storeWebsiteAppearanceSettings,
} from '@/testing/factories';
import { createUseHttpStub } from '@/testing/inertiaStubs';
import type { StoreWebsiteAppearanceSettings } from '@/types/store';
import Fields from './StoreWebsiteAppearanceFormFields.vue';

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

const icon = storeUpload({ id: 21, name: 'Icon' });
const wrappers: VueWrapper[] = [];

function mountFields(
    overrides: Partial<StoreWebsiteAppearanceSettings> = {},
    errors: Record<string, string> = {},
) {
    const settings = reactive(storeWebsiteAppearanceSettings(overrides));
    const wrapper = mount(Fields, {
        props: {
            modelValue: settings,
            errors,
            initialFiles: {
                site_icon: [icon],
                login_page_image: [],
                register_page_image: [],
                flash_deal_banner: [],
                flash_deal_banner_small: [],
                meta_image: [],
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

describe('StoreWebsiteAppearanceFormFields', () => {
    it.each(['en', 'ar'] as const)(
        'labels every section and field in %s',
        async (locale) => {
            i18n.global.locale.value = locale;
            const { wrapper } = mountFields({
                seller_login_page_bg: '',
                delivery_boy_login_page_bg: '',
            });
            await flushPromises();

            for (const key of [
                'general',
                'siteIconHint',
                'colorHint',
                'seo',
                'keywordsHint',
                'cookies',
                'popup',
            ]) {
                expect(wrapper.text()).toContain(
                    t(`store.website.appearance.${key}`),
                );
            }

            for (const key of [
                'website_name',
                'site_motto',
                'site_icon',
                'base_color',
                'base_hov_color',
                'login_page_image',
                'register_page_image',
                'seller_login_page_bg',
                'delivery_boy_login_page_bg',
                'flash_deal_banner',
                'flash_deal_banner_small',
                'meta_title',
                'meta_description',
                'meta_keywords',
                'meta_image',
                'cookies_agreement_text',
                'show_cookies_agreement',
                'show_website_popup',
                'website_popup_content',
                'show_subscribe_form',
            ]) {
                expect(wrapper.text()).toContain(
                    t(`store.website.fields.${key}`),
                );
            }
        },
    );

    it('passes the canonical uploader every image and the shared editor its two texts', () => {
        const { wrapper, settings } = mountFields({ site_icon: '21' });
        expect(
            wrapper
                .findAllComponents(StoreUploadField)
                .map((field) => [
                    field.props('id'),
                    field.props('name'),
                    field.props('type'),
                    field.props('multiple'),
                    field.props('modelValue'),
                    field.props('initialFiles'),
                ]),
        ).toEqual([
            ['site_icon', 'settings[site_icon]', 'image', false, '21', [icon]],
            [
                'login_page_image',
                'settings[login_page_image]',
                'image',
                false,
                '',
                [],
            ],
            [
                'register_page_image',
                'settings[register_page_image]',
                'image',
                false,
                '',
                [],
            ],
            [
                'flash_deal_banner',
                'settings[flash_deal_banner]',
                'image',
                false,
                '',
                [],
            ],
            [
                'flash_deal_banner_small',
                'settings[flash_deal_banner_small]',
                'image',
                false,
                '',
                [],
            ],
            ['meta_image', 'settings[meta_image]', 'image', false, '', []],
        ]);
        expect(
            wrapper
                .findAllComponents(StoreRichTextField)
                .map((field) => field.props()),
        ).toEqual([
            {
                id: 'cookies_agreement_text',
                name: 'settings[cookies_agreement_text]',
                label: t('store.website.fields.cookies_agreement_text'),
                modelValue: settings.cookies_agreement_text,
                required: false,
                error: undefined,
            },
            {
                id: 'website_popup_content',
                name: 'settings[website_popup_content]',
                label: t('store.website.fields.website_popup_content'),
                modelValue: settings.website_popup_content,
                required: false,
                error: undefined,
            },
        ]);
        wrapper
            .findAllComponents(StoreUploadField)[1]
            .vm.$emit('update:modelValue', '30');
        expect(settings.login_page_image).toBe('30');
    });

    it('offers the seller and delivery boy backgrounds only for the keys the server sends', () => {
        const ids = (overrides: Partial<StoreWebsiteAppearanceSettings>) =>
            mountFields(overrides)
                .wrapper.findAllComponents(StoreUploadField)
                .map((field) => field.props('id'));
        expect(ids({})).not.toContain('seller_login_page_bg');
        expect(ids({ seller_login_page_bg: '7' })).toContain(
            'seller_login_page_bg',
        );
        expect(ids({ delivery_boy_login_page_bg: '' })).toContain(
            'delivery_boy_login_page_bg',
        );
    });

    it('mirrors the request rules with native constraints', () => {
        const { wrapper } = mountFields();

        for (const id of ['website_name', 'site_motto', 'meta_title']) {
            expect(wrapper.get(`#${id}`).attributes('maxlength')).toBe('255');
        }

        for (const id of ['meta_description', 'meta_keywords']) {
            expect(wrapper.get(`#${id}`).attributes()).toMatchObject({
                name: `settings[${id}]`,
                maxlength: '65535',
            });
        }

        for (const id of ['base_color', 'base_hov_color']) {
            expect(wrapper.get(`#${id}`).attributes('pattern')).toBe(
                '#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})',
            );
        }
    });

    it('edits texts, colours and switches in the model', async () => {
        const { wrapper, settings } = mountFields();
        await wrapper.get('#website_name').setValue('Holland Store');
        await wrapper.get('#base_color').setValue('#abcdef');
        await wrapper.get('#meta_keywords').setValue('dates, rice');
        await wrapper.get('#show_cookies_agreement').setValue(false);
        await wrapper.get('#show_website_popup').setValue(true);
        await wrapper.get('#show_subscribe_form').setValue(true);
        expect(settings).toMatchObject({
            website_name: 'Holland Store',
            base_color: '#abcdef',
            meta_keywords: 'dates, rice',
            show_cookies_agreement: false,
            show_website_popup: true,
            show_subscribe_form: true,
        });
    });

    it('shows every server error beside its field', () => {
        const errors = {
            settings: 'Unexpected settings',
            'settings.website_name': 'Bad name',
            'settings.base_color': 'Bad colour',
            'settings.site_icon': 'Bad icon',
            'settings.meta_description': 'Bad description',
            'settings.cookies_agreement_text': 'Bad cookies',
            'settings.show_website_popup': 'Bad switch',
            'settings.meta_image': 'Bad meta image',
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

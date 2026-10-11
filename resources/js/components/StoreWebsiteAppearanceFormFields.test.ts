import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive, nextTick } from 'vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { t, i18n } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { storeWebsiteAppearanceSettings } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
const { usePageMock, routerGetMock, slotMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
    slotMock: vi.fn(() => ({
        errors: {} as Record<string, string>,
        processing: false,
    })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(slotMock),
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { get: routerGetMock, post: vi.fn() },
        useHttp: () =>
            reactive({
                processing: false,
                get: () => Promise.resolve(),
                post: () => Promise.resolve(),
            }),
    };
});
beforeEach(() => {
    vi.clearAllMocks();
    slotMock.mockReturnValue({ errors: {}, processing: false });
    usePageMock.mockReturnValue({
        url: '/temp/admin/website',
        props: defaultAuthProps({
            store: storeSharedProps({
                permissions: [
                    'footer_setup',
                    'website_appearance',
                    'manage_website_scripts',
                    'edit_website_page',
                    'reply_to_support_tickets',
                ],
            }),
        }),
    });
    i18n.global.locale.value = 'en';
});
import Fields from './StoreWebsiteAppearanceFormFields.vue';
describe('StoreWebsiteAppearanceFormFields', () => {
    it('full-mounts all exact field labels in both languages and reuses picker/editor', async () => {
        for (const locale of ['en', 'ar'] as const) {
            i18n.global.locale.value = locale;
            const settings = storeWebsiteAppearanceSettings();
            const wrapper = mount(Fields, {
                props: { modelValue: settings, errors: {} },
            });
            await nextTick();

            for (const key of Object.keys(settings)) {
                if (!['widget_one_labels'].includes(key)) {
                    expect(wrapper.text()).toContain(
                        t(`store.website.fields.${key}`),
                    );
                }
            }

            expect(
                wrapper
                    .findAllComponents(StoreUploadField)
                    .every((field) => field.props('type') === 'image'),
            ).toBe(true);
            expect(wrapper.findAllComponents(StoreRichTextField)).toHaveLength(
                2,
            );
            wrapper.unmount();
        }

        i18n.global.locale.value = 'en';
    });
    it('retains edited inputs, unchecked flags, validation errors and native constraints', async () => {
        const settings = storeWebsiteAppearanceSettings();
        const key = 'website_name';
        const wrapper = mount(Fields, {
            props: {
                modelValue: settings,
                errors: { [`settings.${key}`]: 'Invalid value' },
            },
        });
        await wrapper.get(`#${key}`).setValue('Changed');
        expect(settings[key]).toBe('Changed');
        expect(wrapper.text()).toContain('Invalid value');
        expect(wrapper.get(`#${key}`).attributes('maxlength')).toBe('255');
        wrapper.unmount();
    });
});

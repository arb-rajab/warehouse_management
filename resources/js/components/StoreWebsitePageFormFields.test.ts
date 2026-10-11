import { mount } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import { reactive, nextTick } from 'vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { t, i18n } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { storeWebsitePageForm } from '@/testing/factories';
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
import Fields from './StoreWebsitePageFormFields.vue';
it('full-mounts CMS fields with safe rich text image picker and mirrored constraints', async () => {
    const wrapper = mount(Fields, {
        props: {
            modelValue: storeWebsitePageForm(),
            errors: { content: 'Required' },
            builtIn: true,
        },
    });
    await nextTick();
    expect(wrapper.get('#slug').attributes()).toMatchObject({
        readonly: '',
        required: '',
        maxlength: '255',
        pattern: '[a-zA-Z0-9]+(-[a-zA-Z0-9]+)*',
    });
    expect(wrapper.getComponent(StoreRichTextField).props()).toMatchObject({
        name: 'content',
        required: true,
        maxlength: 100000,
    });
    expect(wrapper.getComponent(StoreUploadField).props()).toMatchObject({
        type: 'image',
        name: 'meta_image',
    });
    expect(wrapper.text()).toContain('Required');

    for (const key of [
        'title',
        'slug',
        'content',
        'meta_title',
        'meta_description',
        'keywords',
        'meta_image',
    ]) {
        expect(wrapper.text()).toContain(t(`store.website.fields.${key}`));
    }

    wrapper.unmount();
});

import { EditorContent } from '@tiptap/vue-3';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import StoreFileManagerDialog from '@/components/StoreFileManagerDialog.vue';
import { t, i18n } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { storeUpload } from '@/testing/factories';
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
import StoreRichTextField from './StoreRichTextField.vue';
describe('StoreRichTextField', () => {
    it('mounts the real approved editor and reflects server constraints in its form textarea', async () => {
        const wrapper = mount(StoreRichTextField, {
            props: {
                id: 'reply',
                name: 'reply',
                label: 'Reply',
                modelValue: '<p>Hello</p>',
                required: true,
                maxlength: 10000,
                basic: true,
            },
        });
        await flushPromises();
        expect(wrapper.get('.ProseMirror').text()).toBe('Hello');
        expect(wrapper.get('textarea').attributes()).toMatchObject({
            name: 'reply',
            required: '',
            maxlength: '10000',
        });
        expect(wrapper.findComponent(StoreFileManagerDialog).exists()).toBe(
            false,
        );
        wrapper.unmount();
    });
    it('applies bold and list interactions and emits HTML using the real editor', async () => {
        const wrapper = mount(StoreRichTextField, {
            props: {
                id: 'content',
                name: 'content',
                label: 'Content',
                modelValue: '<p>Hello</p>',
                basic: true,
            },
        });
        await flushPromises();
        const editor = wrapper.getComponent(EditorContent).props('editor');

        if (!editor) {
            throw new Error('Editor must mount');
        }

        editor.commands.setTextSelection({ from: 1, to: 6 });
        await wrapper.get('button[data-action="bold"]').trigger('click');
        await flushPromises();
        expect(wrapper.emitted('update:modelValue')?.at(-1)?.[0]).toContain(
            '<strong>Hello</strong>',
        );
        await wrapper.get('button[data-action="bulletList"]').trigger('click');
        await flushPromises();
        expect(wrapper.emitted('update:modelValue')?.at(-1)?.[0]).toContain(
            '<ul>',
        );
        wrapper.unmount();
    });
    it('retains source input and sanitizes its editor preview without executing HTML', async () => {
        const wrapper = mount(StoreRichTextField, {
            props: {
                id: 'content',
                name: 'content',
                label: 'Content',
                modelValue: '<p>Keep</p>',
            },
        });
        await flushPromises();
        const sourceButton = wrapper
            .findAll('button')
            .find((button) => button.text() === t('store.editor.source'))!;
        await sourceButton.trigger('click');
        await wrapper
            .get('textarea')
            .setValue(
                '<p>Changed</p><img src="javascript:attack()" onerror="attack"><script>attack</script>',
            );
        await wrapper.setProps({
            modelValue: wrapper
                .emitted('update:modelValue')!
                .at(-1)![0] as string,
        });
        await sourceButton.trigger('click');
        expect(wrapper.get('.ProseMirror').text()).toContain('Changed');
        expect(wrapper.get('.ProseMirror').html()).not.toMatch(
            /attack|onerror|script/,
        );
        wrapper.unmount();
    });
    it('reuses the image-only picker and inserts the selected safe upload URL', async () => {
        const wrapper = mount(StoreRichTextField, {
            props: {
                id: 'content',
                name: 'content',
                label: 'Content',
                modelValue: '<p>Hello</p>',
            },
        });
        await flushPromises();
        const dialog = wrapper.getComponent(StoreFileManagerDialog);
        expect(dialog.props()).toMatchObject({
            type: 'image',
            multiple: true,
            selected: [],
        });
        dialog.vm.$emit('select', [
            storeUpload({ url: '/uploads/all/selected.png', name: 'Selected' }),
        ]);
        await flushPromises();
        expect(wrapper.get('.ProseMirror img').attributes('src')).toBe(
            '/uploads/all/selected.png',
        );
        wrapper.unmount();
    });
    it('translates its controls and supports source/fullscreen state in Arabic', async () => {
        i18n.global.locale.value = 'ar';
        const wrapper = mount(StoreRichTextField, {
            props: {
                id: 'content',
                name: 'content',
                label: 'المحتوى',
                modelValue: '',
            },
        });
        await flushPromises();
        expect(
            wrapper.get('button[data-action="bold"]').attributes('aria-label'),
        ).toBe(t('store.editor.bold'));
        await wrapper
            .findAll('button')
            .find((button) => button.text() === t('store.editor.fullscreen'))!
            .trigger('click');
        expect(wrapper.classes()).toContain('fixed');
        wrapper.unmount();
        i18n.global.locale.value = 'en';
    });
});

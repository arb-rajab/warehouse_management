import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive, nextTick } from 'vue';
import { reply } from '@/actions/App/Http/Controllers/Store/Admin/SupportTicketController';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { i18n } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
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
import StoreTicketReplyForm from './StoreTicketReplyForm.vue';
describe('rich ticket reply form', () => {
    it('requires the exact reply permission and reuses image-only attachments and approved editor', async () => {
        usePageMock.mockReturnValue({
            props: defaultAuthProps({
                store: storeSharedProps({
                    permissions: ['view_all_support_tickets'],
                }),
            }),
        });
        const refused = mount(StoreTicketReplyForm, {
            props: { ticket: { id: 1, status: 'pending' } },
        });
        expect(refused.find('form').exists()).toBe(false);
        usePageMock.mockReturnValue({
            props: defaultAuthProps({
                store: storeSharedProps({
                    permissions: ['reply_to_support_tickets'],
                }),
            }),
        });
        const wrapper = mount(StoreTicketReplyForm, {
            props: { ticket: { id: 1, status: 'pending' } },
        });
        await nextTick();
        expect(wrapper.getComponent(Form).props('action')).toEqual(reply());
        expect(wrapper.getComponent(StoreRichTextField).props()).toMatchObject({
            name: 'reply',
            required: true,
            maxlength: 10000,
            basic: true,
        });
        expect(wrapper.getComponent(StoreUploadField).props()).toMatchObject({
            name: 'attachments',
            multiple: true,
            type: 'image',
        });
        wrapper.unmount();
    });
    it('submits only the selected ticket, reply, files and status; resets after success and retains on errors', async () => {
        slotMock.mockReturnValue({
            errors: { reply: 'Invalid reply' },
            processing: true,
        });
        const wrapper = mount(StoreTicketReplyForm, {
            props: { ticket: { id: 7, status: 'pending' } },
        });
        await nextTick();
        wrapper
            .getComponent(StoreRichTextField)
            .vm.$emit('update:modelValue', '<p>Typed reply</p>');
        wrapper
            .getComponent(StoreUploadField)
            .vm.$emit('update:modelValue', '3,4');
        await wrapper.get('select').setValue('solved');
        const form = wrapper.getComponent(Form);
        const transform = form.props('transform') as (data: unknown) => unknown;
        expect(transform({ user_id: 999, ticket_id: 999 })).toEqual({
            ticket_id: 7,
            reply: '<p>Typed reply</p>',
            attachments: '3,4',
            status: 'solved',
        });
        expect(wrapper.text()).toContain('Invalid reply');
        expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(
            true,
        );
        form.vm.$emit('success');
        await nextTick();
        expect(transform({})).toEqual({
            ticket_id: 7,
            reply: '',
            attachments: '',
            status: 'solved',
        });
        wrapper.unmount();
    });
});

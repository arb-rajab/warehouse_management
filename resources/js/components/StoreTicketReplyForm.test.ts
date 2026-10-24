import { Form } from '@inertiajs/vue3';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { reply } from '@/actions/App/Http/Controllers/Store/Admin/SupportTicketController';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { i18n, t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import { createUseHttpStub } from '@/testing/inertiaStubs';
import StoreTicketReplyForm from './StoreTicketReplyForm.vue';

const { usePageMock, useHttpMock, formSlotPropsMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    useHttpMock: vi.fn(),
    formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub } =
        await import('@/testing/inertiaStubs');

    return {
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        usePage: usePageMock,
        useHttp: useHttpMock,
    };
});

function mountForm(status = 'open') {
    return mount(StoreTicketReplyForm, {
        props: { ticket: { id: 45, status } },
        attachTo: document.body,
    });
}

describe('StoreTicketReplyForm', () => {
    beforeEach(() => {
        usePageMock.mockReturnValue({
            url: '/temp/admin/support_ticket/45/show',
            props: defaultAuthProps({
                store: storeSharedProps({
                    permissions: ['reply_to_support_tickets', 'view_uploads'],
                }),
            }),
        });
        useHttpMock.mockImplementation((data) => createUseHttpStub(data, []));
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });
    afterEach(() => {
        i18n.global.locale.value = 'en';
        document.body.innerHTML = '';
    });

    it.each(['en', 'ar'] as const)(
        'posts the editor, image picker, ticket and status to the typed reply endpoint in %s',
        async (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountForm();
            await flushPromises();

            expect(wrapper.getComponent(Form).props()).toMatchObject({
                action: reply(),
                options: { preserveScroll: true },
            });
            expect(
                wrapper.get('input[type="hidden"][name="ticket_id"]').element,
            ).toHaveProperty('value', '45');
            expect(
                wrapper.getComponent(StoreRichTextField).props(),
            ).toMatchObject({
                id: 'reply',
                name: 'reply',
                label: t('store.support.reply'),
                required: true,
                modelValue: '',
            });
            expect(wrapper.get('#reply-label').text()).toBe(
                t('store.support.reply') + '*' + t('common.required'),
            );
            expect(
                wrapper.getComponent(StoreUploadField).props(),
            ).toMatchObject({
                id: 'attachments',
                name: 'attachments',
                label: t('store.support.attachments'),
                type: 'image',
                multiple: true,
                required: false,
                modelValue: '',
            });
            expect(wrapper.get('label[for="status"]').text()).toBe(
                t('store.support.replyStatus') + '*' + t('common.required'),
            );
            expect(wrapper.text()).toContain(t('common.requiredLegend'));
            const select = wrapper.get('#status');
            expect(select.attributes()).toMatchObject({
                name: 'status',
                required: '',
            });
            expect(
                select
                    .findAll('option')
                    .map((option) => [
                        option.attributes('value'),
                        option.text(),
                    ]),
            ).toEqual([
                ['pending', t('store.support.pending')],
                ['open', t('store.support.open')],
                ['solved', t('store.support.solved')],
            ]);
            expect((select.element as HTMLSelectElement).value).toBe('open');
            expect(wrapper.getComponent(SubmitButton).text()).toBe(
                t('store.support.send'),
            );
            wrapper.unmount();
        },
    );

    it('starts a ticket with an unknown status at pending', async () => {
        const wrapper = mountForm('closed');
        await flushPromises();
        expect(
            (wrapper.get('#status').element as HTMLSelectElement).value,
        ).toBe('pending');
        await wrapper.get('#status').setValue('solved');
        expect(
            (wrapper.get('#status').element as HTMLSelectElement).value,
        ).toBe('solved');
        wrapper.unmount();
    });

    it('shows each field error and the processing label', async () => {
        formSlotPropsMock.mockReturnValue({
            errors: {
                reply: 'Reply required',
                attachments: 'Not your file',
                status: 'Bad status',
                ticket_id: 'Unknown ticket',
            },
            processing: true,
        });
        const wrapper = mountForm();
        await flushPromises();
        expect(wrapper.get('#reply-error').text()).toBe('Reply required');
        expect(wrapper.getComponent(StoreUploadField).props('error')).toBe(
            'Not your file',
        );

        for (const message of [
            'Not your file',
            'Bad status',
            'Unknown ticket',
        ]) {
            expect(wrapper.text()).toContain(message);
        }

        expect(wrapper.getComponent(SubmitButton).text()).toBe(
            t('store.support.sending'),
        );
        wrapper.unmount();
    });

    it('clears the reply and attachments after a successful send', async () => {
        const wrapper = mountForm();
        await flushPromises();
        wrapper
            .getComponent(StoreRichTextField)
            .vm.$emit('update:modelValue', '<p>Draft</p>');
        wrapper
            .getComponent(StoreUploadField)
            .vm.$emit('update:modelValue', '7');
        await flushPromises();
        expect(
            wrapper.getComponent(StoreRichTextField).props('modelValue'),
        ).toBe('<p>Draft</p>');
        expect(wrapper.getComponent(StoreUploadField).props('modelValue')).toBe(
            '7',
        );
        wrapper.getComponent(Form).vm.$emit('success');
        await flushPromises();
        expect(
            wrapper.getComponent(StoreRichTextField).props('modelValue'),
        ).toBe('');
        expect(wrapper.getComponent(StoreUploadField).props('modelValue')).toBe(
            '',
        );
        wrapper.unmount();
    });
});

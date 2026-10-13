import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import FormField from '@/components/FormField.vue';
import StoreNewsletterRecipients from '@/components/StoreNewsletterRecipients.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import { i18n, t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import { createUseHttpStub } from '@/testing/inertiaStubs';
import StoreNewsletterFormFields from './StoreNewsletterFormFields.vue';

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
    };
});

function mountFields(errors: Record<string, string> = {}) {
    return mount(StoreNewsletterFormFields, {
        props: { errors },
        attachTo: document.body,
    });
}

describe('StoreNewsletterFormFields', () => {
    beforeEach(() => {
        usePageMock.mockReturnValue({
            url: '/temp/admin/newsletter',
            props: defaultAuthProps({
                store: storeSharedProps({ permissions: ['send_newsletter'] }),
            }),
        });
        useHttpMock.mockImplementation((data) => createUseHttpStub(data, []));
    });
    afterEach(() => {
        i18n.global.locale.value = 'en';
        document.body.innerHTML = '';
    });

    it.each(['en', 'ar'] as const)(
        'full mounts the recipients, subject and the shared editor in %s',
        async (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountFields();
            await flushPromises();

            expect(wrapper.text()).toContain(
                t('store.newsletters.recipientHelp'),
            );
            expect(
                wrapper
                    .findAllComponents(StoreNewsletterRecipients)
                    .map((child) => child.props()),
            ).toEqual([
                {
                    kind: 'users',
                    required: true,
                    errors: {},
                    modelValue: [],
                },
                {
                    kind: 'subscribers',
                    required: true,
                    errors: {},
                    modelValue: [],
                },
            ]);
            expect(wrapper.getComponent(FormField).props()).toMatchObject({
                id: 'subject',
                label: t('store.newsletters.subject'),
            });
            expect(wrapper.get('label[for="subject"]').text()).toBe(
                t('store.newsletters.subject'),
            );
            expect(wrapper.get('#subject').attributes()).toMatchObject({
                name: 'subject',
                required: '',
                maxlength: '255',
            });
            expect(
                wrapper.getComponent(StoreRichTextField).props(),
            ).toMatchObject({
                id: 'content',
                name: 'content',
                label: t('store.newsletters.content'),
                required: true,
                modelValue: '',
            });
            expect(wrapper.get('#content-label').text()).toBe(
                t('store.newsletters.content'),
            );
            expect(
                wrapper.get('input[type="hidden"][name="content"]').element,
            ).toHaveProperty('value', '');
            wrapper.unmount();
        },
    );

    it('requires a recipient in either list and relaxes the other once one is chosen', async () => {
        const wrapper = mountFields();
        await flushPromises();
        const lists = () =>
            wrapper.findAllComponents(StoreNewsletterRecipients);
        expect(lists().map((child) => child.props('required'))).toEqual([
            true,
            true,
        ]);
        lists()[0].vm.$emit('update:modelValue', ['user@example.test']);
        await flushPromises();
        expect(lists().map((child) => child.props('required'))).toEqual([
            true,
            false,
        ]);
        lists()[1].vm.$emit('update:modelValue', ['reader@example.test']);
        await flushPromises();
        expect(lists().map((child) => child.props('required'))).toEqual([
            false,
            false,
        ]);
        wrapper.unmount();
    });

    it('shows every server error beside its field', async () => {
        const errors = {
            subject: 'Subject too long',
            content: 'Content required',
            smtp: 'Configure SMTP',
            'user_emails.0': 'Unknown user',
        };
        const wrapper = mountFields(errors);
        await flushPromises();

        for (const message of Object.values(errors)) {
            expect(wrapper.text()).toContain(message);
        }

        expect(wrapper.getComponent(StoreRichTextField).props('error')).toBe(
            'Content required',
        );
        expect(wrapper.get('#content-error').text()).toBe('Content required');
        expect(
            wrapper
                .findAllComponents(StoreNewsletterRecipients)[0]
                .props('errors'),
        ).toEqual(errors);
        wrapper.unmount();
    });
});

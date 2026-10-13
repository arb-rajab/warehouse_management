import { Form, Head } from '@inertiajs/vue3';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { send } from '@/actions/App/Http/Controllers/Store/Admin/NewsletterController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreNewsletterFormFields from '@/components/StoreNewsletterFormFields.vue';
import StoreNewsletterRecipients from '@/components/StoreNewsletterRecipients.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import { createUseHttpStub } from '@/testing/inertiaStubs';
import Page from './Index.vue';

const { usePageMock, useHttpMock, formSlotPropsMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    useHttpMock: vi.fn(),
    formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
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
        router: { post: vi.fn(), get: vi.fn() },
    };
});

function mountPage(smtpConfigured = true) {
    return mount(Page, { props: { smtpConfigured } });
}

describe('Store newsletter Index', () => {
    beforeEach(() => {
        usePageMock.mockReturnValue({
            url: '/temp/admin/newsletter',
            props: defaultAuthProps({
                store: storeSharedProps({
                    permissions: ['send_newsletter', 'view_uploads'],
                }),
            }),
        });
        useHttpMock.mockImplementation((data) => createUseHttpStub(data, []));
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });
    afterEach(() => {
        i18n.global.locale.value = 'en';
    });

    it.each(['en', 'ar'] as const)(
        'full mounts the store form shell with the shared editor in %s',
        async (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountPage();
            await flushPromises();

            expect(
                wrapper.getComponent(ResourceFormPage).props(),
            ).toMatchObject({
                title: t('store.newsletters.title'),
                action: send(),
                submitLabel: t('store.newsletters.send'),
                submittingLabel: t('store.newsletters.sending'),
                layout: StoreAdminLayout,
                wide: true,
            });
            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
            expect(wrapper.getComponent(Head).props('title')).toBe(
                t('store.newsletters.title'),
            );
            expect(wrapper.get('h1').text()).toBe(t('store.newsletters.title'));
            expect(wrapper.getComponent(Form).props('action')).toEqual(send());
            expect(
                wrapper.getComponent(StoreNewsletterFormFields).props(),
            ).toEqual({ errors: {} });
            expect(
                wrapper.findAllComponents(StoreNewsletterRecipients),
            ).toHaveLength(2);
            expect(
                wrapper.getComponent(StoreRichTextField).props('label'),
            ).toBe(t('store.newsletters.content'));
            expect(wrapper.getComponent(SubmitButton).text()).toBe(
                t('store.newsletters.send'),
            );
            expect(wrapper.text()).not.toContain(
                t('store.newsletters.smtpMissing'),
            );
            wrapper.unmount();
        },
    );

    it.each(['en', 'ar'] as const)(
        'warns in %s when SMTP is not configured',
        async (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountPage(false);
            await flushPromises();
            expect(wrapper.get('[role="status"]').text()).toBe(
                t('store.newsletters.smtpMissing'),
            );
            wrapper.unmount();
        },
    );

    it('forwards server errors and renders the processing label', async () => {
        formSlotPropsMock.mockReturnValue({
            errors: { subject: 'Too long', smtp: 'Configure SMTP' },
            processing: true,
        });
        const wrapper = mountPage();
        await flushPromises();
        expect(
            wrapper.getComponent(StoreNewsletterFormFields).props('errors'),
        ).toEqual({ subject: 'Too long', smtp: 'Configure SMTP' });
        expect(wrapper.text()).toContain('Too long');
        expect(wrapper.text()).toContain('Configure SMTP');
        expect(wrapper.getComponent(SubmitButton).text()).toBe(
            t('store.newsletters.sending'),
        );
        wrapper.unmount();
    });
});

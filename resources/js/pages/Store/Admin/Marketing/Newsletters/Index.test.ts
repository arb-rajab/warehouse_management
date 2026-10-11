import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { send } from '@/actions/App/Http/Controllers/Store/Admin/NewsletterController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreNewsletterFormFields from '@/components/StoreNewsletterFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Page from './Index.vue';
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(() => ({
            errors: { subject: 'Too long' },
            processing: false,
        })),
        Link: createLinkStub(),
        usePage: () => ({
            url: '/temp/admin/newsletter',
            props: defaultAuthProps({ store: storeSharedProps() }),
        }),
        router: { post: vi.fn() },
        useHttp: () => ({
            get: vi.fn(() => Promise.resolve()),
            errors: {},
            processing: false,
        }),
    };
});
vi.mock('@/components/StoreRichTextField.vue', () => ({
    default: {
        props: ['id', 'label', 'name', 'modelValue', 'error'],
        template: '<div>{{label}} {{error}}</div>',
    },
}));
describe('Store newsletter Index', () => {
    it('renders the established store form shell and sends errors to the fields', () => {
        const wrapper = mount(Page, { props: { smtpConfigured: false } });
        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            title: t('store.newsletters.title'),
            action: send(),
            submitLabel: t('store.newsletters.send'),
            submittingLabel: t('store.newsletters.sending'),
            layout: StoreAdminLayout,
            wide: true,
        });
        expect(
            wrapper.getComponent(StoreNewsletterFormFields).props('errors'),
        ).toEqual({ subject: 'Too long' });
        expect(wrapper.text()).toContain('Too long');
        expect(wrapper.text()).toContain(t('store.newsletters.smtpMissing'));
        expect(wrapper.text()).toContain(t('store.newsletters.send'));
    });
    it('hides the missing SMTP hint when configured', () => {
        expect(
            mount(Page, { props: { smtpConfigured: true } }).text(),
        ).not.toContain(t('store.newsletters.smtpMissing'));
    });
});

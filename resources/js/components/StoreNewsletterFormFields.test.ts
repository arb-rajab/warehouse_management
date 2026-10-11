import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import StoreNewsletterRecipients from '@/components/StoreNewsletterRecipients.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import { t } from '@/lib/i18n';
import StoreNewsletterFormFields from './StoreNewsletterFormFields.vue';
vi.mock('@/components/StoreNewsletterRecipients.vue', () => ({
    default: {
        props: ['kind', 'errors', 'modelValue', 'required'],
        template: '<div />',
    },
}));
vi.mock('@/components/StoreRichTextField.vue', () => ({
    default: {
        props: [
            'id',
            'name',
            'label',
            'error',
            'modelValue',
            'required',
            'maxlength',
        ],
        template: '<div>{{label}} {{error}}</div>',
    },
}));
describe('StoreNewsletterFormFields', () => {
    it('renders the subject, recipient guidance and reusable selectors and rich text field with errors', () => {
        const errors = {
            subject: 'Too long',
            content: 'Invalid HTML',
            smtp: 'Missing SMTP',
        };
        const wrapper = mount(StoreNewsletterFormFields, { props: { errors } });

        for (const key of ['subject', 'content', 'recipientHelp']) {
            expect(wrapper.text()).toContain(t(`store.newsletters.${key}`));
        }

        expect(wrapper.get('#subject').attributes()).toMatchObject({
            required: '',
            maxlength: '255',
        });
        expect(
            wrapper
                .findAllComponents(StoreNewsletterRecipients)
                .map((child) => child.props('kind')),
        ).toEqual(['users', 'subscribers']);
        expect(wrapper.getComponent(StoreRichTextField).props()).toMatchObject({
            id: 'content',
            name: 'content',
            label: t('store.newsletters.content'),
            modelValue: '',
            error: 'Invalid HTML',
            required: '',
            maxlength: 100000,
        });

        for (const error of Object.values(errors)) {
            expect(wrapper.text()).toContain(error);
        }
    });
    it('requires a recipient in either group and relaxes the other group when selected', async () => {
        const wrapper = mount(StoreNewsletterFormFields, {
            props: { errors: {} },
        });
        const selectors = wrapper.findAllComponents(StoreNewsletterRecipients);
        expect(selectors.map((child) => child.props('required'))).toEqual([
            true,
            true,
        ]);
        selectors[0].vm.$emit('update:modelValue', ['user@example.test']);
        await wrapper.vm.$nextTick();
        expect(selectors.map((child) => child.props('required'))).toEqual([
            true,
            false,
        ]);
        selectors[1].vm.$emit('update:modelValue', ['subscriber@example.test']);
        await wrapper.vm.$nextTick();
        expect(selectors.map((child) => child.props('required'))).toEqual([
            false,
            false,
        ]);
    });
});

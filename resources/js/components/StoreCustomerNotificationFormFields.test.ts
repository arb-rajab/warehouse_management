import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import { t } from '@/lib/i18n';
import { notificationCustomer } from '@/testing/factories';
import StoreCustomerNotificationFormFields from './StoreCustomerNotificationFormFields.vue';

describe('StoreCustomerNotificationFormFields', () => {
    it('renders all labels, narrow customer options and native validation constraints', () => {
        const customers = [
            notificationCustomer(),
            notificationCustomer({ id: 22, name: 'Other', account_id: null }),
        ];
        const wrapper = mount(StoreCustomerNotificationFormFields, {
            props: { customers, errors: {} },
        });

        for (const key of [
            'title',
            'body',
            'customer',
            'customerPlaceholder',
        ]) {
            expect(wrapper.text()).toContain(
                t(`store.notifications.fields.${key}`),
            );
        }

        expect(
            wrapper
                .findAllComponents(FormField)
                .map((field) => field.props('id')),
        ).toEqual(['title', 'body']);
        expect(wrapper.get('#title').attributes()).toMatchObject({
            required: '',
            maxlength: '255',
        });
        expect(wrapper.get('#body').attributes('required')).toBeDefined();
        expect(wrapper.get('#user_id').attributes()).toMatchObject({
            required: '',
            name: 'user_id',
        });
        expect(
            wrapper.findAll('option').map((option) => option.text()),
        ).toEqual([
            t('store.notifications.fields.customerPlaceholder'),
            'Customer 100',
            'Other',
        ]);
    });

    it('passes each server error and escapes customer names', () => {
        const errors = {
            title: 'Title invalid',
            body: 'Body invalid',
            user_id: 'Recipient invalid',
        };
        const wrapper = mount(StoreCustomerNotificationFormFields, {
            props: {
                customers: [notificationCustomer({ name: '<b>Customer</b>' })],
                errors,
            },
        });
        expect(
            wrapper
                .findAllComponents(FormField)
                .map((field) => field.props('error')),
        ).toEqual(['Title invalid', 'Body invalid']);
        expect(
            wrapper
                .findAllComponents(FieldError)
                .map((field) => field.props('message')),
        ).toContain('Recipient invalid');
        expect(wrapper.find('b').exists()).toBe(false);
        expect(wrapper.text()).toContain('<b>Customer</b>');
    });
});

it('marks every required notification field', () => {
    const wrapper = mount(StoreCustomerNotificationFormFields, {
        props: { customers: [notificationCustomer()], errors: {} },
    });

    for (const id of ['title', 'body', 'user_id']) {
        expect(wrapper.get(`#${id}`).attributes('required')).toBeDefined();
        expect(
            wrapper
                .get(`label[for="${id}"]`)
                .findComponent(RequiredMark)
                .exists(),
        ).toBe(true);
    }
});

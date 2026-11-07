import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { customers } from '@/actions/App/Http/Controllers/Store/Admin/CustomerNotificationController';
import FormField from '@/components/FormField.vue';
import StoreSearchSelect from '@/components/StoreSearchSelect.vue';
import { t } from '@/lib/i18n';
import StoreCustomerNotificationFormFields from './StoreCustomerNotificationFormFields.vue';

const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => ({ get: getMock }),
}));

describe('StoreCustomerNotificationFormFields', () => {
    it('renders all labels, a required customer search select and native validation constraints', () => {
        const wrapper = mount(StoreCustomerNotificationFormFields, {
            props: { errors: {} },
        });

        for (const key of ['title', 'body', 'customer']) {
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
        expect(wrapper.getComponent(StoreSearchSelect).props()).toMatchObject({
            id: 'user_id',
            name: 'user_id',
            required: true,
            endpoint: customers,
            placeholder: t('store.notifications.fields.customerPlaceholder'),
        });
        expect(wrapper.get('#user_id').attributes('aria-required')).toBe(
            'true',
        );
        expect(
            wrapper.get('input[type="hidden"][name="user_id"]').element,
        ).toHaveProperty('value', '');
        // The customers are searched on demand, never listed in the page.
        expect(getMock).not.toHaveBeenCalled();
    });

    it('posts the picked customer id and shows its label', async () => {
        const wrapper = mount(StoreCustomerNotificationFormFields, {
            props: { errors: {} },
        });

        wrapper.getComponent(StoreSearchSelect).vm.$emit('update:modelValue', {
            id: 21,
            label: '<b>Customer</b>',
            description: '0612345678 · customer@example.test',
            icon: 'user',
        });
        await wrapper.vm.$nextTick();

        expect(
            wrapper
                .get('input[type="hidden"][name="user_id"]')
                .attributes('value'),
        ).toBe('21');
        expect(wrapper.get('#user_id').text()).toContain('<b>Customer</b>');
        expect(wrapper.find('b').exists()).toBe(false);
    });

    it('passes each server error to its field', () => {
        const errors = {
            title: 'Title invalid',
            body: 'Body invalid',
            user_id: 'Recipient invalid',
        };
        const wrapper = mount(StoreCustomerNotificationFormFields, {
            props: { errors },
        });

        expect(
            wrapper
                .findAllComponents(FormField)
                .map((field) => field.props('error')),
        ).toEqual(['Title invalid', 'Body invalid']);
        expect(wrapper.getComponent(StoreSearchSelect).props('error')).toBe(
            'Recipient invalid',
        );
        expect(wrapper.text()).toContain('Recipient invalid');
    });
});

import { mount } from '@vue/test-utils';
import { expect, it } from 'vitest';
import FormField from '@/components/FormField.vue';
import { t } from '@/lib/i18n';
import StoreCustomerFormFields from './StoreCustomerFormFields.vue';
function fields(extra: Record<string, unknown> = {}) {
    return mount(StoreCustomerFormFields, {
        props: {
            countries: [{ id: 1, name: 'Netherlands' }],
            errors: {},
            ...extra,
        },
    });
}
it('renders every customer field label with constraints matching server rules', () => {
    const wrapper = fields({ creating: true });

    for (const key of [
        'name',
        'email',
        'phone',
        'company_name',
        'tax_number',
        'bank_account_number',
        'company_address',
        'shipping_address',
        'member_serial',
        'country',
        'selectCountry',
        'AccSysID',
        'trade_license',
        'password',
        'password_confirmation',
    ]) {
        expect(wrapper.text()).toContain(t(`store.customers.fields.${key}`));
    }

    for (const id of [
        'name',
        'email',
        'phone',
        'password',
        'password_confirmation',
    ]) {
        expect(wrapper.get(`#${id}`).attributes('required')).toBeDefined();
    }

    expect(wrapper.get('#email').attributes('type')).toBe('email');
    expect(wrapper.get('#password').attributes('minlength')).toBe('8');
    expect(wrapper.get('#tax_number').attributes('maxlength')).toBe('100');
    expect(wrapper.get('#company_address').attributes('maxlength')).toBe('255');
    expect(wrapper.get('#trade_license').attributes('accept')).toBe(
        '.pdf,.doc,.docx,.png,.jpg,.jpeg',
    );
});
it('representative creation shows required identity fields and no trade licence selector', () => {
    const wrapper = fields({ creating: true, representative: true });
    expect(wrapper.find('#trade_license').exists()).toBe(false);
    expect(wrapper.find('#company_name').exists()).toBe(false);
    expect(wrapper.findAllComponents(FormField)).toHaveLength(5);
});
it('edit passwords are optional and displays server validation errors', () => {
    const wrapper = fields({
        errors: { email: 'Email is taken', trade_license: 'Invalid file' },
    });
    expect(wrapper.get('#password').attributes('required')).toBeUndefined();
    expect(wrapper.text()).toContain('Email is taken');
    expect(wrapper.text()).toContain('Invalid file');
});

it('refuses an oversized trade-license selection before form submission', async () => {
    const wrapper = fields();
    const input = wrapper.get('#trade_license');
    Object.defineProperty(input.element, 'files', {
        configurable: true,
        value: [{ size: 20000 * 1024 + 1 }],
    });
    await input.trigger('change');
    expect((input.element as HTMLInputElement).validity.customError).toBe(true);
    Object.defineProperty(input.element, 'files', {
        configurable: true,
        value: [{ size: 100 }],
    });
    await input.trigger('change');
    expect((input.element as HTMLInputElement).validity.customError).toBe(
        false,
    );
});

it('customer edits show the established representative checkbox with an explicit unchecked value', () => {
    const wrapper = fields({ customer: { id: 17, is_rep: true } });
    expect(
        wrapper.get('input[type="hidden"][name="is_rep"]').attributes('value'),
    ).toBe('0');
    expect(
        (
            wrapper.get('input[type="checkbox"][name="is_rep"]')
                .element as HTMLInputElement
        ).checked,
    ).toBe(true);
    expect(wrapper.text()).toContain(
        t('store.customers.representativeAccount'),
    );
    expect(fields({ creating: true }).find('[name="is_rep"]').exists()).toBe(
        false,
    );
});

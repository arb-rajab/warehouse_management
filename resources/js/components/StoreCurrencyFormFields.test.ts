import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import { storeCurrency } from '@/testing/factories';
import StoreCurrencyFormFields from './StoreCurrencyFormFields.vue';
function mountFields(errors: Record<string, string> = {}) {
    return mount(StoreCurrencyFormFields, {
        props: { currency: storeCurrency(), errors },
    });
}
describe('StoreCurrencyFormFields', () => {
    it('fills the edited name and associates every required field with a label', () => {
        const wrapper = mountFields();
        expect(wrapper.get('#name').element).toHaveProperty(
            'value',
            storeCurrency().name,
        );

        for (const id of ['name', 'symbol', 'code', 'exchange_rate']) {
            expect(wrapper.get(`#${id}`).attributes('required')).toBeDefined();
            expect(wrapper.get(`label[for=${id}]`).text()).not.toBe('');
        }

        for (const key of [
            'reference.name',
            'currencies.symbol',
            'currencies.code',
            'currencies.rate',
        ]) {
            expect(wrapper.text()).toContain(t(`store.${key}`));
        }
    });
    it('shows server field errors without replacing the edited value', () => {
        const wrapper = mountFields(
            Object.fromEntries(
                ['name', 'symbol', 'code', 'exchange_rate'].map((id) => [
                    id,
                    `Invalid ${id}`,
                ]),
            ),
        );

        for (const id of ['name', 'symbol', 'code', 'exchange_rate']) {
            expect(wrapper.text()).toContain(`Invalid ${id}`);
        }

        expect(wrapper.get('#name').element).toHaveProperty(
            'value',
            storeCurrency().name,
        );
    });
    it('mirrors database limits in native constraints', () => {
        const wrapper = mountFields();
        expect(wrapper.get('#name').attributes('maxlength')).toBe('255');
        expect(wrapper.get('#code').attributes('maxlength')).toBe('20');
        expect(wrapper.get('#exchange_rate').attributes()).toMatchObject({
            type: 'number',
            min: '0.00001',
            max: '99999.99999',
            step: '0.00001',
        });
    });
});

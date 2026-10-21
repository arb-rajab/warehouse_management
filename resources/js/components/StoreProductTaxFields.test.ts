import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import { i18n, t } from '@/lib/i18n';
import { storeDigitalProductTax } from '@/testing/factories';
import { expectRequiredFields } from '@/testing/requiredFields';
import StoreProductTaxFields from './StoreProductTaxFields.vue';

const taxes = [
    storeDigitalProductTax(),
    storeDigitalProductTax({
        id: 3,
        name: 'Service',
        tax: '0',
        tax_type: 'amount',
    }),
];

afterEach(() => {
    i18n.global.locale.value = 'en';
});

describe('StoreProductTaxFields', () => {
    it.each(['en', 'ar'] as const)(
        'labels each tax and its type in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mount(StoreProductTaxFields, {
                props: { taxes, errors: {} },
            });

            expect(wrapper.get('label[for="tax_2"]').text()).toBe(
                'VAT*' + t('common.required'),
            );
            expectRequiredFields(wrapper, [
                'tax_2',
                'tax_type_2',
                'tax_3',
                'tax_type_3',
            ]);
            expect(wrapper.get('label[for="tax_type_3"]').text()).toBe(
                t('store.productForm.taxType', { tax: 'Service' }) +
                    '*' +
                    t('common.required'),
            );
            expect(
                wrapper
                    .findAll('#tax_type_2 option')
                    .map((option) => option.text()),
            ).toEqual([
                t('store.productForm.flat'),
                t('store.productForm.percent'),
            ]);
        },
    );

    it('posts each tax id, amount and type in order with the amount constraints', () => {
        const wrapper = mount(StoreProductTaxFields, {
            props: { taxes, errors: {} },
        });

        expect(
            wrapper
                .findAll('input[name="tax_id[]"]')
                .map((input) => input.attributes('value')),
        ).toEqual(['2', '3']);
        expect(
            wrapper
                .findAll('input[name="tax[]"]')
                .map((input) => (input.element as HTMLInputElement).value),
        ).toEqual(['21.00', '0']);
        expect(
            (wrapper.get('#tax_type_2').element as HTMLSelectElement).value,
        ).toBe('percent');
        expect(
            (wrapper.get('#tax_type_3').element as HTMLSelectElement).value,
        ).toBe('amount');
        expect(wrapper.get('#tax_2').attributes()).toMatchObject({
            min: '0',
            max: '999999999999999999.99',
            step: '0.01',
            required: '',
        });
    });

    it('shows the errors of each row and of the lists', () => {
        const wrapper = mount(StoreProductTaxFields, {
            props: {
                taxes,
                errors: {
                    'tax.1': 'Bad amount',
                    'tax_type.0': 'Bad type',
                    tax_id: 'Bad list',
                },
            },
        });

        expect(wrapper.text()).toContain('Bad amount');
        expect(wrapper.text()).toContain('Bad type');
        expect(wrapper.text()).toContain('Bad list');
    });
});

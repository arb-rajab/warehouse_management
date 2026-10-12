import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FormField from '@/components/FormField.vue';
import { t } from '@/lib/i18n';
import StoreBrandDiscountFormFields from './StoreBrandDiscountFormFields.vue';

describe('Store brand discount form fields', () => {
    it('uses the legacy brand and seller flag and mirrors native validation with unique IDs', () => {
        const wrapper = mount(StoreBrandDiscountFormFields, {
            props: { brandId: 3, errors: {} },
        });
        expect(wrapper.get('input[name="brand_id"]').attributes('value')).toBe(
            '3',
        );
        expect(
            wrapper
                .get('input[name="seller_product_discount"]')
                .attributes('value'),
        ).toBe('0');
        expect(
            wrapper.get('input[name="discount"]').attributes(),
        ).toMatchObject({
            id: 'discount_3',
            type: 'number',
            value: '0',
            min: '0',
            step: 'any',
            required: '',
        });
        expect(
            wrapper.get('input[name="date_range"]').attributes(),
        ).toMatchObject({
            id: 'date_range_3',
            maxlength: '42',
            placeholder: t('store.brandDiscount.dateFormat'),
            pattern:
                '\\d{2}-\\d{2}-\\d{4} \\d{2}:\\d{2}:\\d{2} to \\d{2}-\\d{2}-\\d{4} \\d{2}:\\d{2}:\\d{2}',
        });

        for (const key of ['discount', 'dateRange', 'dateHelp']) {
            expect(wrapper.text()).toContain(t(`store.brandDiscount.${key}`));
        }

        expect(wrapper.findAllComponents(FormField)).toHaveLength(2);
    });
    it('renders backend errors without clearing entered values', async () => {
        const wrapper = mount(StoreBrandDiscountFormFields, {
            props: {
                brandId: 4,
                errors: {
                    discount: 'Invalid discount',
                    date_range: 'Invalid dates',
                },
            },
        });
        await wrapper.get('input[name="discount"]').setValue('3.5');
        await wrapper.setProps({ errors: { discount: 'Still invalid' } });
        expect(
            wrapper.get<HTMLInputElement>('input[name="discount"]').element
                .value,
        ).toBe('3.5');
        expect(wrapper.findAllComponents(FormField)[0].props('error')).toBe(
            'Still invalid',
        );
        expect(wrapper.text()).toContain('Still invalid');
    });
});

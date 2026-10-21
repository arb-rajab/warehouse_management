import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import FormField from '@/components/FormField.vue';
import { i18n, t } from '@/lib/i18n';
import { expectRequiredFields } from '@/testing/requiredFields';
import StoreProductDiscountFields from './StoreProductDiscountFields.vue';

afterEach(() => {
    i18n.global.locale.value = 'en';
});

describe('StoreProductDiscountFields', () => {
    it.each(['en', 'ar'] as const)(
        'labels the discount, its type and its period in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mount(StoreProductDiscountFields, {
                props: { errors: {} },
            });

            for (const key of [
                'dateRange',
                'dateHelp',
                'discount',
                'discountType',
                'flat',
                'percent',
            ]) {
                expect(wrapper.text()).toContain(t(`store.productForm.${key}`));
            }

            expect(wrapper.get('#date_range').attributes('placeholder')).toBe(
                t('store.productForm.dateFormat'),
            );
        },
    );

    it('defaults to no discount, a flat type and no period, with the legacy constraints', () => {
        const wrapper = mount(StoreProductDiscountFields, {
            props: { errors: {} },
        });
        expectRequiredFields(wrapper, ['discount', 'discount_type']);
        const [range, discount] = wrapper.findAllComponents(FormField);

        expect(range.props()).toMatchObject({ id: 'date_range', value: '' });
        expect(discount.props()).toMatchObject({
            id: 'discount',
            type: 'number',
            value: '0',
        });
        expect(wrapper.get('#discount').attributes()).toMatchObject({
            min: '0',
            max: '999999999999999999.99',
            step: '0.01',
            required: '',
        });
        expect(wrapper.get('#date_range').attributes()).toMatchObject({
            maxlength: '42',
            dir: 'ltr',
        });
        expect(wrapper.get('#date_range').attributes('pattern')).toContain(
            ' to ',
        );
        expect(
            (wrapper.get('#discount_type').element as HTMLSelectElement).value,
        ).toBe('amount');
    });

    it('starts from stored values and shows their errors', () => {
        const wrapper = mount(StoreProductDiscountFields, {
            props: {
                discount: '5.00',
                discountType: 'percent',
                dateRange: '01-11-2026 08:00:00 to 30-11-2026 20:30:00',
                errors: {
                    discount: 'Too much',
                    date_range: 'Bad range',
                    discount_type: 'Bad type',
                },
            },
        });

        expect(
            (wrapper.get('#discount_type').element as HTMLSelectElement).value,
        ).toBe('percent');
        expect(
            (wrapper.get('#date_range').element as HTMLInputElement).value,
        ).toBe('01-11-2026 08:00:00 to 30-11-2026 20:30:00');
        expect(wrapper.text()).toContain('Too much');
        expect(wrapper.text()).toContain('Bad range');
        expect(wrapper.text()).toContain('Bad type');
    });
});

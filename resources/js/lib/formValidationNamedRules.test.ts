import { describe, expect, it } from 'vitest';
import type { FieldValue } from '@/lib/formValidation';
import {
    formatProductNumber,
    namedRules,
    resolveNamedRules,
} from '@/lib/formValidationNamedRules';

function reader(values: Record<string, FieldValue>) {
    return (field: string) => values[field];
}

describe('formatProductNumber', () => {
    it.each([
        ['120.50', '120.5'],
        ['120.00', '120'],
        ['0.00', '0'],
        ['.0', '0'],
        ['15', '15'],
        ['999999999999999999.99', '999999999999999999.99'],
    ])('formats %s as ProductFormNumber::format() does', (value, expected) => {
        expect(formatProductNumber(value)).toBe(expected);
    });
});

describe('discountLimit', () => {
    const { validate, dependsOn } = namedRules.discountLimit;

    it('always allows no discount, even on a price of 0', () => {
        expect(validate('0', reader({ unit_price: '0' }))).toBeNull();
        expect(validate('0.00', reader({ unit_price: '0' }))).toBeNull();
    });

    it('keeps a flat discount below the unit price', () => {
        expect(
            validate(
                '12.50',
                reader({ unit_price: '12.50', discount_type: 'amount' }),
            ),
        ).toEqual({
            message: 'discount_below_price',
            params: { price: '12.5' },
        });
        expect(validate('12.49', reader({ unit_price: '12.50' }))).toBeNull();
    });

    it('keeps a percentage below 100, whatever the price', () => {
        expect(
            validate(
                '100',
                reader({ unit_price: '500', discount_type: 'percent' }),
            ),
        ).toEqual({ message: 'discount_percent_limit' });
        expect(
            validate(
                '99.99',
                reader({ unit_price: '0', discount_type: 'percent' }),
            ),
        ).toBeNull();
    });

    it('leaves an invalid discount or price to their own rules', () => {
        expect(validate('abc', reader({ unit_price: '1' }))).toBeNull();
        expect(validate('5', reader({ unit_price: null }))).toBeNull();
    });

    it('is checked again when the price or the type changes', () => {
        expect(dependsOn).toEqual(['unit_price', 'discount_type']);
    });
});

describe('discountDateRange', () => {
    const { validate } = namedRules.discountDateRange;
    const failure = {
        message: 'date_format',
        params: { format: 'd-m-Y H:i:s to d-m-Y H:i:s' },
    };

    it('accepts two real moments in order', () => {
        expect(
            validate('01-11-2026 08:00:00 to 30-11-2026 20:30:00', reader({})),
        ).toBeNull();
        expect(
            validate('01-11-2026 08:00:00 to 01-11-2026 08:00:00', reader({})),
        ).toBeNull();
    });

    it.each([
        '2026-11-01 to 2026-11-30',
        '31-02-2026 00:00:00 to 01-03-2026 00:00:00',
        '01-11-2026 24:00:00 to 02-11-2026 00:00:00',
        '30-11-2026 00:00:00 to 01-11-2026 00:00:00',
        '01-11-2026 08:00:00 to 30-11-2026 20:30:00 ',
    ])('refuses %s', (value) => {
        expect(validate(value, reader({}))).toEqual(failure);
    });
});

describe('resolveNamedRules', () => {
    it('resolves the names a schema lists', () => {
        expect(resolveNamedRules({ discount: ['discountLimit'] })).toEqual({
            discount: [namedRules.discountLimit],
        });
    });

    it('refuses an unknown name', () => {
        expect(() => resolveNamedRules({ discount: ['nope'] })).toThrow(
            'Unknown client validation rule: nope',
        );
    });
});

import { describe, expect, it } from 'vitest';
import { parseToken, supportedTokens } from '@/lib/formValidation';
import { namedRules } from '@/lib/formValidationNamedRules';
import {
    digitalProductValidation,
    discountRangePattern,
    physicalProductValidation,
    productAmountMax,
} from '@/lib/storeProductForm';
import productRules from '@/lib/storeProductRules.json';
import ar from '@/locales/ar.json';
import en from '@/locales/en.json';

const allOn = {
    shipping: true,
    productWiseShipping: true,
    flashDeals: true,
    vat: true,
    cashOnDelivery: true,
};
const allOff = {
    shipping: false,
    productWiseShipping: false,
    flashDeals: false,
    vat: false,
    cashOnDelivery: false,
};

function everyToken(): string[] {
    return Object.values(productRules).flatMap((form) =>
        [form.rules, form.create, form.edit].flatMap((rules) =>
            Object.values(rules as Record<string, unknown>).flatMap((entry) =>
                Array.isArray(entry)
                    ? (entry as string[])
                    : (entry as { rules: string[] }).rules,
            ),
        ),
    );
}

describe('storeProductForm', () => {
    it('bounds amounts by the DECIMAL(20,2) columns', () => {
        expect(productAmountMax).toBe('999999999999999999.99');
    });

    it('matches only the legacy discount range format', () => {
        const pattern = new RegExp(`^(?:${discountRangePattern})$`, 'v');

        expect(pattern.test('01-10-2026 00:00:00 to 31-10-2026 23:59:59')).toBe(
            true,
        );
        expect(pattern.test('2026-10-01 to 2026-10-31')).toBe(false);
    });
});

describe('physicalProductValidation', () => {
    it('checks the sections the business settings show, as the server does', () => {
        const on = physicalProductValidation({
            form: 'create',
            sections: allOn,
        });
        const off = physicalProductValidation({
            form: 'create',
            sections: allOff,
        });

        expect(on.rules.shipping_type).toEqual([
            'required',
            'string',
            'in:free,flat_rate',
        ]);
        expect(on.rules['tax.*']).toBeDefined();

        for (const field of [
            'shipping_type',
            'flat_shipping_cost',
            'is_quantity_multiplied',
            'est_shipping_days',
            'cash_on_delivery',
            'flash_deal_id',
            'flash_discount',
            'flash_discount_type',
            'tax_id',
            'tax.*',
        ]) {
            expect(off.rules[field]).toBeUndefined();
        }
    });

    it('keeps the minimum purchase quantity at or below the maximum', () => {
        expect(
            physicalProductValidation({ form: 'create', sections: allOn }).rules
                .min_qty,
        ).toEqual([
            'required',
            'integer',
            'max:2147483647',
            'min:1',
            'lte:max_qty',
        ]);
    });

    it('adds the create or the edit form fields', () => {
        const create = physicalProductValidation({
            form: 'create',
            sections: allOn,
        });
        const edit = physicalProductValidation({
            form: 'edit',
            sections: allOn,
        });

        expect(create.rules.button).toBeDefined();
        expect(create.rules.slug).toBeUndefined();
        expect(edit.rules.slug).toEqual(['nullable', 'string']);
        expect(edit.rules.button).toBeUndefined();
    });

    it('checks the seen barcode list on the edit form only, as the server does', () => {
        const create = physicalProductValidation({
            form: 'create',
            sections: allOn,
        });
        const edit = physicalProductValidation({
            form: 'edit',
            sections: allOn,
        });

        expect(edit.rules.product_barcodes_seen).toEqual([
            'nullable',
            'array',
            'max:50',
        ]);
        expect(edit.rules['product_barcodes_seen.*']).toEqual([
            'nullable',
            'string',
            'max:255',
        ]);
        expect(create.rules.product_barcodes_seen).toBeUndefined();
        expect(create.rules['product_barcodes_seen.*']).toBeUndefined();
    });

    it('does not validate a read-only Otajer field as posted', () => {
        const edit = physicalProductValidation({
            form: 'edit',
            sections: allOn,
            readOnlyFields: ['name', 'unit_price', 'published', 'serial'],
        });

        expect(edit.rules.name).toEqual(['nullable']);
        expect(edit.rules.unit_price).toEqual(['nullable']);
        expect(edit.rules.unit).toEqual(['required', 'string', 'max:20']);
        expect(edit.rules.published).toBeUndefined();
    });

    it('limits the discount by the unit price and checks the date range', () => {
        const schema = physicalProductValidation({
            form: 'create',
            sections: allOn,
        });

        expect(schema.named).toEqual({
            discount: [namedRules.discountLimit],
            date_range: [namedRules.discountDateRange],
        });
    });
});

describe('digitalProductValidation', () => {
    it('checks the date range but not the discount against the price', () => {
        const schema = digitalProductValidation('create');

        expect(schema.named).toEqual({
            date_range: [namedRules.discountDateRange],
        });
        expect(schema.rules.purchase_price).toEqual([
            'required',
            'numeric',
            'min:0',
            'max:999999999999999999.99',
            'decimal:0,2',
        ]);
        expect(schema.rules.lang).toBeUndefined();
        expect(digitalProductValidation('edit').rules.lang).toEqual([
            'required',
            'string',
            'max:10',
        ]);
    });
});

describe('storeProductRules.json', () => {
    it('uses only rule tokens the engine checks', () => {
        const names = new Set(
            everyToken().map((token) => parseToken(token)[0]),
        );

        expect(
            [...names].filter(
                (name) =>
                    !(supportedTokens as readonly string[]).includes(name),
            ),
        ).toEqual([]);
    });

    it('names only rules the registry has', () => {
        for (const form of Object.values(productRules)) {
            for (const names of Object.values(form.named)) {
                for (const name of names) {
                    expect(namedRules[name]).toBeDefined();
                }
            }
        }
    });

    it.each([
        ['en', en],
        ['ar', ar],
    ])('has a %s message for every rule it can fail', (_, locale) => {
        const messages = locale.validation.messages as Record<
            string,
            string | Record<string, string>
        >;
        const sized = ['min', 'max', 'lt', 'lte', 'gt', 'gte'];

        for (const token of everyToken()) {
            const [name] = parseToken(token);

            if (['bail', 'sometimes', 'nullable'].includes(name)) {
                continue;
            }

            const line = messages[name];

            if (sized.includes(name)) {
                expect(Object.keys(line as object)).toEqual([
                    'numeric',
                    'string',
                    'array',
                ]);
            } else {
                expect(typeof line).toBe('string');
            }
        }

        for (const name of [
            'discount_below_price',
            'discount_percent_limit',
            'date_format',
        ]) {
            expect(typeof messages[name]).toBe('string');
        }
    });
});

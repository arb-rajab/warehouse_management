import { afterEach, describe, expect, it } from 'vitest';
import {
    attributeName,
    dependentPatterns,
    formatMessage,
    isNumeric,
    keysFor,
    parseToken,
    patternFor,
    readForm,
    supportedTokens,
    validateForm,
    validateKey,
    valueOf,
} from '@/lib/formValidation';
import type { ValidationSchema } from '@/lib/formValidation';
import { namedRules } from '@/lib/formValidationNamedRules';
import { i18n } from '@/lib/i18n';

function form(html: string): HTMLFormElement {
    document.body.innerHTML = `<form>${html}</form>`;

    return document.querySelector('form') as HTMLFormElement;
}

function errorFor(
    html: string,
    rules: ValidationSchema['rules'],
    key: string,
    named?: ValidationSchema['named'],
): string | undefined {
    return validateKey(readForm(form(html)), { rules, named }, key);
}

afterEach(() => {
    i18n.global.locale.value = 'en';
    document.body.innerHTML = '';
});

describe('parseToken', () => {
    it('splits a rule into its name and arguments', () => {
        expect(parseToken('required')).toEqual(['required', []]);
        expect(parseToken('in:amount,percent')).toEqual([
            'in',
            ['amount', 'percent'],
        ]);
        expect(parseToken('regex:/^a,b$/')).toEqual(['regex', ['/^a,b$/']]);
    });
});

describe('readForm', () => {
    it('reads the fields as PHP parses the posted form', () => {
        const snapshot = readForm(
            form(`
                <input name="name" value="  Olive oil  ">
                <input name="tags" value="   ">
                <input name="product_barcodes[]" value="111">
                <input name="product_barcodes[]" value="222">
                <input name="variants[0][price]" value="4.50">
                <input type="hidden" name="can_expire" value="0">
                <input type="checkbox" name="can_expire" value="1" checked>
                <input type="radio" name="shipping_type" value="free">
                <input name="skipped" value="x" disabled>
                <button type="submit" name="button" value="publish">Save</button>
            `),
        );

        expect(snapshot.values.get('name')).toBe('Olive oil');
        expect(snapshot.values.get('tags')).toBeNull();
        expect(valueOf(snapshot, 'product_barcodes')).toEqual(['111', '222']);
        expect(valueOf(snapshot, 'variants.0.price')).toBe('4.50');
        expect(valueOf(snapshot, 'can_expire')).toBe('1');
        expect(valueOf(snapshot, 'shipping_type')).toBeUndefined();
        expect(snapshot.known.has('shipping_type')).toBe(true);
        expect(snapshot.known.has('skipped')).toBe(false);
        expect(snapshot.known.has('button')).toBe(false);
    });

    it('maps each control to the key its error is shown under', () => {
        const snapshot = readForm(
            form(
                '<input name="product_barcodes[]" value="1"><input name="product_barcodes[]" value="2">',
            ),
        );
        const inputs = document.querySelectorAll('input');

        expect(snapshot.elements.get(inputs[1])).toBe('product_barcodes.1');
    });
});

describe('patterns', () => {
    const schema: ValidationSchema = {
        rules: {
            name: ['required'],
            'variants.*.price': ['required'],
            discount: ['required'],
            flat_shipping_cost: ['required_if:shipping_type,flat_rate'],
            min_qty: ['lte:max_qty'],
        },
        named: { discount: [namedRules.discountLimit] },
    };

    it('finds the rule key of a row key and the row keys of a rule key', () => {
        const snapshot = readForm(
            form(
                '<input name="variants[0][price]"><input name="variants[1][price]">',
            ),
        );

        expect(patternFor(schema, 'variants.1.price')).toBe('variants.*.price');
        expect(patternFor(schema, 'unknown')).toBeUndefined();
        expect(keysFor(snapshot, 'variants.*.price')).toEqual([
            'variants.0.price',
            'variants.1.price',
        ]);
        expect(keysFor(snapshot, 'name')).toEqual([]);
    });

    it('lists the fields whose result depends on another', () => {
        expect(dependentPatterns(schema, 'unit_price')).toEqual(['discount']);
        expect(dependentPatterns(schema, 'shipping_type')).toEqual([
            'flat_shipping_cost',
        ]);
        expect(dependentPatterns(schema, 'max_qty')).toEqual(['min_qty']);
        expect(dependentPatterns(schema, 'name')).toEqual([]);
    });
});

describe('validateKey', () => {
    it('requires a filled value', () => {
        expect(
            errorFor('<input name="name">', { name: ['required'] }, 'name'),
        ).toBe('The name field is required.');
        expect(
            errorFor(
                '<input name="name" value="Oil">',
                { name: ['required'] },
                'name',
            ),
        ).toBeUndefined();
    });

    it('skips the other rules of a blank nullable or an absent field', () => {
        const rules = { weight: ['nullable', 'numeric', 'min:0'] };

        expect(
            errorFor('<input name="weight">', rules, 'weight'),
        ).toBeUndefined();
        expect(
            errorFor(
                '<input type="checkbox" name="featured" value="1">',
                { featured: ['boolean'] },
                'featured',
            ),
        ).toBeUndefined();
        expect(
            errorFor(
                '<input name="other">',
                { can_expire: ['sometimes', 'required'] },
                'can_expire',
            ),
        ).toBeUndefined();
    });

    it('stops at the first failing rule, as the server reports it', () => {
        expect(
            errorFor(
                '<input name="max_qty" value="abc">',
                { max_qty: ['required', 'integer', 'min:1'] },
                'max_qty',
            ),
        ).toBe('The maximum purchase quantity field must be an integer.');
    });

    it.each([
        ['integer', '01', 'The weight field must be an integer.'],
        ['integer', '1.5', 'The weight field must be an integer.'],
        ['numeric', '1,5', 'The weight field must be a number.'],
        [
            'decimal:0,2',
            '1.234',
            'The weight field must have 0-2 decimal places.',
        ],
        ['boolean', 'yes', 'The weight field must be true or false.'],
        ['in:youtube,vimeo', 'other', 'The selected weight is invalid.'],
        [
            'url:http,https',
            'ftp://example.com',
            'The weight field must be a valid URL.',
        ],
        ['regex:/^[A-Z]+$/', 'abc', 'The weight field format is invalid.'],
        ['string', '', undefined],
    ])('checks %s', (token, value, message) => {
        expect(
            errorFor(
                `<input name="weight" value="${value}">`,
                { weight: ['nullable', token] },
                'weight',
            ),
        ).toBe(message);
    });

    it.each([
        ['-3', 'integer', undefined],
        ['1e3', 'numeric', undefined],
        ['.5', 'numeric', undefined],
        ['12.5', 'decimal:0,2', undefined],
        ['https://vimeo.com/1', 'url:http,https', undefined],
    ])('accepts %s for %s', (value, token, message) => {
        expect(
            errorFor(
                `<input name="weight" value="${value}">`,
                { weight: [token] },
                'weight',
            ),
        ).toBe(message);
    });

    it('measures numbers, characters and rows by the field type', () => {
        expect(
            errorFor(
                '<input name="weight" value="1000000">',
                { weight: ['numeric', 'max:999999.99'] },
                'weight',
            ),
        ).toBe('The weight field must not be greater than 999999.99.');
        expect(
            errorFor(
                '<input name="unit" value="bottles of oil, 12 each!">',
                { unit: ['string', 'max:20'] },
                'unit',
            ),
        ).toBe('The unit field must not be greater than 20 characters.');
        expect(
            errorFor(
                '<input name="unit" value="زجاجة">',
                { unit: ['string', 'max:5'] },
                'unit',
            ),
        ).toBeUndefined();
        expect(
            errorFor(
                '<input name="product_types[]" value="a"><input name="product_types[]" value="b">',
                { product_types: ['array', 'max:1'] },
                'product_types',
            ),
        ).toBe('The product types field must not have more than 1 items.');
    });

    it('compares with another field only when both are numbers', () => {
        const rules = { min_qty: ['integer', 'lte:max_qty'] };

        expect(
            errorFor(
                '<input name="min_qty" value="30"><input name="max_qty" value="24">',
                rules,
                'min_qty',
            ),
        ).toBe(
            'The minimum purchase quantity field must be less than or equal to 24.',
        );
        expect(
            errorFor(
                '<input name="min_qty" value="24"><input name="max_qty" value="24">',
                rules,
                'min_qty',
            ),
        ).toBeUndefined();
        expect(
            errorFor(
                '<input name="min_qty" value="30"><input name="max_qty" value="">',
                rules,
                'min_qty',
            ),
        ).toBeUndefined();
    });

    it('requires a field when another has a value', () => {
        const html = (type: string, cost: string) =>
            `<input type="radio" name="shipping_type" value="${type}" checked><input name="flat_shipping_cost" value="${cost}">`;
        const rules = {
            flat_shipping_cost: [
                'nullable',
                'required_if:shipping_type,flat_rate',
            ],
        };

        expect(
            errorFor(html('flat_rate', ''), rules, 'flat_shipping_cost'),
        ).toBe(
            'The flat shipping cost field is required when shipping type is flat_rate.',
        );
        expect(
            errorFor(html('free', ''), rules, 'flat_shipping_cost'),
        ).toBeUndefined();
        expect(
            errorFor(
                '<select name="flash_deal_id"><option value="9" selected>Deal</option></select><input name="flash_discount">',
                { flash_discount: ['nullable', 'required_with:flash_deal_id'] },
                'flash_discount',
            ),
        ).toBe(
            'The flash discount field is required when flash deal is present.',
        );
    });

    it('finds duplicate rows the way PHP compares them', () => {
        const html =
            '<input name="product_barcodes[]" value="0871"><input name="product_barcodes[]" value="871"><input name="product_barcodes[]" value="">';
        const rules = {
            'product_barcodes.*': ['nullable', 'string', 'distinct'],
        };

        expect(errorFor(html, rules, 'product_barcodes.1')).toBe(
            'The product barcode field has a duplicate value.',
        );
        expect(errorFor(html, rules, 'product_barcodes.2')).toBeUndefined();
    });

    it('runs the named rules after the token rules', () => {
        const html =
            '<input name="unit_price" value="10"><select name="discount_type"><option value="amount" selected></option></select><input name="discount" value="12">';

        expect(
            errorFor(html, { discount: ['required', 'numeric'] }, 'discount', {
                discount: [namedRules.discountLimit],
            }),
        ).toBe('The discount must be less than the unit price (10).');
    });

    it('names a row by its rule key, or by itself without a translation', () => {
        const html =
            '<input name="variants[0][price]"><input name="variants[0][image]" value="x">';

        expect(
            errorFor(
                html,
                { 'variants.*.price': ['required'] },
                'variants.0.price',
            ),
        ).toBe('The variant price field is required.');
        expect(
            errorFor(
                html,
                { 'variants.*.image': ['integer'] },
                'variants.0.image',
            ),
        ).toBe('The variants.0.image field must be an integer.');
    });

    it('speaks the page language with the server wording', () => {
        i18n.global.locale.value = 'ar';

        expect(
            errorFor('<input name="name">', { name: ['required'] }, 'name'),
        ).toBe('حقل الاسم مطلوب.');
    });
});

describe('validateForm', () => {
    it('checks every rule key against the controls the form has', () => {
        const snapshot = readForm(
            form(
                '<input name="name"><input name="variants[0][qty]" value="-1"><input name="variants[1][qty]" value="2">',
            ),
        );

        expect(
            validateForm(snapshot, {
                rules: {
                    name: ['required'],
                    'variants.*.qty': ['required', 'integer', 'min:0'],
                    lang: ['required'],
                },
            }),
        ).toEqual({
            name: 'The name field is required.',
            'variants.0.qty': 'The variant quantity field must be at least 0.',
        });
    });
});

describe('helpers', () => {
    it('replaces placeholders in every case, longest name first', () => {
        expect(
            formatMessage(':Attribute and :ATTRIBUTE, :attribute (:a)', {
                attribute: 'discount',
                a: 'x',
            }),
        ).toBe('Discount and DISCOUNT, discount (x)');
    });

    it('follows PHP is_numeric', () => {
        expect(['1', '-1.5', '1e3', '.5', '5.'].every(isNumeric)).toBe(true);
        expect(['', 'abc', '1,5', null].some(isNumeric)).toBe(false);
    });

    it('names a field without a translation by its key', () => {
        expect(attributeName('max_qty')).toBe('maximum purchase quantity');
        expect(attributeName('some_field')).toBe('some field');
    });

    it('lists the rule tokens it checks', () => {
        expect(supportedTokens).toContain('lte');
        expect(supportedTokens).not.toContain('exists');
    });
});

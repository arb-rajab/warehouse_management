import { afterEach, describe, expect, it } from 'vitest';
import type { ValidationSchema } from '@/lib/formValidation';
import { namedRules } from '@/lib/formValidationNamedRules';
import { useFormValidation } from '@/lib/useFormValidation';

const schema: ValidationSchema = {
    rules: {
        name: ['required', 'string', 'max:10'],
        unit_price: ['required', 'numeric'],
        discount: ['required', 'numeric'],
        current_stock: ['required', 'integer'],
    },
    named: { discount: [namedRules.discountLimit] },
};

function setup(rules: ValidationSchema | null = schema) {
    document.body.innerHTML = `
        <form>
            <input name="name" value="">
            <input name="unit_price" value="10">
            <input name="discount" value="0">
            <div style="display: none"><input name="current_stock" value=""></div>
        </form>
        <input name="name" id="outside">`;
    const form = document.querySelector('form') as HTMLFormElement;
    const validation = useFormValidation(
        () => rules ?? undefined,
        () => form,
    );
    form.addEventListener('focusout', validation.onFocusOut);
    form.addEventListener('input', validation.onInput);
    form.addEventListener('change', validation.onChange);
    document.body.addEventListener('input', validation.onInput);
    const field = (name: string) =>
        form.querySelector(`[name="${name}"]`) as HTMLInputElement;
    const type = (name: string, value: string, event = 'input') => {
        field(name).value = value;
        field(name).dispatchEvent(new Event(event, { bubbles: true }));
    };
    const leave = (name: string) =>
        field(name).dispatchEvent(
            new FocusEvent('focusout', { bubbles: true }),
        );

    return { form, validation, field, type, leave };
}

afterEach(() => {
    document.body.innerHTML = '';
});

describe('useFormValidation', () => {
    it('says nothing about a field before it is left', () => {
        const { validation, type } = setup();
        type('name', '');

        expect(validation.errorsFor({})).toEqual({});
    });

    it('checks a field when it is left, and follows its value once wrong', () => {
        const { validation, field, type, leave } = setup();
        leave('name');

        expect(validation.errorsFor({})).toEqual({
            name: 'The name field is required.',
        });
        expect(field('name').getAttribute('aria-invalid')).toBe('true');

        type('name', 'Olive oil');

        expect(validation.errorsFor({})).toEqual({});
        expect(field('name').hasAttribute('aria-invalid')).toBe(false);
    });

    it('checks a dependent field again when the field it reads changes', () => {
        const { validation, type } = setup();
        type('discount', '5', 'change');
        expect(validation.errorsFor({})).toEqual({});

        type('unit_price', '4', 'change');

        expect(validation.errorsFor({})).toEqual({
            discount: 'The discount must be less than the unit price (4).',
        });
    });

    it('blocks the submit, focuses the first visible invalid field and shows the summary', () => {
        const { validation, field } = setup();

        expect(validation.onBefore()).toBe(false);
        expect(validation.blocked.value).toBe(true);
        expect(validation.errorsFor({})).toEqual({
            name: 'The name field is required.',
            current_stock: 'The stock quantity field is required.',
        });
        expect(document.activeElement).toBe(field('name'));
    });

    it('lets a valid form through and clears the summary once fixed', () => {
        const { validation, type } = setup();
        validation.onBefore();
        type('name', 'Oil');
        type('current_stock', '3');

        expect(validation.blocked.value).toBe(false);
        expect(validation.onBefore()).toBe(true);
    });

    it('shows server errors until their field is edited', () => {
        const { validation, type } = setup();
        const server = {
            name: 'The name has already been taken.',
            unit_price: 'Server says no.',
        };

        expect(validation.errorsFor(server)).toEqual(server);

        type('unit_price', '11');

        expect(validation.errorsFor(server)).toEqual({
            name: 'The name has already been taken.',
        });

        validation.onStart();

        expect(validation.errorsFor(server)).toEqual(server);
    });

    it('ignores controls outside its form', () => {
        const { validation } = setup();
        const outside = document.getElementById('outside') as HTMLInputElement;
        outside.dispatchEvent(new Event('input', { bubbles: true }));

        expect(validation.errorsFor({ name: 'kept' })).toEqual({
            name: 'kept',
        });
    });

    it('does nothing without a schema', () => {
        const { validation, leave } = setup(null);
        leave('name');

        expect(validation.onBefore()).toBe(true);
        expect(validation.errorsFor({ name: 'server' })).toEqual({
            name: 'server',
        });
    });
});

import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import StoreProductListField from './StoreProductListField.vue';

function mountList(errors: Record<string, string> = {}, values?: string[]) {
    return mount(StoreProductListField, {
        props: {
            id: 'product_barcodes',
            name: 'product_barcodes',
            label: 'Barcodes',
            addLabel: 'Add barcode',
            removeLabel: 'Remove barcode',
            placeholder: 'Barcode number',
            values,
            errors,
        },
        attachTo: document.body,
    });
}

function addButton(wrapper: VueWrapper) {
    return wrapper
        .findAll('button')
        .find((button) => button.text() === 'Add barcode')!;
}

describe('StoreProductListField', () => {
    it('labels the group and starts without rows', () => {
        const wrapper = mountList();

        expect(
            wrapper.get('#product_barcodes').attributes('aria-labelledby'),
        ).toBe('product_barcodes_label');
        expect(wrapper.get('#product_barcodes_label').text()).toBe('Barcodes');
        expect(wrapper.findAll('input')).toHaveLength(0);
        expect(wrapper.get('button').text()).toBe('Add barcode');
        wrapper.unmount();
    });

    it('adds focused rows posted as a list and removes the chosen one', async () => {
        const wrapper = mountList();
        await addButton(wrapper).trigger('click');
        await addButton(wrapper).trigger('click');
        const inputs = wrapper.findAll('input');

        expect(inputs).toHaveLength(2);
        expect(document.activeElement).toBe(inputs[1].element);
        expect(inputs.map((input) => input.attributes())).toEqual([
            expect.objectContaining({
                name: 'product_barcodes[]',
                type: 'text',
                maxlength: '255',
                placeholder: 'Barcode number',
                'aria-label': 'Barcodes 1',
            }),
            expect.objectContaining({
                name: 'product_barcodes[]',
                'aria-label': 'Barcodes 2',
            }),
        ]);
        await inputs[0].setValue('8712345678906');
        await inputs[1].setValue('4006381333931');
        await wrapper
            .get('button[aria-label="Remove barcode 1"]')
            .trigger('click');

        expect(
            wrapper
                .findAll('input')
                .map((input) => (input.element as HTMLInputElement).value),
        ).toEqual(['4006381333931']);
        wrapper.unmount();
    });

    it('starts from the stored values on the edit form, each removable', async () => {
        const wrapper = mountList({}, ['8712345678906', '4006381333931']);
        const values = () =>
            wrapper
                .findAll('input')
                .map((input) => (input.element as HTMLInputElement).value);

        expect(values()).toEqual(['8712345678906', '4006381333931']);
        expect(
            wrapper.findAll('input').map((input) => input.attributes('name')),
        ).toEqual(['product_barcodes[]', 'product_barcodes[]']);
        await wrapper
            .get('button[aria-label="Remove barcode 1"]')
            .trigger('click');
        await addButton(wrapper).trigger('click');

        expect(values()).toEqual(['4006381333931', '']);
        wrapper.unmount();
    });

    it('shows the error of each row and of the list', async () => {
        const wrapper = mountList({
            'product_barcodes.0': 'Taken',
            product_barcodes: 'Too many',
        });
        await wrapper.get('button').trigger('click');

        expect(wrapper.text()).toContain('Taken');
        expect(wrapper.text()).toContain('Too many');
        wrapper.unmount();
    });
});

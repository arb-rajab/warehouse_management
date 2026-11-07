import { mount } from '@vue/test-utils';
import { expect, it, vi } from 'vitest';
import StoreOrderEntryFields from './StoreOrderEntryFields.vue';
import StoreSearchSelect from './StoreSearchSelect.vue';
vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => ({ get: vi.fn(), cancel: vi.fn() }),
}));
it('reuses the search select, applies representative discount, converts pieces, preserves established tax and total semantics and maintains line names', async () => {
    const wrapper = mount(StoreOrderEntryFields, {
        props: { representativeOrder: true, errors: {} },
    });
    const searches = wrapper.findAllComponents(StoreSearchSelect);
    expect(searches).toHaveLength(3);
    expect(searches.map((search) => search.props('params'))).toEqual([
        { kind: 'customers' },
        { kind: 'representatives' },
        { kind: 'products' },
    ]);
    searches[1].vm.$emit('update:modelValue', {
        id: 2,
        label: 'Representative',
        extra: { rep_discount_percentage: 10 },
    });
    searches[2].vm.$emit('update:modelValue', {
        id: 5,
        label: 'Product',
        extra: {
            unit_price: 12,
            unit_equal: 6,
            tax: 21,
            stocks: [{ variant: '', qty: 50, price: 12 }],
        },
    });
    await wrapper.vm.$nextTick();
    expect(
        wrapper
            .get('input[name="products[0][product_id]"]')
            .attributes('value'),
    ).toBe('5');
    expect(wrapper.get('input[name="rep_id"]').attributes('value')).toBe('2');
    expect(wrapper.get('#price-1').attributes('value')).toBe('10.8');
    await wrapper.get('#unit-1').setValue('PerPiece');
    expect(wrapper.get('#price-1').attributes('value')).toBe('1.8');
    await wrapper.get('#quantity-1').setValue('3');
    expect(wrapper.text()).toContain('21.00');
    expect(wrapper.text()).toContain('5.40');
    expect(wrapper.text()).not.toContain('6.53');
    const add = wrapper
        .findAll('button')
        .find((button) => button.text().includes('Add product'))!;
    await add.trigger('click');
    expect(wrapper.findAll('input[name$="[quantity]"]')).toHaveLength(2);
    await wrapper.get('button[aria-label="Remove product"]').trigger('click');
    expect(wrapper.find('input[name="products[0][quantity]"]').exists()).toBe(
        true,
    );
});

it.each([false, true])(
    'marks order row units and required fields, leaving optional controls unmarked representative=%s',
    async (representativeOrder) => {
        const wrapper = mount(StoreOrderEntryFields, {
            props: { representativeOrder, errors: {} },
        });

        for (const id of [
            'customer_id',
            ...(representativeOrder ? ['rep_id'] : []),
            'product-1',
        ]) {
            expect(wrapper.get(`#${id}`).attributes('aria-required')).toBe(
                'true',
            );
            expect(
                wrapper
                    .find(`label[for="${id}"] [data-required-mark]`)
                    .exists(),
            ).toBe(true);
        }

        for (const id of ['quantity-1', 'unit-1', 'price-1']) {
            expect(wrapper.get(`#${id}`).attributes('required')).toBeDefined();
            expect(
                wrapper
                    .find(`label[for="${id}"] [data-required-mark]`)
                    .exists(),
            ).toBe(true);
        }

        for (const id of ['delivery_date', 'additional_info', 'info-1']) {
            expect(
                wrapper.get(`#${id}`).attributes('required'),
            ).toBeUndefined();
            expect(
                wrapper
                    .get(`label[for="${id}"]`)
                    .find('[data-required-mark]')
                    .exists(),
            ).toBe(false);
        }

        await wrapper
            .findAll('button')
            .find((button) => button.text().includes('Add product'))!
            .trigger('click');
        expect(
            wrapper.find('label[for="unit-2"] [data-required-mark]').exists(),
        ).toBe(true);
    },
);

it('prefills the customer from the server option and clears a product line', async () => {
    const wrapper = mount(StoreOrderEntryFields, {
        props: {
            representativeOrder: false,
            errors: {},
            customerOption: {
                id: 9,
                label: 'Ali Hassan',
                description: '+31 6 1234 5678 · ali@example.test',
                icon: 'user',
            },
        },
    });

    expect(wrapper.get('input[name="customer_id"]').attributes('value')).toBe(
        '9',
    );
    expect(wrapper.get('#customer_id').text()).toContain('Ali Hassan');
    expect(wrapper.get('#customer_id').text()).toContain('ali@example.test');

    const product = wrapper.findAllComponents(StoreSearchSelect)[1];
    product.vm.$emit('update:modelValue', {
        id: 5,
        label: 'Product',
        extra: {
            unit_price: 12,
            stocks: [{ variant: 'XL', qty: 5, price: 12 }],
        },
    });
    await wrapper.vm.$nextTick();
    expect(wrapper.get('#price-1').attributes('value')).toBe('12');
    expect(
        wrapper.get('select[name="products[0][stock_variant]"]').element,
    ).toHaveProperty('value', 'XL');

    product.vm.$emit('update:modelValue', null);
    await wrapper.vm.$nextTick();
    expect(
        wrapper
            .get('input[name="products[0][product_id]"]')
            .attributes('value'),
    ).toBe('');
    expect(wrapper.get('#price-1').attributes('value')).toBe('0');
    expect(
        wrapper.find('select[name="products[0][stock_variant]"]').exists(),
    ).toBe(false);
});

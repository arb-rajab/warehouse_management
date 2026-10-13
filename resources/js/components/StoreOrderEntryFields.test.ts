import { mount } from '@vue/test-utils';
import { expect, it, vi } from 'vitest';
import StoreOrderEntryFields from './StoreOrderEntryFields.vue';
import StoreOrderSearch from './StoreOrderSearch.vue';
vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => ({ get: vi.fn(), cancel: vi.fn() }),
}));
it('reuses async fields, applies representative discount, converts pieces, preserves established tax and total semantics and maintains line names', async () => {
    const wrapper = mount(StoreOrderEntryFields, {
        props: { representativeOrder: true, errors: {} },
    });
    const searches = wrapper.findAllComponents(StoreOrderSearch);
    searches[1].vm.$emit('select', {
        id: 2,
        name: 'Representative',
        rep_discount_percentage: 10,
    });
    searches[2].vm.$emit('select', {
        id: 5,
        name: 'Product',
        unit_price: 12,
        unit_equal: 6,
        tax: 21,
        stocks: [{ variant: '', qty: 50, price: 12 }],
    });
    await wrapper.vm.$nextTick();
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

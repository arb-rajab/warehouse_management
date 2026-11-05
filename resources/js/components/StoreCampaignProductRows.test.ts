import { mount } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import { index as productIndex } from '@/actions/App/Http/Controllers/Store/Admin/CampaignProductController';
import StoreSearchSelect from '@/components/StoreSearchSelect.vue';
import { t } from '@/lib/i18n';
import { storeCampaignProduct } from '@/testing/factories';
import { expectRequiredFields } from '@/testing/requiredFields';
import StoreCampaignProductRows from './StoreCampaignProductRows.vue';
const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => ({ get: getMock }),
}));
beforeEach(() => {
    vi.clearAllMocks();
});

it('lists the picked products with their discounts, keeps typed discounts across picks, escapes names and removes rows', async () => {
    const p = storeCampaignProduct();
    const wrapper = mount(StoreCampaignProductRows, {
        props: {
            modelValue: [
                {
                    id: p.id as number,
                    discount: 25,
                    discount_type: 'percent' as const,
                    quantity: 2,
                },
            ],
            selectedProducts: [p],
            quantity: true,
            errors: { 'products.0.discount': 'Rejected' },
            'onUpdate:modelValue': (value) =>
                wrapper.setProps({ modelValue: value }),
        },
    });
    expectRequiredFields(wrapper, [
        'products',
        'discount-12',
        'discount-type-12',
        'quantity-12',
    ]);
    expect(wrapper.text()).toContain('Rejected');

    for (const key of [
        'product',
        'price',
        'discount',
        'quantity',
        'discountType',
        'actions',
        'remove',
        'products',
    ]) {
        expect(wrapper.text()).toContain(t(`store.campaigns.fields.${key}`));
    }

    expect(wrapper.get('#discount-12').attributes()).toMatchObject({
        name: 'products[0][discount]',
        min: '0',
        max: '100',
        step: '0.01',
        required: '',
    });
    expect(wrapper.get('#quantity-12').attributes()).toMatchObject({
        name: 'products[0][quantity]',
        min: '0',
        step: '1',
    });

    // The picker is a multiple search select against the campaign endpoint,
    // showing the stored product as a chip before any search.
    const select = wrapper.getComponent(StoreSearchSelect);
    expect(select.props()).toMatchObject({
        multiple: true,
        endpoint: productIndex,
        required: true,
    });
    expect(wrapper.get('[data-testid="search-select-chip"]').text()).toContain(
        p.label,
    );
    expect(getMock).not.toHaveBeenCalled();

    const added = storeCampaignProduct({
        id: 13,
        label: '<script>bad</script>',
        extra: { price: 8, discount: 2, discount_type: 'amount' },
    });
    select.vm.$emit('update:items', [p, added]);
    await wrapper.vm.$nextTick();
    const modelValue = wrapper.props('modelValue');
    // The first row keeps its typed discount; the new one starts from the product's own.
    expect(modelValue[0].discount).toBe(25);
    expect(modelValue[1]).toEqual({
        id: 13,
        discount: 0,
        discount_type: 'amount',
        quantity: 1,
    });
    expect(wrapper.findAll('tbody tr')).toHaveLength(2);
    expect(wrapper.find('script').exists()).toBe(false);
    expect(wrapper.findAll('tbody tr')[1].text()).toContain(
        '<script>bad</script>',
    );
    expect(wrapper.findAll('tbody tr')[1].text()).toContain('8');

    await wrapper.findAll('tbody button')[0].trigger('click');
    expect(wrapper.props('modelValue').map((r) => r.id)).toEqual([13]);
    expect(wrapper.get('[data-testid="search-select-chip"]').text()).toContain(
        '<script>bad</script>',
    );
});

it('starts a flash deal row from the product discount and names an unknown product', async () => {
    const p = storeCampaignProduct({
        extra: { price: 50, discount: 15, discount_type: 'percent' },
    });
    const wrapper = mount(StoreCampaignProductRows, {
        props: {
            modelValue: [{ id: 99, discount: 1, discount_type: 'amount' }],
            selectedProducts: [],
            errors: {},
            'onUpdate:modelValue': (value) =>
                wrapper.setProps({ modelValue: value }),
        },
    });
    expect(wrapper.get('tbody').text()).toContain(
        t('store.campaigns.fields.unavailable', { id: 99 }),
    );

    wrapper.getComponent(StoreSearchSelect).vm.$emit('update:items', [p]);
    await wrapper.vm.$nextTick();

    expect(wrapper.props('modelValue')).toEqual([
        { id: 12, discount: 15, discount_type: 'percent' },
    ]);
    expect(wrapper.find('input[name$="[quantity]"]').exists()).toBe(false);
});

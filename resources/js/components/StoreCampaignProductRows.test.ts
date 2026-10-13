import { mount } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import { t } from '@/lib/i18n';
import { storeCampaignProduct } from '@/testing/factories';
import StoreCampaignProductRows from './StoreCampaignProductRows.vue';
const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => ({ get: getMock, processing: false, errors: {}, q: '' }),
}));
beforeEach(() => {
    vi.clearAllMocks();
    getMock.mockReturnValue(Promise.resolve());
});

it('preserves discounts and selected rows across searches, escapes names, ignores stale HTTP, and removes rows', async () => {
    const p = storeCampaignProduct();
    const wrapper = mount(StoreCampaignProductRows, {
        props: {
            modelValue: [
                {
                    id: p.id,
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
        'searchProducts',
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
    await wrapper.get('#product_search').setValue('Other');
    getMock.mock.calls[1][1].onSuccess([
        storeCampaignProduct({ id: 13, name: '<script>bad</script>' }),
    ]);
    getMock.mock.calls[0][1].onSuccess([
        storeCampaignProduct({ id: 14, name: 'Stale' }),
    ]);
    await wrapper.vm.$nextTick();
    expect(wrapper.find('script').exists()).toBe(false);
    expect(wrapper.get('#products').text()).toContain('<script>bad</script>');
    expect(wrapper.get('#products').text()).not.toContain('Stale');
    expect(wrapper.get('#products').text()).toContain(p.name);
    await wrapper.get('#products').setValue(['12', '13']);
    expect(wrapper.props('modelValue')[0].discount).toBe(25);
    expect(wrapper.findAll('tbody tr')).toHaveLength(2);
    await wrapper.findAll('tbody button')[0].trigger('click');
    expect(wrapper.props('modelValue').map((r) => r.id)).toEqual([13]);
});
it('pins a separately selected base product while reward searches change', async () => {
    const p = storeCampaignProduct();
    const wrapper = mount(StoreCampaignProductRows, {
        props: {
            modelValue: [],
            selectedProducts: [],
            pinnedIds: [12],
            errors: {},
        },
    });
    getMock.mock.calls[0][1].onSuccess([p]);
    await wrapper.vm.$nextTick();
    await wrapper.get('#product_search').setValue('New');
    getMock.mock.calls[1][1].onSuccess([]);
    await wrapper.vm.$nextTick();
    expect(wrapper.get('#products').text()).toContain(p.name);
});

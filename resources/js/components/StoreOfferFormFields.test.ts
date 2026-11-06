import { mount } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import StoreSearchSelect from '@/components/StoreSearchSelect.vue';
import { t } from '@/lib/i18n';
import {
    storeOffer,
    storeCampaignProduct,
    storeSharedProps,
} from '@/testing/factories';
import { expectRequiredFields } from '@/testing/requiredFields';
import StoreOfferFormFields from './StoreOfferFormFields.vue';
const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: { store: storeSharedProps() } }),
    useHttp: () => ({ cancel: () => {}, get: getMock, processing: false }),
}));
beforeEach(() => {
    vi.clearAllMocks();
});

it('posts product offer discriminator and positive integer base quantity while rendering hydrated reward rows', () => {
    const wrapper = mount(StoreOfferFormFields, {
        props: {
            offer: storeOffer(),
            baseProductId: 12,
            baseQuantity: 2,
            selectedProducts: [storeCampaignProduct()],
            selectedBrand: null,
            products: [
                { id: 12, quantity: 1, discount: 10, discount_type: 'amount' },
            ],
            errors: { base_product_id: 'Invalid' },
        },
    });
    expect(wrapper.get('input[name="offer_type"]').attributes('value')).toBe(
        'products_offer',
    );
    expect(wrapper.get('#base_quantity').attributes()).toMatchObject({
        min: '1',
        step: '1',
        required: '',
    });
    expectRequiredFields(wrapper, [
        'title',
        'start_date',
        'end_date',
        'base_product_id',
        'base_quantity',
        'products',
        'discount-12',
        'discount-type-12',
        'quantity-12',
    ]);
    // The base product is a single search select prefilled from the stored rows.
    const base = wrapper
        .findAllComponents(StoreSearchSelect)
        .find((select) => select.props('id') === 'base_product_id')!;
    expect(base.props('modelValue')).toMatchObject({ id: 12 });
    expect(
        wrapper.get('input[name="base_product_id"]').attributes('value'),
    ).toBe('12');
    expect(wrapper.get('#base_product_id').text()).toContain(
        'Translated product',
    );
    expect(wrapper.text()).toContain(t('store.campaigns.fields.baseProduct'));
    expect(wrapper.text()).toContain(t('store.campaigns.fields.baseQuantity'));
    expect(wrapper.text()).toContain('Invalid');
});
it('brand edit sends brand discriminator and spend threshold instead of base quantity and retains selected brand across search', async () => {
    const wrapper = mount(StoreOfferFormFields, {
        props: {
            offer: storeOffer({ offer_type: 'brand_offer', brand_id: 7 }),
            baseQuantity: 35.5,
            selectedProducts: [storeCampaignProduct()],
            selectedBrand: { id: 7, label: 'Brand', icon: 'tag' },
            errors: {},
        },
    });
    expect(wrapper.get('input[name="offer_type"]').attributes('value')).toBe(
        'brand_offer',
    );
    expectRequiredFields(wrapper, [
        'title',
        'start_date',
        'end_date',
        'brand_id',
        'base_quantity',
        'products',
    ]);
    expect(wrapper.find('#base_product_id').exists()).toBe(false);
    expect(wrapper.get('#base_quantity').attributes()).toMatchObject({
        min: '0.01',
        step: '0.01',
    });

    for (const key of ['brand', 'spendThreshold']) {
        expect(wrapper.text()).toContain(t(`store.campaigns.fields.${key}`));
    }

    // The brand is a single search select showing the stored brand before any search.
    expect(wrapper.get('input[name="brand_id"]').attributes('value')).toBe('7');
    expect(wrapper.get('#brand_id').text()).toContain('Brand');
    expect(getMock).not.toHaveBeenCalled();
    wrapper
        .findAllComponents(StoreSearchSelect)
        .find((select) => select.props('id') === 'brand_id')!
        .vm.$emit('update:modelValue', { id: 8, label: 'Other', icon: 'tag' });
    await wrapper.vm.$nextTick();
    expect(wrapper.get('input[name="brand_id"]').attributes('value')).toBe('8');
    expect(wrapper.get('#brand_id').text()).toContain('Other');
});

import { mount } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import { t } from '@/lib/i18n';
import { storeOffer, storeCampaignProduct } from '@/testing/factories';
import StoreOfferFormFields from './StoreOfferFormFields.vue';
const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => ({ get: getMock, processing: false, errors: {}, q: '' }),
}));
beforeEach(() => {
    vi.clearAllMocks();
    getMock.mockReturnValue(Promise.resolve());
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
            selectedBrand: { id: 7, name: 'Brand' },
            errors: {},
        },
    });
    expect(wrapper.get('input[name="offer_type"]').attributes('value')).toBe(
        'brand_offer',
    );
    expect(wrapper.find('#base_product_id').exists()).toBe(false);
    expect(wrapper.get('#base_quantity').attributes()).toMatchObject({
        min: '0.01',
        step: '0.01',
    });

    for (const key of ['brand', 'searchBrands', 'spendThreshold']) {
        expect(wrapper.text()).toContain(t(`store.campaigns.fields.${key}`));
    }

    await wrapper.get('#brand_search').setValue('Other');
    getMock.mock.calls[getMock.mock.calls.length - 1][1].onSuccess([
        { id: 8, name: 'Other' },
    ]);
    await wrapper.vm.$nextTick();
    expect(wrapper.get('#brand_id').text()).toContain('Brand');
});

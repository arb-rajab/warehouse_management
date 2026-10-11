import { mount } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import StoreCampaignFields from '@/components/StoreCampaignFields.vue';
import { t } from '@/lib/i18n';
import {
    storeFlashDeal,
    storeCampaignProduct,
    storeSharedProps,
} from '@/testing/factories';
import StoreFlashDealFormFields from './StoreFlashDealFormFields.vue';
const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: { store: storeSharedProps() } }),
    useHttp: () => ({
        cancel: () => {},
        get: getMock,
        processing: false,
        errors: {},
        q: '',
    }),
}));
beforeEach(() => {
    vi.clearAllMocks();
    getMock.mockReturnValue(Promise.resolve());
});

it('renders translated edit title and campaign styles with root product discounts', () => {
    const deal = storeFlashDeal();
    const wrapper = mount(StoreFlashDealFormFields, {
        props: {
            deal,
            title: 'عرض',
            products: [{ id: 12, discount: 12, discount_type: 'percent' }],
            selectedProducts: [storeCampaignProduct()],
            errors: { text_color: 'Rejected' },
        },
    });
    expect(wrapper.getComponent(StoreCampaignFields).props()).toMatchObject({
        title: 'عرض',
        titleMax: 50,
        campaign: deal,
    });
    expect(wrapper.get('#title').attributes('value')).toBe('عرض');

    for (const key of ['backgroundColor', 'textColor', 'dark', 'light']) {
        expect(wrapper.text()).toContain(t(`store.campaigns.fields.${key}`));
    }

    expect(wrapper.get('#background_color').attributes()).toMatchObject({
        maxlength: '255',
        required: '',
    });
    expect(wrapper.get('#text_color').attributes('required')).toBeDefined();
    expect(wrapper.find('#quantity-12').exists()).toBe(false);
    expect(wrapper.text()).toContain('Rejected');
});

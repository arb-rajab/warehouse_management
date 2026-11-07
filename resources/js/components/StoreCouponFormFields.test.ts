import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { productOptions } from '@/actions/App/Http/Controllers/Store/Admin/CouponController';
import StoreSearchSelect from '@/components/StoreSearchSelect.vue';
import { t } from '@/lib/i18n';
import { storeCampaignProduct, storeCoupon } from '@/testing/factories';
import { expectRequiredFields } from '@/testing/requiredFields';
import StoreCouponFormFields from './StoreCouponFormFields.vue';

const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => ({ get: getMock }),
}));
function mountFields(props: Record<string, unknown> = {}) {
    return mount(StoreCouponFormFields, {
        props: { selectedProducts: [], errors: {}, ...props },
    });
}
describe('StoreCouponFormFields', () => {
    beforeEach(() => vi.clearAllMocks());
    it('renders labels and required native constraints for cart discount fields', () => {
        const wrapper = mountFields();

        for (const key of [
            'type',
            'code',
            'discountType',
            'amount',
            'percent',
            'discount',
            'startDate',
            'endDate',
            'minBuy',
            'maxDiscount',
        ]) {
            expect(wrapper.text()).toContain(t(`store.coupons.fields.${key}`));
        }

        for (const id of [
            'type',
            'code',
            'discount_type',
            'discount',
            'start_date',
            'end_date',
            'min_buy',
            'max_discount',
        ]) {
            expect(wrapper.get(`#${id}`).attributes('required')).toBeDefined();
        }

        expectRequiredFields(wrapper, [
            'type',
            'code',
            'discount_type',
            'discount',
            'start_date',
            'end_date',
            'min_buy',
            'max_discount',
        ]);

        expect(wrapper.get('#code').attributes('maxlength')).toBe('255');

        for (const id of ['discount', 'min_buy', 'max_discount']) {
            expect(wrapper.get(`#${id}`).attributes()).toMatchObject({
                type: 'number',
                min: '1',
                step: '0.01',
            });
        }

        expect(wrapper.get('#start_date').attributes('type')).toBe('date');
        expect(wrapper.get('#end_date').attributes('type')).toBe('date');
    });
    it('caps percentages and constrains date ordering as the user changes fields', async () => {
        const wrapper = mountFields();
        await wrapper.get('#discount_type').setValue('percent');
        expect(wrapper.get('#discount').attributes('max')).toBe('100');
        await wrapper.get('#discount_type').setValue('amount');
        expect(wrapper.get('#discount').attributes('max')).toBeUndefined();
        await wrapper.get('#start_date').setValue('2026-10-01');
        await wrapper.get('#end_date').setValue('2026-10-31');
        expect(wrapper.get('#start_date').attributes('max')).toBe('2026-10-31');
        expect(wrapper.get('#end_date').attributes('min')).toBe('2026-10-01');
    });
    it('switches targeting in Vue and posts the picked products as product_ids[]', async () => {
        const selected = storeCampaignProduct({
            id: 12,
            label: 'Selected product',
        });
        const wrapper = mountFields({
            coupon: storeCoupon({ type: 'product_base', product_ids: [12] }),
            selectedProducts: [selected],
        });
        expectRequiredFields(wrapper, [
            'type',
            'code',
            'discount_type',
            'discount',
            'start_date',
            'end_date',
            'product_ids',
        ]);
        expect(wrapper.find('#min_buy').exists()).toBe(false);
        const select = wrapper.getComponent(StoreSearchSelect);
        expect(select.props()).toMatchObject({
            id: 'product_ids',
            name: 'product_ids[]',
            multiple: true,
            required: true,
            endpoint: productOptions,
        });
        const posted = () =>
            wrapper
                .findAll('input[type="hidden"][name="product_ids[]"]')
                .map((input) => input.attributes('value'));
        expect(posted()).toEqual(['12']);
        expect(
            wrapper.get('[data-testid="search-select-chip"]').text(),
        ).toContain('Selected product');
        expect(getMock).not.toHaveBeenCalled();

        select.vm.$emit('update:items', [
            selected,
            storeCampaignProduct({ id: 14, label: '<script>unsafe</script>' }),
        ]);
        await wrapper.vm.$nextTick();
        expect(posted()).toEqual(['12', '14']);
        expect(wrapper.find('script').exists()).toBe(false);

        await wrapper.get('#type').setValue('cart_base');
        expect(wrapper.find('#product_ids').exists()).toBe(false);
        expect(wrapper.find('#min_buy').exists()).toBe(true);
        await wrapper.get('#type').setValue('product_base');
        expect(posted()).toEqual(['12', '14']);
    });
    it('forwards server field and row errors', () => {
        const wrapper = mountFields({
            coupon: storeCoupon({ type: 'product_base' }),
            errors: {
                code: 'Taken',
                product_ids: 'Pick products',
                'product_ids.0': 'Unavailable',
            },
        });

        expect(wrapper.text()).toContain('Taken');
        expect(wrapper.text()).toContain('Pick products');
        expect(wrapper.text()).toContain('Unavailable');
        expect(wrapper.get('#product_ids').attributes('aria-invalid')).toBe(
            'true',
        );
    });
});

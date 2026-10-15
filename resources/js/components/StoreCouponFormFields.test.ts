import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { productOptions } from '@/actions/App/Http/Controllers/Store/Admin/CouponController';
import { t } from '@/lib/i18n';
import { storeCoupon } from '@/testing/factories';
import StoreCouponFormFields from './StoreCouponFormFields.vue';

const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => ({ get: getMock, errors: {}, processing: false, q: '' }),
}));
function mountFields(props: Record<string, unknown> = {}) {
    return mount(StoreCouponFormFields, {
        props: { selectedProducts: [], errors: {}, ...props },
    });
}
function resolveSearch(
    products: { id: number; name: string }[],
    call = getMock.mock.calls.length - 1,
) {
    getMock.mock.calls[call][1].onSuccess(products);
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
    it('switches targeting in Vue and keeps selected products across searches', async () => {
        const wrapper = mountFields({
            coupon: storeCoupon({ type: 'product_base', product_ids: [12] }),
            selectedProducts: [{ id: 12, name: 'Selected product' }],
        });
        expect(wrapper.find('#min_buy').exists()).toBe(false);
        expect(wrapper.get('#product_ids').attributes()).toMatchObject({
            name: 'product_ids[]',
            multiple: '',
            required: '',
        });
        expect(getMock).toHaveBeenCalledWith(
            productOptions.url(),
            expect.any(Object),
        );
        resolveSearch([{ id: 14, name: '<script>unsafe</script>' }]);
        await wrapper.vm.$nextTick();
        expect(wrapper.get('#product_ids').text()).toContain(
            'Selected product',
        );
        expect(wrapper.find('script').exists()).toBe(false);
        await wrapper.get('#product_ids').setValue(['12', '14']);
        await wrapper.get('#product_search').setValue('Other');
        resolveSearch([{ id: 15, name: 'Other product' }]);
        await wrapper.vm.$nextTick();
        expect(
            wrapper
                .get('#product_ids')
                .findAll('option')
                .map((option) => option.text()),
        ).toEqual([
            'Selected product',
            '<script>unsafe</script>',
            'Other product',
        ]);
        expect(
            (wrapper.get('#product_ids').element as HTMLSelectElement)
                .selectedOptions,
        ).toHaveLength(2);
        await wrapper.get('#type').setValue('cart_base');
        expect(wrapper.find('#product_ids').exists()).toBe(false);
        expect(wrapper.find('#min_buy').exists()).toBe(true);
    });
    it('ignores stale search results and forwards server field and row errors', async () => {
        const wrapper = mountFields({
            coupon: storeCoupon({ type: 'product_base' }),
            errors: { code: 'Taken', 'product_ids.0': 'Unavailable' },
        });
        await wrapper.get('#product_search').setValue('Latest');
        resolveSearch([{ id: 2, name: 'Latest' }]);
        resolveSearch([{ id: 1, name: 'Stale' }], 0);
        await wrapper.vm.$nextTick();
        expect(wrapper.get('#product_ids').text()).toContain('Latest');
        expect(wrapper.get('#product_ids').text()).not.toContain('Stale');
        expect(wrapper.text()).toContain('Taken');
        expect(wrapper.text()).toContain('Unavailable');
    });
});

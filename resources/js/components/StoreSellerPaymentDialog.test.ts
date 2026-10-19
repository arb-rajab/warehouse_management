import { Form } from '@inertiajs/vue3';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    modal,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/SellerPaymentController';
import { paymentModal } from '@/actions/App/Http/Controllers/Store/Admin/SellerWithdrawRequestController';
import FilterDialog from '@/components/FilterDialog.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { confirmSellerPayment } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import {
    storeSellerPayout,
    storeSellerWithdrawal,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import StoreSellerPaymentDialog from './StoreSellerPaymentDialog.vue';
const { post, cancel, usePage, formSlotProps, processing } = vi.hoisted(() => ({
    post: vi.fn(),
    cancel: vi.fn(),
    usePage: vi.fn(),
    processing: vi.fn(() => false),
    formSlotProps: vi.fn(
        (): { errors: Record<string, string>; processing: boolean } => ({
            errors: {},
            processing: false,
        }),
    ),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createUseHttpStub } =
        await import('@/testing/inertiaStubs');

    return {
        Form: createFormStub(formSlotProps),
        usePage,
        useHttp: (data: object) =>
            Object.assign(createUseHttpStub(data), {
                post,
                cancel,
                processing: processing(),
            }),
    };
});
vi.mock('@/lib/confirm', () => ({ confirmSellerPayment: vi.fn(() => true) }));
function mountDialog(open = true, withdrawal = false) {
    return mount(StoreSellerPaymentDialog, {
        props: {
            open,
            shopId: 17,
            withdrawal: withdrawal ? storeSellerWithdrawal() : null,
        },
    });
}
describe('seller payment dialog', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        post.mockResolvedValue(storeSellerPayout());
        processing.mockReturnValue(false);
        formSlotProps.mockReturnValue({ errors: {}, processing: false });
        usePage.mockReturnValue({
            props: defaultAuthProps({
                store: storeSharedProps({ permissions: ['pay_to_seller'] }),
            }),
        });
    });
    it('loads on opening and renders the complete payout form native constraints and bank fields', async () => {
        const w = mountDialog(false);
        expect(post).not.toHaveBeenCalled();
        await w.setProps({ open: true });
        await flushPromises();
        expect(post).toHaveBeenCalledWith(modal.url());
        expect(w.getComponent(FilterDialog).props()).toMatchObject({
            title: t('store.sellers.payment'),
            closeLabel: t('common.close'),
        });

        for (const key of [
            'dueToSeller',
            'bank_name',
            'bank_acc_name',
            'bank_acc_no',
            'bank_routing_no',
            'amount',
            'paymentMethod',
            'selectPaymentMethod',
            'cash',
            'bankPayment',
            'pay',
        ]) {
            expect(w.text()).toContain(t(`store.sellers.${key}`));
        }

        expect(w.get('input[name="shop_id"]').attributes('value')).toBe('17');
        expect(w.get('input[name="amount"]').attributes()).toMatchObject({
            type: 'number',
            min: '0.01',
            step: '0.01',
            required: '',
            value: '100',
        });
        expect(
            w.get('select[name="payment_option"]').attributes('required'),
        ).toBe('');
        expect(w.find('input[name="txn_code"]').exists()).toBe(false);
        await w.get('select').setValue('bank_payment');
        expect(w.get('input[name="txn_code"]').attributes('maxlength')).toBe(
            '191',
        );
        expect(w.text()).toContain(t('store.sellers.txnCode'));
        expect(w.getComponent(Form).props('action')).toEqual(
            store({ mergeQuery: {} }),
        );
        w.getComponent(Form).vm.$emit('before');
        expect(confirmSellerPayment).toHaveBeenCalled();
        w.getComponent(Form).vm.$emit('success');
        expect(w.emitted('update:open')).toEqual([[false]]);
        w.unmount();
        expect(cancel).toHaveBeenCalled();
    });
    it('uses the withdrawal route and caps only the default amount while retaining its hidden ids and absent txn field', async () => {
        post.mockResolvedValue(
            storeSellerPayout({ withdrawal: { id: 8, amount: 150 } }),
        );
        const w = mountDialog(true, true);
        await flushPromises();
        expect(post).toHaveBeenCalledWith(paymentModal.url());
        expect(w.get('input[name="amount"]').attributes('value')).toBe('100');
        expect(w.get('input[name="amount"]').attributes('max')).toBeUndefined();
        expect(
            w.get('input[name="withdraw_request_id"]').attributes('value'),
        ).toBe('8');
        expect(
            w.get('input[name="payment_withdraw"]').attributes('value'),
        ).toBe('withdraw_request');
        await w.get('select').setValue('bank_payment');
        expect(w.find('input[name="txn_code"]').exists()).toBe(false);
    });
    it('preserves negative due form behavior and hides payment methods disabled by the shop', async () => {
        const payout = storeSellerPayout();
        payout.shop.admin_to_pay = -12.5;
        payout.shop.bank_enabled = false;
        post.mockResolvedValue(payout);
        const w = mountDialog();
        await flushPromises();
        expect(w.text()).toContain(t('store.sellers.dueToAdmin'));
        expect(w.text()).toContain(t('store.sellers.clearDue'));
        expect(w.find('select').exists()).toBe(false);
        expect(w.find('input[name="txn_code"]').exists()).toBe(false);
        expect(w.text()).not.toContain(t('store.sellers.bank_name'));
    });
    it('does not offer a withdrawal payment for a nonpositive balance', async () => {
        const payout = storeSellerPayout({ withdrawal: { id: 8, amount: 40 } });
        payout.shop.admin_to_pay = 0;
        post.mockResolvedValue(payout);
        const w = mountDialog(true, true);
        await flushPromises();
        expect(w.find('input[name="amount"]').exists()).toBe(false);
        expect(w.findComponent(SubmitButton).exists()).toBe(false);
    });
    it('handles failure loading server errors and escaped bank text', async () => {
        post.mockRejectedValueOnce(new Error('failed'));
        const w = mountDialog();
        await flushPromises();
        expect(w.text()).toContain(t('store.sellers.paymentError'));
        await w.setProps({ open: false });
        const payout = storeSellerPayout();
        payout.shop.bank_name = '<script>bank</script>';
        formSlotProps.mockReturnValue({
            errors: { amount: 'Invalid amount' },
            processing: true,
        });
        post.mockResolvedValue(payout);
        await w.setProps({ open: true });
        await flushPromises();
        expect(w.text()).toContain('Invalid amount');
        expect(w.text()).toContain('<script>bank</script>');
        expect(w.find('script').exists()).toBe(false);
        expect(w.getComponent(SubmitButton).props('processing')).toBe(true);
    });
    it('announces loading and ignores stale replies after closure', async () => {
        let resolve!: (value: ReturnType<typeof storeSellerPayout>) => void;
        processing.mockReturnValue(true);
        post.mockReturnValue(
            new Promise((r) => {
                resolve = r;
            }),
        );
        const w = mountDialog();
        expect(w.get('[role="status"]').text()).toContain(
            t('store.sellers.loading'),
        );
        await w.setProps({ open: false });
        resolve(storeSellerPayout());
        await flushPromises();
        expect(w.findComponent(Form).exists()).toBe(false);
    });
    it('never fetches or renders the write form without pay permission', async () => {
        usePage.mockReturnValue({
            props: defaultAuthProps({
                store: storeSharedProps({ permissions: [] }),
            }),
        });
        const w = mountDialog();
        await flushPromises();
        expect(post).not.toHaveBeenCalled();
        expect(w.findComponent(Form).exists()).toBe(false);
    });
});

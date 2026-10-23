import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FilterDialog from '@/components/FilterDialog.vue';
import { t } from '@/lib/i18n';
import { storeSellerWithdrawal } from '@/testing/factories';
import Dialog from './StoreSellerWithdrawMessageDialog.vue';
describe('seller withdrawal message dialog', () => {
    it('shows escaped readonly seller text and closes through the shared dialog', async () => {
        const w = mount(Dialog, {
            props: {
                open: true,
                withdrawal: storeSellerWithdrawal({
                    message: '<script>seller</script>',
                }),
            },
        });
        expect(w.getComponent(FilterDialog).props()).toMatchObject({
            title: t('store.sellers.sellerMessage'),
            closeLabel: t('common.close'),
        });
        expect(w.text()).toContain(t('store.sellers.message'));
        expect(w.text()).toContain('<script>seller</script>');
        expect(w.find('script').exists()).toBe(false);
        expect(w.find('textarea').exists()).toBe(false);
        await w.get('button').trigger('click');
        expect(w.emitted('update:open')).toEqual([[false]]);
    });
    it('handles no selection and closed state', () => {
        const w = mount(Dialog, { props: { open: false, withdrawal: null } });
        expect(w.find('[role="dialog"]').exists()).toBe(false);
    });
});

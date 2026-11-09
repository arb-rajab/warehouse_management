import { CircleCheck, PencilLine } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import ReceiptStatusBadge from './ReceiptStatusBadge.vue';

describe('ReceiptStatusBadge', () => {
    it('shows a finalized receipt as a success pill with a check icon', () => {
        const wrapper = mount(ReceiptStatusBadge, {
            props: { status: 'finalized' },
        });

        expect(wrapper.text()).toBe(t('receipts.status.finalized'));
        expect(wrapper.findComponent(CircleCheck).exists()).toBe(true);
        expect(wrapper.findComponent(PencilLine).exists()).toBe(false);
        expect(wrapper.classes()).toEqual(
            expect.arrayContaining([
                'rounded-full',
                'bg-success-soft',
                'text-success-strong',
            ]),
        );
    });

    it('shows a draft as a warning pill with a pencil icon', () => {
        const wrapper = mount(ReceiptStatusBadge, {
            props: { status: 'draft' },
        });

        expect(wrapper.text()).toBe(t('receipts.status.draft'));
        expect(wrapper.findComponent(PencilLine).exists()).toBe(true);
        expect(wrapper.findComponent(CircleCheck).exists()).toBe(false);
        expect(wrapper.classes()).toEqual(
            expect.arrayContaining([
                'rounded-full',
                'bg-warning-soft',
                'text-warning-strong',
            ]),
        );
        expect(wrapper.classes()).not.toContain('bg-success-soft');
    });
});

import { Check } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import CellVerificationAcceptButton from './CellVerificationAcceptButton.vue';

describe('CellVerificationAcceptButton', () => {
    it('renders a plain button with the accept label and the confirm icon', () => {
        const wrapper = mount(CellVerificationAcceptButton);

        const button = wrapper.get('button');
        expect(button.attributes('type')).toBe('button');
        expect(button.text()).toBe(t('cellVerificationReport.accept'));
        expect(button.classes()).toContain('cursor-pointer');
        expect(wrapper.findComponent(Check).exists()).toBe(true);
    });

    it('emits click to the parent that owns the accept action', async () => {
        const wrapper = mount(CellVerificationAcceptButton);

        await wrapper.get('button').trigger('click');

        expect(wrapper.emitted('click')).toHaveLength(1);
    });
});

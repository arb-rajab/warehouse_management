import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import CellVerificationCorrectnessBadge from './CellVerificationCorrectnessBadge.vue';

describe('CellVerificationCorrectnessBadge', () => {
    it('shows the correct label and styling when correct', () => {
        const wrapper = mount(CellVerificationCorrectnessBadge, {
            props: { isCorrect: true },
        });

        expect(wrapper.text()).toBe(t('cellVerificationReport.correct'));
        expect(wrapper.get('span').classes()).toContain('bg-green-100');
    });

    it('shows the incorrect label and styling when not correct', () => {
        const wrapper = mount(CellVerificationCorrectnessBadge, {
            props: { isCorrect: false },
        });

        expect(wrapper.text()).toBe(t('cellVerificationReport.incorrect'));
        expect(wrapper.get('span').classes()).toContain('bg-red-100');
    });
});

import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import CellVerificationRoundStatusBadge from './CellVerificationRoundStatusBadge.vue';

describe('CellVerificationRoundStatusBadge', () => {
    it('shows the completed label and styling once the round has a completed_at', () => {
        const wrapper = mount(CellVerificationRoundStatusBadge, {
            props: { completedAt: '2026-08-01T11:00:00Z' },
        });

        expect(wrapper.text()).toBe(
            t('cellVerificationRound.status.completed'),
        );
        expect(wrapper.get('span').classes()).toContain('bg-success-soft');
        expect(wrapper.get('span').classes()).not.toContain('bg-warning-soft');
    });

    it('shows the in-progress label and styling while completed_at is null', () => {
        const wrapper = mount(CellVerificationRoundStatusBadge, {
            props: { completedAt: null },
        });

        expect(wrapper.text()).toBe(
            t('cellVerificationRound.status.inProgress'),
        );
        expect(wrapper.get('span').classes()).toContain('bg-warning-soft');
        expect(wrapper.get('span').classes()).not.toContain('bg-success-soft');
    });

    it('lets the completed state be relabelled through the default slot', () => {
        const wrapper = mount(CellVerificationRoundStatusBadge, {
            props: { completedAt: '2026-08-01T11:00:00Z' },
            slots: { default: formatDateTime('2026-08-01T11:00:00Z') },
        });

        expect(wrapper.text()).toBe(formatDateTime('2026-08-01T11:00:00Z'));
        expect(wrapper.get('span').classes()).toContain('bg-success-soft');
    });

    it('ignores the slot while in progress, keeping the in-progress label', () => {
        const wrapper = mount(CellVerificationRoundStatusBadge, {
            props: { completedAt: null },
            slots: { default: 'should not render' },
        });

        expect(wrapper.text()).toBe(
            t('cellVerificationRound.status.inProgress'),
        );
    });
});

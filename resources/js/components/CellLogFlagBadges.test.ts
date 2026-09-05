import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import type { CellStatusLog } from '@/types/admin';
import CellLogFlagBadges from './CellLogFlagBadges.vue';

function mountBadges(flags: CellStatusLog['flags']) {
    return mount(CellLogFlagBadges, { props: { flags } });
}

describe('CellLogFlagBadges', () => {
    it('renders nothing when there are no flags', () => {
        const wrapper = mountBadges([]);

        expect(wrapper.find('div').exists()).toBe(false);
    });

    it('renders a badge with the translated reason label for each flag', () => {
        const wrapper = mountBadges([
            { id: 1, reason: 'off_hours', acknowledged: false },
            { id: 2, reason: 'quick_flip', acknowledged: false },
        ]);

        const badges = wrapper.findAll('span');
        expect(badges).toHaveLength(2);
        expect(badges[0].text()).toBe(t('cellLog.flags.reasons.off_hours'));
        expect(badges[1].text()).toBe(t('cellLog.flags.reasons.quick_flip'));
    });

    it('styles an unacknowledged flag distinctly from an acknowledged one', () => {
        const wrapper = mountBadges([
            { id: 1, reason: 'off_hours', acknowledged: false },
            { id: 2, reason: 'off_hours', acknowledged: true },
        ]);

        const badges = wrapper.findAll('span');
        expect(badges[0].classes()).toContain('bg-amber-100');
        expect(badges[1].classes()).toContain('bg-gray-100');
        expect(badges[0].classes()).not.toEqual(badges[1].classes());
    });

    it('shows who acknowledged a flag, and nothing for an unacknowledged one', () => {
        const wrapper = mountBadges([
            {
                id: 1,
                reason: 'off_hours',
                acknowledged: true,
                acknowledged_by: { id: 9, name: 'Alice Admin' },
            },
            { id: 2, reason: 'quick_flip', acknowledged: false },
        ]);

        const acknowledgedByText = t('cellLog.flags.acknowledgedBy', {
            name: 'Alice Admin',
        });
        expect(wrapper.text()).toContain(acknowledgedByText);
        expect(
            wrapper
                .findAll('span')
                .filter((span) => span.text() === acknowledgedByText),
        ).toHaveLength(1);
    });
});

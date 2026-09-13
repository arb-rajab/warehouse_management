import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import ProductOccupancyFilterFields from './ProductOccupancyFilterFields.vue';

function mountFields(overrides: Record<string, unknown> = {}) {
    return mount(ProductOccupancyFilterFields, {
        props: {
            idPrefix: 'filter',
            expiringSoonDays: 45,
            state: '',
            expired: false,
            expiresWithinDays: '',
            inactive: false,
            ...overrides,
        },
    });
}

describe('ProductOccupancyFilterFields', () => {
    it('renders the state select, expires-within-days field with quick picks, and expired/inactive checkboxes, prefixed by idPrefix', () => {
        const wrapper = mountFields();

        expect(wrapper.get('select').attributes('id')).toBe('filter-state');
        expect(wrapper.get('input[type="number"]').attributes('id')).toBe(
            'filter-expires-within-days',
        );
        const checkboxes = wrapper.findAll('input[type="checkbox"]');
        expect(checkboxes[0].attributes('id')).toBe('filter-expired');
        expect(checkboxes[1].attributes('id')).toBe('filter-inactive');

        const quickPicks = wrapper
            .findAll('button')
            .map((button) => button.text());
        expect(quickPicks).toEqual(['7', '14', '30', '60']);
    });

    it('uses the given idPrefix so the fields can render twice on one page', () => {
        const wrapper = mountFields({ idPrefix: 'popover-filter' });

        expect(wrapper.get('select').attributes('id')).toBe(
            'popover-filter-state',
        );
        expect(wrapper.get('input[type="number"]').attributes('id')).toBe(
            'popover-filter-expires-within-days',
        );
        const checkboxes = wrapper.findAll('input[type="checkbox"]');
        expect(checkboxes[0].attributes('id')).toBe('popover-filter-expired');
        expect(checkboxes[1].attributes('id')).toBe('popover-filter-inactive');
    });

    it('offers only the full and opened state options', () => {
        const wrapper = mountFields();

        const options = wrapper
            .findAll('option')
            .map((option) => option.text());
        expect(options).toEqual([
            t('cellLog.filters.all'),
            t('cellLog.states.full'),
            t('cellLog.states.opened'),
        ]);
    });

    it('uses expiringSoonDays as the expires-within-days placeholder', () => {
        const wrapper = mountFields({ expiringSoonDays: 30 });

        expect(
            wrapper.get('input[type="number"]').attributes('placeholder'),
        ).toBe('30');
    });

    it('emits update:expiresWithinDays when a quick-pick button is clicked', async () => {
        const wrapper = mountFields();

        await wrapper.findAll('button')[1].trigger('click');

        expect(wrapper.emitted('update:expiresWithinDays')?.[0]).toEqual([
            '14',
        ]);
    });

    it('emits update:state, update:expiresWithinDays, update:expired, and update:inactive when each field changes', async () => {
        const wrapper = mountFields();

        await wrapper.get('select').setValue('full');
        expect(wrapper.emitted('update:state')?.[0]).toEqual(['full']);

        await wrapper.get('input[type="number"]').setValue('10');
        expect(wrapper.emitted('update:expiresWithinDays')?.[0]).toEqual([10]);

        const checkboxes = wrapper.findAll('input[type="checkbox"]');
        await checkboxes[0].setValue(true);
        expect(wrapper.emitted('update:expired')?.[0]).toEqual([true]);

        await checkboxes[1].setValue(true);
        expect(wrapper.emitted('update:inactive')?.[0]).toEqual([true]);
    });
});

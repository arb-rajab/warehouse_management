import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import DateRangeFilterFields from './DateRangeFilterFields.vue';

function mountFields(overrides: Record<string, unknown> = {}) {
    return mount(DateRangeFilterFields, {
        props: {
            fromId: 'filter-date-from',
            toId: 'filter-date-to',
            withinDaysId: 'filter-created-within-days',
            fromLabel: 'From',
            toLabel: 'To',
            withinDaysLabel: 'Within days',
            rangeDisabled: false,
            daysDisabled: false,
            from: '',
            to: '',
            withinDays: '',
            ...overrides,
        },
    });
}

describe('DateRangeFilterFields', () => {
    it('renders the from/to date inputs and the within-days number input with the given ids and labels', () => {
        const wrapper = mountFields();

        const dateInputs = wrapper.findAll('input[type="date"]');
        expect(dateInputs).toHaveLength(2);
        expect(dateInputs[0].attributes('id')).toBe('filter-date-from');
        expect(dateInputs[1].attributes('id')).toBe('filter-date-to');

        const numberInput = wrapper.find('input[type="number"]');
        expect(numberInput.attributes('id')).toBe('filter-created-within-days');

        const labels = wrapper.findAll('label');
        expect(labels.map((label) => label.text())).toEqual([
            'From',
            'To',
            'Within days',
        ]);
    });

    it('uses the given ids so the fields can render twice on one page', () => {
        const wrapper = mountFields({
            fromId: 'popover-filter-expiration-date-from',
            toId: 'popover-filter-expiration-date-to',
            withinDaysId: 'popover-filter-expires-within-days',
        });

        const dateInputs = wrapper.findAll('input[type="date"]');
        expect(dateInputs[0].attributes('id')).toBe(
            'popover-filter-expiration-date-from',
        );
        expect(dateInputs[1].attributes('id')).toBe(
            'popover-filter-expiration-date-to',
        );
        expect(wrapper.find('input[type="number"]').attributes('id')).toBe(
            'popover-filter-expires-within-days',
        );
    });

    it('disables the date inputs when rangeDisabled is true', () => {
        const wrapper = mountFields({ rangeDisabled: true });

        const dateInputs = wrapper.findAll('input[type="date"]');
        expect(dateInputs[0].attributes('disabled')).toBeDefined();
        expect(dateInputs[1].attributes('disabled')).toBeDefined();
        expect(
            wrapper.find('input[type="number"]').attributes('disabled'),
        ).toBeUndefined();
    });

    it('disables the within-days input when daysDisabled is true', () => {
        const wrapper = mountFields({ daysDisabled: true });

        expect(
            wrapper.find('input[type="number"]').attributes('disabled'),
        ).toBeDefined();
        const dateInputs = wrapper.findAll('input[type="date"]');
        expect(dateInputs[0].attributes('disabled')).toBeUndefined();
        expect(dateInputs[1].attributes('disabled')).toBeUndefined();
    });

    it('emits update:from, update:to, and update:withinDays when each input changes', async () => {
        const wrapper = mountFields();

        await wrapper.find('input[type="date"]').setValue('2026-08-01');
        expect(wrapper.emitted('update:from')?.[0]).toEqual(['2026-08-01']);

        await wrapper.findAll('input[type="date"]')[1].setValue('2026-08-10');
        expect(wrapper.emitted('update:to')?.[0]).toEqual(['2026-08-10']);

        await wrapper.find('input[type="number"]').setValue('7');
        // FilterNumberField's native number input auto-casts to a JS number
        // even though the model type is declared string (see js.md).
        expect(wrapper.emitted('update:withinDays')?.[0]).toEqual([7]);
    });
});

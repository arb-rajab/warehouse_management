import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { emptyCellHighlightFilters } from '@/lib/cellHighlight';
import type { CellHighlightFiltersValue } from '@/lib/cellHighlight';
import { t } from '@/lib/i18n';
import CellHighlightFilters from './CellHighlightFilters.vue';

const products = [
    { id: 1, name: 'Widgets' },
    { id: 2, name: 'Gadgets' },
];

describe('CellHighlightFilters', () => {
    it('opens the dialog when the trigger button is clicked', async () => {
        const wrapper = mount(CellHighlightFilters, {
            props: {
                products,
                modelValue: emptyCellHighlightFilters(),
            },
        });

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);

        await wrapper.get('button').trigger('click');

        expect(wrapper.find('[role="dialog"]').exists()).toBe(true);
        expect(wrapper.find('#highlight-state').exists()).toBe(true);
        expect(wrapper.find('#highlight-expires-within-days').exists()).toBe(
            true,
        );
        expect(wrapper.find('#highlight-product').exists()).toBe(true);
        expect(wrapper.find('#highlight-stale-after-days').exists()).toBe(true);
    });

    it('shows a badge with the count of active filters', () => {
        const wrapper = mount(CellHighlightFilters, {
            props: {
                products,
                modelValue: {
                    state: ['full'],
                    expiresWithinDays: '',
                    productIds: ['1'],
                    staleAfterDays: '',
                },
            },
        });

        expect(wrapper.get('button').text()).toContain('2');
    });

    it('updates the model when the state filter changes, allowing more than one selection', async () => {
        const modelValue = emptyCellHighlightFilters();
        const wrapper = mount(CellHighlightFilters, {
            props: { products, modelValue },
        });
        await wrapper.get('button').trigger('click');

        await wrapper.get('#highlight-state').trigger('click');
        await wrapper.findAll('input[type="checkbox"]')[0].setValue(true);
        expect(modelValue.state).toEqual(['empty']);

        await wrapper.findAll('input[type="checkbox"]')[1].setValue(true);
        expect(modelValue.state).toEqual(['empty', 'full']);
    });

    it('resets the model when Clear is clicked', async () => {
        const modelValue: CellHighlightFiltersValue = {
            state: ['full'],
            expiresWithinDays: '3',
            productIds: ['1'],
            staleAfterDays: '5',
        };
        const wrapper = mount(CellHighlightFilters, {
            props: { products, modelValue },
        });
        await wrapper.get('button').trigger('click');

        await wrapper
            .findAll('button')
            .find((button) => button.text() === t('cellLog.filters.clear'))
            ?.trigger('click');

        expect(modelValue).toEqual(emptyCellHighlightFilters());
    });
});

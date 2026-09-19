import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import {
    CELL_STATE_COLOR,
    CELL_STATES,
    cellStateLabel,
} from '@/lib/cellStateColor';
import CellStateLegend from './CellStateLegend.vue';

describe('CellStateLegend', () => {
    it('renders every cell state with its real label and icon', () => {
        const wrapper = mount(CellStateLegend);

        for (const state of CELL_STATES) {
            expect(wrapper.text()).toContain(cellStateLabel(state));
            expect(
                wrapper.findComponent(CELL_STATE_COLOR[state].icon).exists(),
            ).toBe(true);
        }
    });
});

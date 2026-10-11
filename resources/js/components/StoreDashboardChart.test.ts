import { mount } from '@vue/test-utils';
import { expect, it, vi } from 'vitest';
import StoreDashboardChart from './StoreDashboardChart.vue';
const mocks = vi.hoisted(() => ({ create: vi.fn(), destroy: vi.fn() }));
vi.mock('chart.js/auto', () => ({
    default: class {
        constructor(...args: unknown[]) {
            mocks.create(...args);
        }
        destroy() {
            mocks.destroy();
        }
    },
}));
it('renders accessible chart data, replaces changed charts and destroys on unmount', async () => {
    const wrapper = mount(StoreDashboardChart, {
        props: { title: 'Sales', labels: ['Root'], values: [4], kind: 'bar' },
    });
    expect(wrapper.get('canvas').attributes('aria-label')).toBe('Sales');
    expect(wrapper.text()).toContain('Root');
    expect(wrapper.text()).toContain('4');
    expect(mocks.create).toHaveBeenCalledOnce();
    await wrapper.setProps({ values: [7] });
    expect(mocks.destroy).toHaveBeenCalledOnce();
    wrapper.unmount();
    expect(mocks.destroy).toHaveBeenCalledTimes(2);
});

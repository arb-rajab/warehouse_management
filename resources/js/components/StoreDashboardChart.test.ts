import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { i18n } from '@/lib/i18n';
import { ChartMock, chartDestroy } from '@/testing/chartMock';
import StoreDashboardChart from './StoreDashboardChart.vue';

vi.mock(
    'chart.js',
    async () => (await import('@/testing/chartMock')).chartModuleMock,
);
const { canMock } = vi.hoisted(() => ({ canMock: vi.fn(() => true) }));
vi.mock('@/lib/storeAccess', async () => {
    const { computed } = await import('vue');

    return { useStoreAccess: () => computed(() => ({ can: canMock })) };
});
let themeListener: () => void;
const media = {
    matches: false,
    addEventListener: vi.fn((_event, callback) => {
        themeListener = callback;
    }),
    removeEventListener: vi.fn(),
};
beforeEach(() => {
    vi.clearAllMocks();
    canMock.mockReturnValue(true);
    media.matches = false;
    vi.stubGlobal(
        'matchMedia',
        vi.fn(() => media),
    );
});
afterEach(() => {
    vi.unstubAllGlobals();
    i18n.global.locale.value = 'en';
});
const props = {
    title: 'Sales',
    type: 'bar' as const,
    labels: ['Oil', 'Rice'],
    values: [3, 7],
    valueLabel: 'Sales count',
    categoryLabel: 'Category',
};
it('renders a real canvas with an accessible data table and destroys its chart', () => {
    const wrapper = mount(StoreDashboardChart, { props });
    expect(wrapper.get('canvas').attributes()).toMatchObject({
        role: 'img',
        'aria-label': 'Sales',
        'aria-describedby': wrapper.get('table').attributes('id'),
    });
    expect(
        wrapper.get('canvas').element.parentElement?.getAttribute('dir'),
    ).toBe('ltr');
    expect(wrapper.get('table').classes()).toContain('sr-only');
    expect(wrapper.get('caption').text()).toBe('Sales');
    expect(wrapper.findAll('thead th').map((x) => x.text())).toEqual([
        'Category',
        'Sales count',
    ]);
    expect(wrapper.findAll('tbody tr').map((x) => x.text())).toEqual([
        'Oil3',
        'Rice7',
    ]);
    expect(ChartMock).toHaveBeenCalledWith(
        wrapper.get('canvas').element,
        expect.objectContaining({
            type: 'bar',
            data: {
                labels: ['Oil', 'Rice'],
                datasets: [
                    expect.objectContaining({
                        data: [3, 7],
                        label: 'Sales count',
                    }),
                ],
            },
        }),
    );
    wrapper.unmount();
    expect(chartDestroy).toHaveBeenCalledOnce();
    expect(media.removeEventListener).toHaveBeenCalledWith(
        'change',
        themeListener,
    );
});
it('rebuilds for new series, Arabic labels and dark mode and retains an LTR canvas', async () => {
    const wrapper = mount(StoreDashboardChart, {
        props: { ...props, type: 'doughnut' },
    });
    await wrapper.setProps({ labels: ['زيت'], values: [12] });
    i18n.global.locale.value = 'ar';
    media.matches = true;
    themeListener();
    await nextTick();
    expect(ChartMock).toHaveBeenLastCalledWith(
        wrapper.get('canvas').element,
        expect.objectContaining({
            data: expect.objectContaining({
                labels: ['زيت'],
                datasets: [
                    expect.objectContaining({
                        data: [12],
                        backgroundColor: ['#60a5fa', '#fbbf24'],
                    }),
                ],
            }),
            options: expect.objectContaining({
                locale: 'ar',
                cutout: '70%',
                plugins: expect.objectContaining({
                    legend: expect.objectContaining({
                        rtl: true,
                        textDirection: 'rtl',
                        labels: { color: '#e5e7eb', boxWidth: 12 },
                    }),
                }),
            }),
        }),
    );
    expect(chartDestroy).toHaveBeenCalled();
    wrapper.unmount();
});
it('does not instantiate a chart without dashboard permission', () => {
    canMock.mockReturnValue(false);
    const wrapper = mount(StoreDashboardChart, { props });
    expect(ChartMock).not.toHaveBeenCalled();
    wrapper.unmount();
});

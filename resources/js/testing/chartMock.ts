import { vi } from 'vitest';

export const chartDestroy = vi.fn();
export const ChartMock = vi.fn(
    class {
        static register: ReturnType<typeof vi.fn> = vi.fn();
        destroy = chartDestroy;
    },
);
export const chartModuleMock = {
    Chart: ChartMock,
    ArcElement: {},
    BarController: {},
    BarElement: {},
    CategoryScale: {},
    DoughnutController: {},
    Legend: {},
    LinearScale: {},
    Tooltip: {},
};

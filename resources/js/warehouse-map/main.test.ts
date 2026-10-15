import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { MemoryStorage } from './data/Storage';
import type { WarehouseRepository } from './data/WarehouseRepository';
import { mountWarehouseMap } from './main';
import type { WarehouseMapHandle } from './main';
import { installFakeCanvas } from './testing/canvas';
import { makeWarehouse } from './testing/fixtures';

const messages = new Proxy({} as Record<string, string>, {
    get: (_, key: string) =>
        key === 'matches' ? '{count} match | {count} matches' : key,
    has: () => true,
});

let restore: () => void;
let handle: WarehouseMapHandle | null = null;
let repository: WarehouseRepository;
const warehouse = makeWarehouse(4, 6, 3);

beforeEach(() => {
    restore = installFakeCanvas();
    window.location.hash = '';
    repository = {
        load: vi.fn().mockResolvedValue({ warehouse, changed: true }),
    };
});

afterEach(() => {
    handle?.dispose();
    handle = null;
    restore();
    document.body.innerHTML = '';
    vi.useRealTimers();
});

async function mount(
    extra: Partial<Parameters<typeof mountWarehouseMap>[1]> = {},
) {
    const container = document.createElement('div');

    document.body.append(container);

    const switchLocale = vi.fn();

    handle = mountWarehouseMap(container, {
        rowsUrl: '/rows',
        helpUrl: '/admin/help',
        locale: 'en',
        messages,
        switchLocale,
        repository,
        storage: new MemoryStorage(),
        ...extra,
    });
    await vi.waitFor(() =>
        expect(container.querySelectorAll('.wms-tile').length).toBeGreaterThan(
            0,
        ),
    );

    return {
        container,
        switchLocale,
        root: container.querySelector('.wms') as HTMLElement,
    };
}

describe('mountWarehouseMap', () => {
    it('builds the page and shows the 2D plan with every tile', async () => {
        const { container, root } = await mount();

        expect(root.dataset.view).toBe('2d');
        expect(container.querySelectorAll('.wms-tile')).toHaveLength(24);
        expect(container.querySelectorAll('.wms-pair')).toHaveLength(2);
        expect(
            container
                .querySelector('.wms-load')
                ?.classList.contains('is-hidden'),
        ).toBe(true);
        expect(repository.load).toHaveBeenCalledTimes(1);
    });

    it('shows an error and lets you retry when the warehouse cannot be loaded', async () => {
        repository.load = vi
            .fn()
            .mockRejectedValueOnce(new Error('offline'))
            .mockResolvedValue({ warehouse, changed: true });

        const container = document.createElement('div');

        document.body.append(container);
        handle = mountWarehouseMap(container, {
            rowsUrl: '/rows',
            locale: 'en',
            messages,
            switchLocale: vi.fn(),
            repository,
            storage: new MemoryStorage(),
        });

        await vi.waitFor(() =>
            expect(
                container.querySelector('.wms-load.is-failed'),
            ).not.toBeNull(),
        );
        container.querySelector<HTMLButtonElement>('.wms-load button')!.click();
        await vi.waitFor(() =>
            expect(container.querySelectorAll('.wms-tile')).toHaveLength(24),
        );
    });

    it('filters the plan as you search, and counts the matches', async () => {
        vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });

        const { container } = await mount();
        const input =
            container.querySelector<HTMLInputElement>('.wms-search__input')!;

        input.value = 'rice';
        input.dispatchEvent(new Event('input'));
        vi.advanceTimersByTime(300);

        expect(
            container.querySelectorAll('.wms-tile.is-match').length,
        ).toBeGreaterThan(0);
        expect(
            container.querySelectorAll('.wms-tile.is-dim').length,
        ).toBeGreaterThan(0);
        expect(
            container.querySelector('.wms-result-pill.is-shown'),
        ).not.toBeNull();
        expect(
            container.querySelector('.wms-floors.has-filter'),
        ).not.toBeNull();
    });

    it('narrows by state chip and clears the search', async () => {
        const { container } = await mount();
        const chips = container.querySelectorAll<HTMLButtonElement>(
            '.wms-chips .wms-chip',
        );

        chips[2].click(); // opened

        const dimmed = container.querySelectorAll('.wms-tile.is-dim').length;

        expect(dimmed).toBeGreaterThan(0);

        chips[0].click(); // all
        expect(container.querySelectorAll('.wms-tile.is-dim')).toHaveLength(0);
    });

    it('opens the rack dialog from the plan, and closes it', async () => {
        const { container, root } = await mount();
        const tile = container.querySelector<HTMLElement>(
            '.wms-tile[data-row="A"][data-col="2"]',
        )!;

        document.elementFromPoint = () => tile;
        const plan = container.querySelector('.wms-plan')!;

        plan.dispatchEvent(
            Object.assign(
                new MouseEvent('pointerdown', {
                    clientX: 3,
                    clientY: 3,
                    bubbles: true,
                }),
                { pointerId: 1 },
            ),
        );
        plan.dispatchEvent(
            Object.assign(
                new MouseEvent('pointerup', {
                    clientX: 3,
                    clientY: 3,
                    bubbles: true,
                }),
                { pointerId: 1 },
            ),
        );
        Reflect.deleteProperty(document, 'elementFromPoint');

        expect(container.querySelector('.wms-backdrop.is-open')).not.toBeNull();
        expect(container.querySelectorAll('.wms-shelf')).toHaveLength(3);
        expect(root.dataset.modal).toBe('true');

        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        expect(container.querySelector('.wms-backdrop.is-open')).toBeNull();
        expect(root.dataset.modal).toBe('false');
    });

    it('chooses a floor', async () => {
        const { container } = await mount();
        const floor2 =
            container.querySelectorAll<HTMLButtonElement>(
                '.wms-chip--floor',
            )[2];

        floor2.click();

        expect(container.querySelector('.wms-plan__level')?.textContent).toBe(
            'level(2)'.replace('(2)', ' 2'),
        );
    });

    it('folds the toolbar with T and remembers it', async () => {
        const storage = new MemoryStorage();
        const { root } = await mount({ storage });

        window.dispatchEvent(
            new KeyboardEvent('keydown', { key: 't', code: 'KeyT' }),
        );
        expect(root.dataset.folded).toBe('true');
        expect(storage.getItem('wms-map:folded')).toBe('1');
    });

    it('toggles the theme and remembers the choice', async () => {
        const storage = new MemoryStorage();
        const { container, root } = await mount({ storage });
        const before = root.dataset.theme;

        container
            .querySelector<HTMLButtonElement>('button[aria-label="theme"]')!
            .click();

        expect(root.dataset.theme).not.toBe(before);
        expect(storage.getItem('wms-map:theme')).toBe(root.dataset.theme);
    });

    it('asks the panel to change the language, and flips direction for Arabic', async () => {
        const { container, switchLocale } = await mount();

        container
            .querySelector<HTMLButtonElement>('.wms-icon-btn--lang')!
            .click();
        expect(switchLocale).toHaveBeenCalledWith('ar');

        handle?.dispose();
        handle = null;
        document.body.innerHTML = '';

        const arabic = await mount({ locale: 'ar' });

        expect(arabic.root.getAttribute('dir')).toBe('rtl');
        expect(arabic.root.getAttribute('lang')).toBe('ar');
    });

    it('falls back to the plan and says so when the browser has no WebGL', async () => {
        const { container, root } = await mount();
        const error = vi.spyOn(console, 'error').mockImplementation(() => {});

        container
            .querySelector<HTMLButtonElement>(
                '.wms-seg button[data-view="3d"]',
            )!
            .click();
        await vi.waitFor(() =>
            expect(
                container.querySelector('.wms-stack__item--hint.is-shown'),
            ).not.toBeNull(),
        );

        expect(root.dataset.view).toBe('2d');
        expect(
            container.querySelector('.wms-stack__item--hint')?.textContent,
        ).toBe('webglMissing');
        error.mockRestore();
    });

    it('opens the legend and the help', async () => {
        const { container } = await mount();
        const buttons = container.querySelectorAll<HTMLButtonElement>(
            '.wms-actions .wms-icon-btn',
        );

        buttons[0].click();
        expect(
            container
                .querySelector('.wms-dialog--small')
                ?.parentElement?.classList.contains('is-open'),
        ).toBe(true);
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

        window.dispatchEvent(new KeyboardEvent('keydown', { key: '?' }));
        expect(
            container
                .querySelector('.wms-dialog--wide')
                ?.parentElement?.classList.contains('is-open'),
        ).toBe(true);
    });

    it('links to the help guide', async () => {
        const { container } = await mount();

        expect(
            container.querySelector('.wms-guide')?.getAttribute('href'),
        ).toBe('/admin/help');
    });

    it('opens a deep link to a slot in 3D (and shows the plan if that is impossible)', async () => {
        const error = vi.spyOn(console, 'error').mockImplementation(() => {});

        window.location.hash = '#slot=A2-1';

        const { root } = await mount();

        await vi.waitFor(() => expect(root.dataset.view).toBe('2d'));
        error.mockRestore();
    });

    it('revalidates when asked and redraws only when the data changed', async () => {
        const { container } = await mount();
        const bigger = makeWarehouse(4, 8, 3);

        repository.load = vi
            .fn()
            .mockResolvedValue({ warehouse: bigger, changed: true });
        vi.useFakeTimers({ toFake: ['Date'], now: Date.now() + 60_000 });
        await handle!.refresh();

        await vi.waitFor(() =>
            expect(container.querySelectorAll('.wms-tile')).toHaveLength(32),
        );
    });

    it('removes everything on dispose', async () => {
        const { container } = await mount();

        handle!.dispose();
        handle = null;

        expect(container.querySelector('.wms')).toBeNull();
    });
});

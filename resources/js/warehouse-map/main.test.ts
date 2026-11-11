import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { MemoryStorage } from './data/Storage';
import type { WarehouseRepository } from './data/WarehouseRepository';
import { mountWarehouseMap, transferRequestOf } from './main';
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

    it('matches slots holding any of the products checked in the picker', async () => {
        const { container } = await mount();
        const holding = (ids: number[]) =>
            warehouse.slots.filter(
                (s) => s.pallet && ids.includes(s.pallet.productId),
            ).length;
        const pill = () =>
            container.querySelector('.wms-result-pill')?.textContent;

        container
            .querySelector<HTMLButtonElement>('.wms-products__trigger')!
            .click();
        container
            .querySelector<HTMLInputElement>('[data-product-id="1"]')!
            .click();
        expect(pill()).toBe(`${holding([1])} matches`);

        container
            .querySelector<HTMLInputElement>('[data-product-id="3"]')!
            .click();
        expect(pill()).toBe(`${holding([1, 3])} matches`);
        expect(
            container.querySelectorAll('.wms-tile.is-dim').length,
        ).toBeGreaterThan(0);
        expect(
            container.querySelector('.wms-products__trigger')?.textContent,
        ).toBe('products');

        container
            .querySelector<HTMLButtonElement>('.wms-products__clear')!
            .click();
        expect(container.querySelectorAll('.wms-tile.is-dim')).toHaveLength(0);
    });

    it('opens a filter link for several products', async () => {
        window.location.hash = '#products=2,4&state=full';

        const { container } = await mount();
        const expected = warehouse.slots.filter(
            (s) =>
                s.state === 'full' &&
                s.pallet &&
                [2, 4].includes(s.pallet.productId),
        ).length;

        await vi.waitFor(() =>
            expect(
                container.querySelector('.wms-result-pill')?.textContent,
            ).toBe(`${expected} matches`),
        );
        expect(
            container.querySelector('.wms-chips .wms-chip.is-on')?.textContent,
        ).toBe('stFull');
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

    it("hands an occupied shelf's pallet to the host for a transfer", async () => {
        const transferPallet = vi.fn();
        const { container } = await mount({ transferPallet });
        const slot = warehouse.slots.find(
            (candidate) => candidate.pallet !== null && candidate.active,
        )!;
        const tile = container.querySelector<HTMLElement>(
            `.wms-tile[data-row="${slot.row}"][data-col="${slot.col}"]`,
        )!;

        document.elementFromPoint = () => tile;
        const plan = container.querySelector('.wms-plan')!;

        for (const type of ['pointerdown', 'pointerup']) {
            plan.dispatchEvent(
                Object.assign(
                    new MouseEvent(type, {
                        clientX: 3,
                        clientY: 3,
                        bubbles: true,
                    }),
                    { pointerId: 1 },
                ),
            );
        }

        Reflect.deleteProperty(document, 'elementFromPoint');

        const button = container.querySelector<HTMLElement>(
            `.wms-shelf[data-level="${slot.level}"] [data-action="transfer"]`,
        )!;

        button.click();
        expect(transferPallet).toHaveBeenCalledWith(transferRequestOf(slot));
    });

    it('offers no transfer button without a host to take it', async () => {
        const { container } = await mount();
        const slot = warehouse.slots.find((candidate) => candidate.pallet)!;
        const tile = container.querySelector<HTMLElement>(
            `.wms-tile[data-row="${slot.row}"][data-col="${slot.col}"]`,
        )!;

        document.elementFromPoint = () => tile;
        const plan = container.querySelector('.wms-plan')!;

        for (const type of ['pointerdown', 'pointerup']) {
            plan.dispatchEvent(
                Object.assign(
                    new MouseEvent(type, {
                        clientX: 3,
                        clientY: 3,
                        bubbles: true,
                    }),
                    { pointerId: 1 },
                ),
            );
        }

        Reflect.deleteProperty(document, 'elementFromPoint');

        expect(container.querySelector('.wms-backdrop.is-open')).not.toBeNull();
        expect(container.querySelector('[data-action="transfer"]')).toBeNull();
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

    describe('warehouses', () => {
        const options = [
            { id: 1, name: 'Main' },
            { id: 2, name: 'Annex' },
            { id: 3, name: 'Cold store' },
        ];
        /** Rows A and B of the four, as a filter on their warehouse loads them. */
        const fewer = makeWarehouse(2, 6, 3);
        const repositoryOf = (
            result: Promise<{ warehouse: typeof warehouse; changed: boolean }>,
        ): WarehouseRepository => ({ load: vi.fn(() => result) });

        it('shows a switcher only with more than one warehouse, naming the ones shown', async () => {
            const { container } = await mount({
                warehouses: { options, selectedIds: [2], select: vi.fn() },
            });

            expect(
                container.querySelector('.wms-brand .wms-warehouses__trigger')
                    ?.textContent,
            ).toBe('Annex');
            handle?.dispose();

            const single = await mount({
                warehouses: {
                    options: [options[0]],
                    selectedIds: null,
                    select: vi.fn(),
                },
            });

            expect(
                single.container.querySelector('.wms-warehouses'),
            ).toBeNull();
        });

        it('hands the ticked warehouses to the host, which owns the URL', async () => {
            const select = vi.fn();
            const { container } = await mount({
                warehouses: { options, selectedIds: [1], select },
            });

            container
                .querySelector<HTMLButtonElement>('.wms-warehouses__trigger')!
                .click();
            container
                .querySelector<HTMLButtonElement>(
                    '.wms-warehouses__option[data-warehouse-id="3"]',
                )!
                .click();
            container
                .querySelector<HTMLButtonElement>('.wms-warehouses__apply')!
                .click();

            expect(select).toHaveBeenCalledWith([1, 3]);
            // the map waits for the host: nothing is loaded yet
            expect(repository.load).toHaveBeenCalledTimes(1);
        });

        it('loads the rows of other warehouses in place, keeping the search, the chips and the view', async () => {
            vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });

            const createRepository = vi.fn(() =>
                repositoryOf(
                    Promise.resolve({ warehouse: fewer, changed: true }),
                ),
            );
            const { container, root } = await mount({
                warehouses: { options, selectedIds: null, select: vi.fn() },
                createRepository,
            });
            const input =
                container.querySelector<HTMLInputElement>(
                    '.wms-search__input',
                )!;

            input.value = 'rice';
            input.dispatchEvent(new Event('input'));
            vi.advanceTimersByTime(300);
            container
                .querySelectorAll<HTMLButtonElement>('.wms-chips .wms-chip')[1]
                .click(); // full

            await handle!.showWarehouses([2], '/rows?warehouse_ids[]=2');

            expect(createRepository).toHaveBeenCalledWith(
                '/rows?warehouse_ids[]=2',
            );
            expect(container.querySelectorAll('.wms-tile')).toHaveLength(12);
            expect(input.value).toBe('rice');
            expect(
                container.querySelector('.wms-floors.has-filter'),
            ).not.toBeNull();
            expect(root.dataset.view).toBe('2d');
            expect(
                container.querySelector('.wms-warehouses__trigger')
                    ?.textContent,
            ).toBe('Annex');
            expect(container.querySelector('.wms-hint')?.textContent).toContain(
                'warehouseShowing',
            );
            expect(handle!.snapshot()).toMatchObject({
                warehouseIds: [2],
                query: 'rice',
                filter: { terms: ['rice'], state: 'full' },
            });
        });

        it('keeps the newest of two quick switches', async () => {
            let resolveFirst: (value: {
                warehouse: typeof warehouse;
                changed: boolean;
            }) => void = () => undefined;
            const first = new Promise<{
                warehouse: typeof warehouse;
                changed: boolean;
            }>((resolve) => (resolveFirst = resolve));
            const createRepository = vi
                .fn()
                .mockReturnValueOnce(repositoryOf(first))
                .mockReturnValueOnce(
                    repositoryOf(
                        Promise.resolve({ warehouse: fewer, changed: true }),
                    ),
                );
            const { container } = await mount({
                warehouses: { options, selectedIds: null, select: vi.fn() },
                createRepository,
            });

            const slow = handle!.showWarehouses([1], '/rows?one');

            await handle!.showWarehouses([2], '/rows?two');
            resolveFirst({ warehouse: makeWarehouse(4, 8, 3), changed: true });
            await slow;

            expect(container.querySelectorAll('.wms-tile')).toHaveLength(12);
            expect(handle!.snapshot().warehouseIds).toEqual([2]);
        });

        it('covers the old rows with a retry when the new ones cannot be loaded', async () => {
            const retried = vi
                .fn()
                .mockResolvedValue({ warehouse: fewer, changed: true });
            const createRepository = vi.fn(() => ({
                load: vi
                    .fn()
                    .mockRejectedValueOnce(new Error('offline'))
                    .mockImplementation(retried),
            }));
            const { container } = await mount({
                warehouses: { options, selectedIds: null, select: vi.fn() },
                createRepository,
            });

            await handle!.showWarehouses([2], '/rows?two');

            expect(
                container.querySelector('.wms-load.is-failed'),
            ).not.toBeNull();
            expect(
                container
                    .querySelector('.wms-load')
                    ?.classList.contains('is-hidden'),
            ).toBe(false);

            container
                .querySelector<HTMLButtonElement>('.wms-load button')!
                .click();
            await vi.waitFor(() =>
                expect(container.querySelectorAll('.wms-tile')).toHaveLength(
                    12,
                ),
            );
            expect(
                container
                    .querySelector('.wms-load')
                    ?.classList.contains('is-hidden'),
            ).toBe(true);
        });

        it('carries what an earlier mount held into a new one (Back to other warehouses)', async () => {
            const { container } = await mount({
                warehouses: { options, selectedIds: [1], select: vi.fn() },
                carry: {
                    warehouseIds: [2],
                    query: 'rice',
                    filter: {
                        terms: ['rice'],
                        state: 'all',
                        expiring: false,
                        productIds: [],
                    },
                    floor: 2,
                    targetId: null,
                    selectedId: null,
                    rack: null,
                    rackSlotIds: [],
                    drawerOpen: false,
                },
            });

            expect(
                container.querySelector<HTMLInputElement>('.wms-search__input')!
                    .value,
            ).toBe('rice');
            expect(
                container.querySelectorAll('.wms-tile.is-match').length,
            ).toBeGreaterThan(0);
            expect(handle!.snapshot()).toMatchObject({
                warehouseIds: [1],
                query: 'rice',
                floor: 2,
            });
            expect(container.querySelector('.wms-hint')?.textContent).toContain(
                'warehouseShowing',
            );
        });
    });

    it('removes everything on dispose', async () => {
        const { container } = await mount();

        handle!.dispose();
        handle = null;

        expect(container.querySelector('.wms')).toBeNull();
    });
});

describe('transferRequestOf', () => {
    it("describes an occupied slot's pallet, its place and its warehouse", () => {
        const slot = warehouse.slots.find((candidate) => candidate.pallet)!;

        expect(transferRequestOf({ ...slot, warehouseId: 4 })).toEqual({
            palletId: slot.pallet!.id,
            rowLetter: slot.row,
            cellNumber: slot.col,
            flatNumber: slot.level,
            warehouseId: 4,
            productName: slot.pallet!.productName,
            productArName: slot.pallet!.productArName,
        });
        expect(transferRequestOf(slot)?.warehouseId).toBeNull();
    });

    it('is null for an empty slot', () => {
        const slot = warehouse.slots.find((candidate) => !candidate.pallet)!;

        expect(transferRequestOf(slot)).toBeNull();
    });
});

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { buildMatchIndex } from '../domain/MatchIndex';
import type { Slice } from '../state/slices';
import { makeWarehouse } from '../testing/fixtures';
import { PlanView } from './PlanView';
import type { PlanState } from './PlanView';

function slice(
    initial: PlanState,
): Slice<PlanState> & { set(next: Partial<PlanState>): void } {
    let state = initial;
    const listeners = new Set<(v: PlanState, p: PlanState) => void>();

    return {
        get: () => state,
        subscribe: (listener) => {
            listeners.add(listener);

            return () => listeners.delete(listener);
        },
        set(next) {
            const previous = state;

            state = { ...state, ...next };
            listeners.forEach((l) => l(state, previous));
        },
    };
}

const none = buildMatchIndex([], {
    terms: [],
    state: 'all',
    expiring: false,
    productIds: [],
});
const t = (key: string) => key;

function setup(rows = 4, cols = 6) {
    const warehouse = makeWarehouse(rows, cols, 3);
    const stage = document.createElement('div');

    Object.defineProperty(stage, 'clientWidth', { value: 1400 });
    Object.defineProperty(stage, 'clientHeight', { value: 900 });
    document.body.append(stage);

    const state = slice({
        warehouse,
        match: none,
        floor: null,
        targetId: null,
        orientation: 'auto',
        active: true,
        locale: 'en',
    });
    const intents = {
        openRack: vi.fn(),
        setChromeAway: vi.fn(),
        cycleOrientation: vi.fn(),
    };
    const view = new PlanView(stage, state, intents, t);

    view.mount();

    return { view, stage, state, intents, warehouse };
}

let rafCallbacks: FrameRequestCallback[] = [];
/** How big the unscaled plan is, vertical and lying down. */
let planSize = { v: { w: 800, h: 1200 }, h: { w: 1200, h: 800 } };

beforeEach(() => {
    rafCallbacks = [];
    planSize = { v: { w: 800, h: 1200 }, h: { w: 1200, h: 800 } };
    vi.stubGlobal('requestAnimationFrame', (cb: FrameRequestCallback) =>
        rafCallbacks.push(cb),
    );
    // jsdom does not lay anything out: pretend the plan is 800 x 1200 vertically and 1200 x 800 lying down
    Object.defineProperty(HTMLElement.prototype, 'offsetWidth', {
        configurable: true,
        get() {
            return (this as HTMLElement).classList.contains('is-horizontal')
                ? planSize.h.w
                : planSize.v.w;
        },
    });
    Object.defineProperty(HTMLElement.prototype, 'offsetHeight', {
        configurable: true,
        get() {
            return (this as HTMLElement).classList.contains('is-horizontal')
                ? planSize.h.h
                : planSize.v.h;
        },
    });
});

afterEach(() => {
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
    Reflect.deleteProperty(HTMLElement.prototype, 'offsetWidth');
    Reflect.deleteProperty(HTMLElement.prototype, 'offsetHeight');
});

function flushFrames() {
    const pending = rafCallbacks;

    rafCallbacks = [];
    pending.forEach((cb) => cb(0));
}

describe('PlanView', () => {
    it('lays out one pair per aisle, one strip per row and one tile per column', () => {
        const { stage } = setup(4, 6);

        expect(stage.querySelectorAll('.wms-pair')).toHaveLength(2);
        expect(stage.querySelectorAll('.wms-rack-strip')).toHaveLength(4);
        expect(stage.querySelectorAll('.wms-tile')).toHaveLength(24);
        expect(
            stage
                .querySelectorAll('.wms-pair')[1]
                .classList.contains('wms-pair--alt'),
        ).toBe(true);
    });

    it('puts row A on the right and row B on the left of its aisle', () => {
        const { stage } = setup();
        const strips = stage
            .querySelectorAll('.wms-pair')[0]
            .querySelectorAll('.wms-rack-strip');

        expect(strips[0].classList.contains('wms-rack-strip--a')).toBe(true);
        expect(strips[1].classList.contains('wms-rack-strip--b')).toBe(true);
        expect(stage.querySelector('.wms-plate')?.textContent).toBe('A');
    });

    it('writes the aisle signs, the plates and the entrance from the translator', () => {
        const { stage } = setup();

        expect(stage.querySelector('.wms-pair__sign')?.textContent).toBe(
            'aisle A - B',
        );
        expect(
            stage.querySelector('.wms-plan__entrance')?.textContent,
        ).toContain('entrance');
    });

    it('numbers the tiles from the entrance end', () => {
        const { stage } = setup();
        const first = stage.querySelector('.wms-rack-strip')!;

        expect(
            [...first.querySelectorAll('.wms-tile__num')].map(
                (n) => n.textContent,
            ),
        ).toEqual(['1', '2', '3', '4', '5', '6']);
    });

    it('colours each tile by what its column holds', () => {
        const { stage, warehouse } = setup();
        const tile = stage.querySelector<HTMLElement>(
            '.wms-tile[data-row="A"][data-col="1"]',
        )!;
        const column = warehouse.byColumn.get('A|1')!;
        const expected = column.some((s) => s.state === 'opened')
            ? 'opened'
            : column.some((s) => s.state === 'full')
              ? 'full'
              : 'empty';

        expect(tile.classList.contains(`wms-tile--${expected}`)).toBe(true);
    });

    it('opens the rack of a tapped tile', () => {
        const { stage, intents } = setup();
        const tile = stage.querySelector<HTMLElement>(
            '.wms-tile[data-row="B"][data-col="3"]',
        )!;

        document.elementFromPoint = () => tile;
        stage.dispatchEvent(
            Object.assign(
                new MouseEvent('pointerdown', {
                    clientX: 5,
                    clientY: 5,
                    bubbles: true,
                }),
                { pointerId: 1 },
            ),
        );
        stage.dispatchEvent(
            Object.assign(
                new MouseEvent('pointerup', {
                    clientX: 5,
                    clientY: 5,
                    bubbles: true,
                }),
                { pointerId: 1 },
            ),
        );

        expect(intents.openRack).toHaveBeenCalledWith({
            row: 'B',
            col: 3,
            level: null,
        });
        expect(intents.setChromeAway.mock.calls).toEqual([[true], [false]]);
        Reflect.deleteProperty(document, 'elementFromPoint');
    });

    it('shows how many matches each aisle holds and fades the rest', () => {
        const { stage, state, warehouse } = setup();
        const match = buildMatchIndex(warehouse.slots, {
            terms: ['rice'],
            state: 'all',
            expiring: false,
            productIds: [],
        });

        state.set({ match });

        const heads = [
            ...stage.querySelectorAll<HTMLElement>('.wms-aisle-head'),
        ];

        heads.forEach((head, i) => {
            const letter = warehouse.rows[i].letter;
            const hits = match.byRow.get(letter) ?? 0;
            const count = head.querySelector('.wms-count')!;

            expect(count.classList.contains('is-hidden')).toBe(hits === 0);
            expect(head.classList.contains('is-faded')).toBe(hits === 0);

            if (hits) {
                expect(count.textContent).toBe(String(hits));
            }
        });
        expect(
            stage.querySelectorAll('.wms-tile.is-dim').length +
                stage.querySelectorAll('.wms-tile.is-match').length,
        ).toBe(24);
    });

    it('shows the chosen floor in a pill', () => {
        const { stage, state } = setup();
        const pill = stage.querySelector('.wms-plan__level')!;

        expect(pill.classList.contains('is-hidden')).toBe(true);

        state.set({ floor: 2 });
        expect(pill.classList.contains('is-hidden')).toBe(false);
        expect(pill.textContent).toBe('level 2');
    });

    it('marks the target tile with its code', () => {
        const { stage, state, warehouse } = setup();
        const slot = warehouse.slots.find((s) => s.row === 'A' && s.col === 2)!;

        state.set({ targetId: slot.id });

        const tile = stage.querySelector(
            '.wms-tile[data-row="A"][data-col="2"]',
        )!;

        expect(tile.classList.contains('is-target')).toBe(true);
        expect(tile.querySelector('.wms-tile__code')?.textContent).toBe(
            slot.code,
        );
        expect(stage.querySelectorAll('.is-target')).toHaveLength(1);
    });

    it('fits the plan to the screen once it is shown, lying down when that fits better', () => {
        const { stage } = setup();
        const world = stage.querySelector<HTMLElement>('.wms-plan__world')!;

        flushFrames();

        expect(world.style.transform).toMatch(/translate\(.*\) scale\(/);
        // 800x1200 vs 1200x800 on a 1400x900 screen: lying down fits far better
        expect(world.classList.contains('is-horizontal')).toBe(true);
    });

    it('asks to cycle the orientation, and refits when the store says so', () => {
        const { view, stage, state, intents } = setup();
        const world = stage.querySelector<HTMLElement>('.wms-plan__world')!;

        flushFrames();
        view.rotate();
        expect(intents.cycleOrientation).toHaveBeenCalled();

        state.set({ orientation: 'v' });
        flushFrames();
        expect(world.classList.contains('is-horizontal')).toBe(false);
    });

    it('zooms about the middle, and only a zoomed-out big plan drops its detail', () => {
        planSize = { v: { w: 2400, h: 6000 }, h: { w: 6000, h: 2400 } };

        const { view, stage } = setup();
        const world = stage.querySelector<HTMLElement>('.wms-plan__world')!;

        flushFrames();

        const scale = () =>
            Number(/scale\(([\d.]+)\)/.exec(world.style.transform)![1]);
        const fitted = scale();

        expect(world.classList.contains('is-coarse')).toBe(true);

        view.zoomBy(0.5); // cannot go out past the fit
        expect(scale()).toBeCloseTo(fitted, 5);

        for (let i = 0; i < 6; i++) {
            view.zoomBy(1.35);
        }

        expect(scale()).toBeGreaterThan(fitted);
        expect(world.classList.contains('is-coarse')).toBe(false);

        view.fit();
        expect(scale()).toBeCloseTo(fitted, 5);
        expect(world.classList.contains('is-coarse')).toBe(true);
    });

    it('paints the light look while zooming and the full detail once it settles', () => {
        vi.useFakeTimers();

        try {
            const { view, stage } = setup();
            const world = stage.querySelector<HTMLElement>('.wms-plan__world')!;

            flushFrames();
            expect(world.classList.contains('is-zooming')).toBe(false);

            view.zoomBy(1.2);
            expect(world.classList.contains('is-zooming')).toBe(true);

            vi.advanceTimersByTime(100);
            view.zoomBy(1.2); // another step restarts the wait
            vi.advanceTimersByTime(100);
            expect(world.classList.contains('is-zooming')).toBe(true);

            vi.advanceTimersByTime(100);
            expect(world.classList.contains('is-zooming')).toBe(false);

            view.fit();
            expect(world.classList.contains('is-zooming')).toBe(false);
        } finally {
            vi.useRealTimers();
        }
    });

    it('centres on a slot to reveal it', () => {
        const { view, stage, warehouse } = setup();
        const world = stage.querySelector<HTMLElement>('.wms-plan__world')!;

        flushFrames();
        stage.querySelectorAll<HTMLElement>('.wms-tile').forEach((tile) => {
            tile.getBoundingClientRect = () =>
                ({ left: 100, top: 200, width: 60, height: 70 }) as DOMRect;
        });
        world.getBoundingClientRect = () => ({ left: 0, top: 0 }) as DOMRect;

        view.reveal(warehouse.slots[0]);

        expect(world.style.transform).toMatch(/translate\(/);
        expect(world.classList.contains('is-animating')).toBe(true);
    });

    it('rebuilds when the warehouse changes', () => {
        const { stage, state } = setup(4, 6);

        state.set({ warehouse: makeWarehouse(2, 3, 2) });

        expect(stage.querySelectorAll('.wms-tile')).toHaveLength(6);
    });

    it('relabels in the new language', () => {
        const { stage, state } = setup();
        const texts: Record<string, string> = {};
        const view2 = new PlanView(
            document.createElement('div'),
            state,
            {
                openRack: vi.fn(),
                setChromeAway: vi.fn(),
                cycleOrientation: vi.fn(),
            },
            (k) => texts[k] ?? k,
        );

        texts.aisle = 'الممر';
        view2.mount();
        state.set({ locale: 'ar' });

        expect(stage.querySelector('.wms-pair__sign')?.textContent).toBe(
            'aisle A - B',
        );
        view2.dispose();
    });

    it('cleans up on dispose', () => {
        const { view, stage, state } = setup();

        view.dispose();
        state.set({ floor: 3 });

        expect(stage.querySelector('.wms-plan__world')).toBeNull();
        expect(stage.querySelector('.wms-plan__level')).toBeNull();
    });

    it('keeps product names out of the markup', () => {
        const { stage } = setup();

        expect(stage.innerHTML).not.toContain('Basmati');
    });
});

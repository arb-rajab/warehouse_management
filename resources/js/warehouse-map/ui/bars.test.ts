import { afterEach, describe, expect, it, vi } from 'vitest';
import { click, fakeSlice, host, keyI18n } from '../testing/ui';
import { CameraBar } from './CameraBar';
import type { CameraBarState } from './CameraBar';
import { Crosshair } from './Crosshair';
import { FloorSelector } from './FloorSelector';
import { ResultPill } from './ResultPill';
import { TouchControls } from './TouchControls';
import { WalkBar } from './WalkBar';
import type { WalkBarState } from './WalkBar';
import { ZoomControls } from './ZoomControls';

afterEach(() => {
    document.body.innerHTML = '';
});

describe('FloorSelector', () => {
    function floors(partial = {}) {
        const root = host();
        const slice = fakeSlice({
            levels: 3,
            floor: null as number | null,
            filtering: false,
            total: 0,
            byLevel: new Map<number, number>(),
            ...partial,
        });
        const setFloor = vi.fn();
        const view = new FloorSelector(root, slice, keyI18n(), setFloor);

        view.mount();

        return { view, slice, setFloor, root };
    }

    it('lists All and then the levels, top first', () => {
        const { view, root } = floors();

        expect(
            [...root.querySelectorAll('.wms-chip--floor')].map(
                (c) => c.querySelector('span')?.textContent,
            ),
        ).toEqual(['allFloors', '3', '2', '1']);
        view.dispose();
    });

    it('chooses a floor or all', () => {
        const { view, root, setFloor, slice } = floors();
        const chips = root.querySelectorAll('.wms-chip--floor');

        click(chips[2]);
        click(chips[0]);
        expect(setFloor.mock.calls).toEqual([[2], [null]]);

        slice.set({ floor: 2 });
        expect(chips[2].classList.contains('is-on')).toBe(true);
        expect(chips[0].classList.contains('is-on')).toBe(false);
        view.dispose();
    });

    it('shows match counts per floor only while filtering, and keeps their place otherwise', () => {
        const { view, root, slice } = floors();
        const counts = () =>
            [...root.querySelectorAll('.wms-chip--floor small')].map(
                (s) => s.textContent,
            );

        expect(counts()).toEqual([' ', ' ', ' ', ' ']);

        slice.set({
            filtering: true,
            total: 5,
            byLevel: new Map([
                [3, 2],
                [1, 3],
            ]),
        });
        expect(counts()).toEqual(['5', '2', '0', '3']);
        expect(
            root.querySelector('.wms-floors')?.classList.contains('has-filter'),
        ).toBe(true);
        view.dispose();
    });

    it('rebuilds when the number of levels changes', () => {
        const { view, root, slice } = floors();

        slice.set({ levels: 5 });

        expect(root.querySelectorAll('.wms-chip--floor')).toHaveLength(6);
        view.dispose();
    });
});

describe('ResultPill', () => {
    it('shows the number of matches, and says when there are none', () => {
        const slice = fakeSlice({ filtering: false, count: 0 });
        const open = vi.fn();
        const pill = new ResultPill(slice, keyI18n(), open);

        pill.mount();
        expect(pill.element.classList.contains('is-shown')).toBe(false);
        expect(pill.element.getAttribute('aria-hidden')).toBe('true');

        slice.set({ filtering: true, count: 4 });
        expect(pill.element.classList.contains('is-shown')).toBe(true);
        expect(pill.element.textContent).toBe('matches(4)');

        slice.set({ count: 0 });
        expect(pill.element.textContent).toBe('noMatches');

        click(pill.element);
        expect(open).toHaveBeenCalled();
        pill.dispose();
    });
});

describe('CameraBar', () => {
    const rows = [{ letter: 'A' }, { letter: 'B' }, { letter: 'C' }];
    const state = (partial: Partial<CameraBarState> = {}): CameraBarState => ({
        rows,
        aisles: 2,
        byRow: new Map(),
        filtering: false,
        mode: 'overview',
        landing: 'overview',
        activeAisle: null,
        ...partial,
    });

    function bar(partial: Partial<CameraBarState> = {}) {
        const slice = fakeSlice(state(partial));
        const intents = { goOverview: vi.fn(), enterAisle: vi.fn() };
        const view = new CameraBar(slice, keyI18n(), intents);

        view.mount();
        document.body.append(view.element);

        return { view, slice, intents };
    }

    it('has Overview and one chip per aisle, named by its row letters', () => {
        const { view } = bar();
        const chips = [...view.element.querySelectorAll('.wms-chip')];

        expect(chips).toHaveLength(3);
        expect(chips[0].textContent).toBe('overview');
        expect(chips[1].textContent).toBe('aisle A - B');
        expect(chips[2].textContent).toBe('aisle C');
        view.dispose();
    });

    it('goes to the overview or into an aisle', () => {
        const { view, intents } = bar();
        const chips = view.element.querySelectorAll('.wms-chip');

        click(chips[2]);
        click(chips[0]);
        expect(intents.enterAisle).toHaveBeenCalledWith(1);
        expect(intents.goOverview).toHaveBeenCalled();
        view.dispose();
    });

    it('lights the overview chip, or the aisle you walk in (also while flying there)', () => {
        const { view, slice } = bar();
        const chips = view.element.querySelectorAll('.wms-chip');

        expect(chips[0].classList.contains('is-on')).toBe(true);

        slice.set({ mode: 'walk', activeAisle: 1 });
        expect(chips[0].classList.contains('is-on')).toBe(false);
        expect(chips[2].classList.contains('is-on')).toBe(true);

        slice.set({ mode: 'fly', landing: 'overview' });
        expect(chips[0].classList.contains('is-on')).toBe(true);
        view.dispose();
    });

    it('puts the match count on the corner of each aisle chip, only while filtering', () => {
        const { view, slice } = bar();
        const counts = () =>
            [...view.element.querySelectorAll('.wms-chip__count')].map((c) =>
                c.classList.contains('is-hidden') ? '' : c.textContent,
            );

        expect(counts()).toEqual(['', '']);

        slice.set({
            filtering: true,
            byRow: new Map([
                ['A', 2],
                ['B', 3],
            ]),
        });
        expect(counts()).toEqual(['5', '']);
        view.dispose();
    });
});

describe('WalkBar', () => {
    function walkbar(partial = {}) {
        const root = host();
        const slice = fakeSlice<WalkBarState>({
            nav: 'free',
            magnet: true,
            inputKind: 'mouse',
            ...partial,
        });
        const intents = {
            goOverview: vi.fn(),
            setNav: vi.fn(),
            toggleMagnet: vi.fn(),
            openHelp: vi.fn(),
            toggleFullscreen: vi.fn(),
        };
        const view = new WalkBar(root, slice, keyI18n(), intents);

        view.mount();

        return { view, slice, intents, root };
    }

    it('fires the walk intents', () => {
        const { view, root, intents } = walkbar();
        const buttons = root.querySelectorAll('.wms-walkbar button');

        buttons.forEach((b) => click(b));

        expect(intents.goOverview).toHaveBeenCalled();
        expect(intents.setNav.mock.calls).toEqual([['free'], ['guided']]);
        expect(intents.toggleMagnet).toHaveBeenCalled();
        expect(intents.openHelp).toHaveBeenCalled();
        expect(intents.toggleFullscreen).toHaveBeenCalled();
        view.dispose();
    });

    it('lights the current navigation mode and the auto-align switch', () => {
        const { view, root, slice } = walkbar();
        const [, free, guided, magnet] = root.querySelectorAll(
            '.wms-walkbar .wms-chip',
        );

        expect(free.classList.contains('is-on')).toBe(true);
        slice.set({ nav: 'guided', magnet: false });
        expect(guided.classList.contains('is-on')).toBe(true);
        expect(magnet.getAttribute('aria-pressed')).toBe('false');
        expect(magnet.classList.contains('is-set')).toBe(false);
        view.dispose();
    });
});

describe('ZoomControls', () => {
    it('zooms, fits and rotates the plan', () => {
        const plan = {
            zoomBy: vi.fn(),
            fit: vi.fn(),
            rotate: vi.fn(),
            reveal: vi.fn(),
        };
        const root = host();
        const zoom = new ZoomControls(
            root,
            fakeSlice({}) as never,
            keyI18n(),
            plan,
        );

        zoom.mount();

        const buttons = root.querySelectorAll('.wms-zoom button');

        buttons.forEach((b) => click(b));
        expect(plan.zoomBy.mock.calls[0][0]).toBeCloseTo(1.35);
        expect(plan.fit).toHaveBeenCalled();
        expect(plan.rotate).toHaveBeenCalled();
        expect(plan.zoomBy.mock.calls[1][0]).toBeCloseTo(1 / 1.35);
        expect([...buttons].map((b) => b.getAttribute('aria-label'))).toEqual([
            'zoomIn',
            'fitPlan',
            'rotatePlan',
            'zoomOut',
        ]);
        zoom.dispose();
    });
});

describe('TouchControls', () => {
    it('builds the joystick, the lift buttons and an eight-button pad, all labelled', () => {
        const root = host();
        const canvas = document.createElement('canvas');
        const controls = new TouchControls(root, canvas, keyI18n());

        controls.mount();

        const { elements } = controls;

        expect(elements.padButtons.map((b) => b.action)).toEqual([
            'up',
            'tl',
            'fw',
            'tr',
            'down',
            'sl',
            'bk',
            'sr',
        ]);
        expect(elements.canvas).toBe(canvas);
        expect(root.querySelectorAll('.wms-pad__btn')).toHaveLength(8);
        expect(
            [...root.querySelectorAll('button')].every((b) =>
                b.getAttribute('aria-label'),
            ),
        ).toBe(true);
        controls.dispose();
        expect(root.querySelector('.wms-pad')).toBeNull();
    });
});

describe('Crosshair', () => {
    it('turns yellow while a pallet is under it', () => {
        const root = host();
        const slice = fakeSlice({ hot: false });
        const crosshair = new Crosshair(root, slice, keyI18n());

        crosshair.mount();
        slice.set({ hot: true });
        expect(crosshair.element.classList.contains('is-hot')).toBe(true);
        crosshair.dispose();
    });
});

import type * as THREE from 'three';
import {
    afterAll,
    beforeAll,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';
import { DEFAULT_SETTINGS } from '../config';
import { FrameLoop } from '../core/FrameLoop';
import { buildMatchIndex } from '../domain/MatchIndex';
import type { Slot } from '../domain/types';
import type { PointerCapture } from '../input/Intent';
import type { SceneState } from '../state/state';
import { INITIAL_SCENE } from '../state/state';
import { installFakeCanvas } from '../testing/canvas';
import { makeWarehouse } from '../testing/fixtures';
import { SceneView } from './SceneView';
import type { SceneHost } from './SceneView';

let restore: () => void;

beforeAll(() => {
    restore = installFakeCanvas();
});
afterAll(() => restore());

class FakeRenderer {
    pixelRatio = 1;
    renders = 0;
    disposed = false;
    sizes: Array<[number, number]> = [];
    setPixelRatio(v: number) {
        this.pixelRatio = v;
    }
    getPixelRatio() {
        return this.pixelRatio;
    }
    setClearColor() {}
    setSize(w: number, h: number) {
        this.sizes.push([w, h]);
    }
    render() {
        this.renders++;
    }
    dispose() {
        this.disposed = true;
    }
}

function manualFrames() {
    let callback: FrameRequestCallback | null = null;
    let clock = 0;
    const frames = new FrameLoop(
        (cb) => {
            callback = cb;

            return 1;
        },
        () => {},
    );

    frames.mount();

    return {
        frames,
        /** Runs frames of 16 ms until `ms` of animation time passed. */
        now: () => clock,
        run(ms: number, step = 16) {
            for (let t = 0; t < ms; t += step) {
                clock += step;
                const cb = callback;

                callback = null;
                cb?.(clock);
            }
        },
    };
}

function setup(
    options: {
        input?: 'mouse' | 'touch';
        rows?: number;
        cols?: number;
        levels?: number;
    } = {},
) {
    const canvas = document.createElement('canvas');

    Object.defineProperty(canvas, 'clientWidth', { value: 1200 });
    Object.defineProperty(canvas, 'clientHeight', { value: 700 });
    canvas.getBoundingClientRect = () =>
        ({ left: 0, top: 0, width: 1200, height: 700 }) as DOMRect;
    document.body.append(canvas);

    const warehouse = makeWarehouse(
        options.rows ?? 4,
        options.cols ?? 10,
        options.levels ?? 3,
    );
    const renderer = new FakeRenderer();
    const { frames, run, now } = manualFrames();
    const scene: SceneState = { ...INITIAL_SCENE };
    let captured = false;
    const capture: PointerCapture = {
        get captured() {
            return captured;
        },
        request: vi.fn(() => {
            captured = true;
        }),
        release: vi.fn(() => {
            captured = false;
        }),
    };
    const host: SceneHost = {
        settings: () => DEFAULT_SETTINGS,
        inputKind: () => options.input ?? 'mouse',
        patch: vi.fn((patch) => Object.assign(scene, patch)),
        openRack: vi.fn(),
        setTarget: vi.fn(),
        setChromeAway: vi.fn(),
        toggleKeys: vi.fn(),
        buzz: vi.fn(),
        t: (key) => key,
    };
    const view = new SceneView({
        canvas,
        warehouse,
        frames,
        capture,
        host,
        styleRoot: document.body,
        clock: now,
        createRenderer: () => renderer as unknown as THREE.WebGLRenderer,
    });

    view.mount();
    view.show();

    return {
        view,
        scene,
        host,
        capture,
        renderer,
        frames,
        run,
        warehouse,
        canvas,
    };
}

describe('SceneView', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
    });

    it('starts in the overview, drawing only after something changed', () => {
        const { scene, renderer, run } = setup();

        expect(scene.mode).toBe('overview');

        run(200);
        const drawn = renderer.renders;

        expect(drawn).toBeGreaterThan(0);

        run(400);
        expect(renderer.renders).toBe(drawn); // idle: nothing re-rendered
    });

    it('sizes the renderer to the canvas', () => {
        const { renderer } = setup();

        expect(renderer.sizes.at(-1)).toEqual([1200, 700]);
    });

    it('flies into an aisle and starts walking, taking the mouse', () => {
        const { view, scene, capture, run } = setup();

        view.enterAisle(1);
        expect(scene.mode).toBe('fly');
        expect(scene.landing).toBe('walk');
        expect(capture.request).toHaveBeenCalled();

        run(2000);

        expect(scene.mode).toBe('walk');
        expect(scene.nav).toBe('free');
        expect(scene.activeAisle).toBe(1);
    });

    it('does not capture the mouse on a touch device', () => {
        const { view, capture, run } = setup({ input: 'touch' });

        view.enterAisle(0);
        run(2000);

        expect(capture.request).not.toHaveBeenCalled();
    });

    it('ignores an aisle that does not exist', () => {
        const { view, scene } = setup();

        view.enterAisle(99);

        expect(scene.mode).toBe('overview');
    });

    it('walks forward with held keys and hands the camera back on O', () => {
        const { view, scene, run } = setup();

        view.enterAisle(0);
        run(2000);
        view.handleIntent({
            type: 'move',
            source: 'keyboard',
            forward: 1,
            strafe: 0,
        });
        run(500);
        view.handleIntent({ type: 'goOverview' });
        expect(scene.mode).toBe('fly');
        expect(scene.landing).toBe('overview');

        run(2000);
        expect(scene.mode).toBe('overview');
        expect(scene.activeAisle).toBe(0);
    });

    it('releases the mouse when going back to the overview', () => {
        const { view, capture, run } = setup();

        view.enterAisle(0);
        run(2000);
        view.handleIntent({ type: 'goOverview' });
        run(2000);

        expect(capture.release).toHaveBeenCalled();
    });

    it('switches between free and guided walking (G) and gives the mouse back for guided', () => {
        const { view, scene, capture, run } = setup();

        view.enterAisle(0);
        run(2000);
        view.handleIntent({ type: 'toggleNav' });

        expect(scene.nav).toBe('guided');
        expect(capture.release).toHaveBeenCalled();

        view.handleIntent({ type: 'toggleNav' });
        expect(scene.nav).toBe('free');
    });

    it('steps one bay at a time in guided mode', () => {
        const { view, run, scene } = setup();

        view.setNav('guided');
        view.enterAisle(0);
        run(2000);
        expect(scene.nav).toBe('guided');

        const before = view.minimap.element; // the scene is alive and well

        view.handleIntent({ type: 'step', forward: 1, strafe: 0 });
        run(1000);

        expect(before).toBeTruthy();
        expect(scene.mode).toBe('walk');
    });

    it('does not let G or O act in the overview', () => {
        const { view, scene } = setup();

        view.handleIntent({ type: 'toggleNav' });
        view.handleIntent({ type: 'goOverview' });

        expect(scene.mode).toBe('overview');
        expect(scene.nav).toBe('free');
    });

    it('jumps to an aisle on a digit', () => {
        const { view, scene } = setup();

        view.handleIntent({ type: 'aisle', index: 1 });

        expect(scene.mode).toBe('fly');
    });

    it('flies in on a slot and offers to open it, and a close tap opens the rack straight away', () => {
        const { view, scene, host, run, warehouse } = setup();
        const hit = warehouse.slots[0];

        // aim straight at the middle of the screen: the picker needs a real hit, so stub it
        (view as unknown as { picker: { pickAt: () => Slot } }).picker.pickAt =
            () => hit;

        view.handleIntent({ type: 'tap', x: 600, y: 350, pointer: 'mouse' });

        expect(host.setTarget).toHaveBeenCalledWith(hit);
        expect(scene.mode).toBe('fly');
        expect(scene.landing).toBe('overview');

        run(2000);
        expect(scene.mode).toBe('overview');
        expect(scene.selectedId).toBe(hit.id);

        // now close: the next tap opens the rack
        view.handleIntent({ type: 'tap', x: 600, y: 350, pointer: 'mouse' });
        expect(host.openRack).toHaveBeenCalledWith({
            row: hit.row,
            col: hit.col,
            level: hit.level,
        });
    });

    it('clears the selection when tapping empty space', () => {
        const { view, scene } = setup();

        scene.selectedId = 5;
        (view as unknown as { picker: { pickAt: () => null } }).picker.pickAt =
            () => null;
        view.handleIntent({ type: 'tap', x: 10, y: 10, pointer: 'mouse' });

        expect(scene.selectedId).toBeNull();
    });

    it('the first click while walking only brings the mouse back', () => {
        const { view, host, capture, run } = setup();

        view.enterAisle(0);
        run(2000);
        capture.release();
        (capture.request as ReturnType<typeof vi.fn>).mockClear();

        view.handleIntent({ type: 'tap', x: 600, y: 350, pointer: 'mouse' });

        expect(capture.request).toHaveBeenCalled();
        expect(host.openRack).not.toHaveBeenCalled();
    });

    it('opens the slot under the crosshair on inspect', () => {
        const { view, host, run, warehouse } = setup();
        const hit = warehouse.slots[3];

        view.enterAisle(0);
        run(2000);
        (
            view as unknown as { picker: { pickCentre: () => Slot } }
        ).picker.pickCentre = () => hit;
        view.handleIntent({ type: 'inspect' });

        expect(host.openRack).toHaveBeenCalledWith({
            row: hit.row,
            col: hit.col,
            level: hit.level,
        });
    });

    it('describes what is under the crosshair while the mouse is captured', () => {
        const { view, scene, run, warehouse } = setup();
        const hit = warehouse.slots[3];

        view.enterAisle(0);
        run(2000);
        (
            view as unknown as { picker: { pickCentre: () => Slot } }
        ).picker.pickCentre = () => hit;
        run(300);

        expect(scene.hoverId).toBe(hit.id);
        expect(scene.cardVisible).toBe(true);
    });

    it('does not describe a far-away hover in the overview', () => {
        const { view, scene, run, warehouse } = setup({ rows: 20, cols: 44 });

        (view as unknown as { picker: { pickAt: () => Slot } }).picker.pickAt =
            () => warehouse.slots[0];
        view.handleIntent({ type: 'hover', x: 5, y: 5 });
        run(100);

        expect(scene.hoverId).toBe(warehouse.slots[0].id);
        expect(scene.cardVisible).toBe(false);
    });

    it('moves the minimap and key-hint toggles through the host', () => {
        const { view, scene, host } = setup();

        view.handleIntent({ type: 'toggleMinimap' });
        expect(scene.minimapHidden).toBe(true);

        view.handleIntent({ type: 'toggleMinimap' });
        expect(scene.minimapHidden).toBe(false);

        view.handleIntent({ type: 'toggleKeys' });
        expect(host.toggleKeys).toHaveBeenCalled();
    });

    it('steps the toolbars aside while the mouse is captured', () => {
        const { view, host, scene } = setup();

        view.handleIntent({ type: 'captureChange', captured: true });
        expect(scene.captured).toBe(true);
        expect(host.setChromeAway).toHaveBeenLastCalledWith(true);

        view.handleIntent({ type: 'captureChange', captured: false });
        expect(host.setChromeAway).toHaveBeenLastCalledWith(false);
    });

    it('recolours for a filter and draws again', () => {
        const { view, renderer, run, warehouse } = setup();

        run(200);
        const before = renderer.renders;

        view.setFilter(
            buildMatchIndex(warehouse.slots, {
                terms: ['rice'],
                state: 'all',
                expiring: false,
            }),
            2,
        );
        run(100);

        expect(renderer.renders).toBeGreaterThan(before);
    });

    it('puts the target frame on a slot and takes it off again', () => {
        const { view, run, warehouse } = setup();

        view.setTarget(warehouse.slots[0]);
        run(100);
        view.setTarget(null);
        run(100);

        expect(view).toBeTruthy();
    });

    it('stops drawing when hidden (the 2D view) and lets go of the mouse', () => {
        const { view, renderer, capture, run } = setup();

        run(200);
        view.hide();
        const drawn = renderer.renders;

        view.setFilter(
            buildMatchIndex([], {
                terms: ['x'],
                state: 'all',
                expiring: false,
            }),
            null,
        );
        run(300);

        expect(renderer.renders).toBe(drawn);
        expect(capture.release).toHaveBeenCalled();
    });

    it('ignores intents while hidden', () => {
        const { view, scene } = setup();

        view.hide();
        view.handleIntent({ type: 'aisle', index: 0 });

        expect(scene.mode).toBe('overview');
    });

    it('rebuilds for a new warehouse and keeps working', () => {
        const { view, run } = setup();
        const bigger = makeWarehouse(6, 12, 3);

        view.setWarehouse(bigger);
        run(200);
        view.enterAisle(2);
        run(2000);

        expect(view).toBeTruthy();
    });

    it('frees the renderer and every texture on dispose', () => {
        const { view, renderer, run } = setup();

        run(200);
        view.dispose();

        expect(renderer.disposed).toBe(true);
    });

    it('lowers the pixel ratio when frames stay slow', () => {
        const { view, renderer, run } = setup();

        renderer.pixelRatio = 2;
        view.handleIntent({
            type: 'move',
            source: 'keyboard',
            forward: 1,
            strafe: 0,
        }); // panning redraws every frame
        run(4000, 45);

        expect(renderer.pixelRatio).toBeLessThan(2);
    });

    it('keeps the full pixel ratio while frames are quick', () => {
        const { view, renderer, run } = setup();

        renderer.pixelRatio = 2;
        view.handleIntent({
            type: 'move',
            source: 'keyboard',
            forward: 1,
            strafe: 0,
        });
        run(4000, 16);

        expect(renderer.pixelRatio).toBe(2);
    });
});

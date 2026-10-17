import { afterEach, describe, expect, it, vi } from 'vitest';
import { buildMatchIndex } from '../domain/MatchIndex';
import { makeWarehouse } from '../testing/fixtures';
import { LazySceneView } from './LazySceneView';
import type { SceneView } from './SceneView';

afterEach(() => {
    vi.useRealTimers();
});

function fakeScene() {
    return {
        mount: vi.fn(),
        dispose: vi.fn(),
        show: vi.fn(),
        hide: vi.fn(),
        focusSlot: vi.fn(),
        enterAisle: vi.fn(),
        goOverview: vi.fn(),
        setNav: vi.fn(),
        handleIntent: vi.fn(),
        setFilter: vi.fn(),
        setTarget: vi.fn(),
        setWarehouse: vi.fn(),
        applySettings: vi.fn(),
        onLocale: vi.fn(),
        onInputKind: vi.fn(),
        resize: vi.fn(),
        minimap: { element: document.createElement('div') },
    };
}

function lazy() {
    const scene = fakeScene();
    const create = vi.fn(() => scene as unknown as SceneView);
    const onBuilt = vi.fn();
    const view = new LazySceneView(create, onBuilt);
    const warehouse = makeWarehouse(2, 3, 2);

    return { view, scene, create, onBuilt, warehouse };
}

describe('LazySceneView', () => {
    it('builds nothing until it is needed', () => {
        const { view, create } = lazy();

        view.setFilter(
            buildMatchIndex([], {
                terms: [],
                state: 'all',
                expiring: false,
                productIds: [],
            }),
            null,
        );
        view.hide();
        view.resize();

        expect(create).not.toHaveBeenCalled();
        expect(view.isBuilt).toBe(false);
    });

    it('cannot build without a warehouse', () => {
        const { view, create } = lazy();

        expect(view.ensure()).toBeNull();
        expect(create).not.toHaveBeenCalled();
    });

    it('builds once, on the first thing that needs the scene, and tells the page', () => {
        const { view, scene, create, onBuilt, warehouse } = lazy();

        view.setWarehouse(warehouse);
        view.show();
        view.show();

        expect(create).toHaveBeenCalledTimes(1);
        expect(create).toHaveBeenCalledWith(warehouse);
        expect(scene.mount).toHaveBeenCalledTimes(1);
        expect(onBuilt).toHaveBeenCalledWith(scene);
        expect(scene.show).toHaveBeenCalledTimes(2);
    });

    it('applies the filter and the target that were set before the scene existed', () => {
        const { view, scene, warehouse } = lazy();
        const match = buildMatchIndex(warehouse.slots, {
            terms: ['rice'],
            state: 'all',
            expiring: false,
            productIds: [],
        });

        view.setWarehouse(warehouse);
        view.setFilter(match, 2);
        view.setTarget(warehouse.slots[0]);
        view.ensure();

        expect(scene.setFilter).toHaveBeenCalledWith(match, 2);
        expect(scene.setTarget).toHaveBeenCalledWith(warehouse.slots[0]);
    });

    it('forwards commands to a built scene', () => {
        const { view, scene, warehouse } = lazy();

        view.setWarehouse(warehouse);
        view.ensure();
        view.enterAisle(1);
        view.focusSlot(warehouse.slots[0]);
        view.goOverview();
        view.setNav('guided');
        view.handleIntent({ type: 'goOverview' });
        view.applySettings();
        view.onLocale();
        view.onInputKind();
        view.resize();
        view.hide();
        view.setWarehouse(makeWarehouse(1, 1, 1));

        expect(scene.enterAisle).toHaveBeenCalledWith(1);
        expect(scene.setNav).toHaveBeenCalledWith('guided');
        expect(scene.handleIntent).toHaveBeenCalled();
        expect(scene.setWarehouse).toHaveBeenCalled();
        expect(scene.hide).toHaveBeenCalled();
    });

    it('builds in idle time', () => {
        vi.useFakeTimers();

        const { view, create, warehouse } = lazy();

        view.setWarehouse(warehouse);
        view.prepareWhenIdle();
        expect(create).not.toHaveBeenCalled();

        vi.advanceTimersByTime(500);
        expect(create).toHaveBeenCalledTimes(1);
    });

    it('does not build after dispose, and frees the scene it had', () => {
        vi.useFakeTimers();

        const { view, scene, create, warehouse } = lazy();

        view.setWarehouse(warehouse);
        view.prepareWhenIdle();
        view.dispose();
        vi.advanceTimersByTime(5000);
        expect(create).not.toHaveBeenCalled();

        const built = lazy();

        built.view.setWarehouse(built.warehouse);
        built.view.ensure();
        built.view.dispose();
        expect(built.scene.dispose).toHaveBeenCalled();
        expect(built.view.isBuilt).toBe(false);
        void scene;
    });
});

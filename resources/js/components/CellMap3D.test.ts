import { mount } from '@vue/test-utils';
import * as THREE from 'three';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { CELL_STATE_COLOR } from '@/lib/cellStateColor';
import { i18n, t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import {
    boundsForWarehouse,
    cellWorldZ,
    EYE_HEIGHT,
    flatWorldY,
    miniMapRowLeftPercents,
    orbitRangeForBounds,
    rowWorldX,
    WALK_SPEED,
    YAW_DRAG_SENSITIVITY,
} from '@/lib/mapWalker';
import type { CellMap3DBand, CellMap3DItem } from '@/types/admin';
import CellMap3D from './CellMap3D.vue';
import CellSlot from './CellSlot.vue';

vi.mock('three', () => {
    class Vector3 {
        x = 0;
        y = 0;
        z = 0;

        set(x: number, y: number, z: number) {
            this.x = x;
            this.y = y;
            this.z = z;

            return this;
        }

        copy(v: Vector3) {
            this.x = v.x;
            this.y = v.y;
            this.z = v.z;

            return this;
        }
    }

    class Object3D {
        position = new Vector3();
        rotation = { x: 0, y: 0, z: 0 };
        name = '';
        visible = true;
        userData: Record<string, unknown> = {};
        children: Object3D[] = [];

        add(...items: Object3D[]) {
            this.children.push(...items);
        }

        remove(...items: Object3D[]) {
            this.children = this.children.filter((c) => !items.includes(c));
        }

        traverse(callback: (obj: Object3D) => void) {
            callback(this);
            this.children.forEach((c) => c.traverse(callback));
        }
    }

    class Vector2 {
        x: number;
        y: number;

        constructor(x = 0, y = 0) {
            this.x = x;
            this.y = y;
        }
    }

    class Raycaster {
        setFromCamera = vi.fn();
        intersectObjects = vi.fn(
            () => [] as Array<{ object: Object3D; instanceId?: number }>,
        );

        constructor() {
            registry.raycasters.push(this);
        }
    }

    class Scene extends Object3D {
        background: unknown;
    }

    class Group extends Object3D {}

    class Mesh extends Object3D {
        geometry: unknown;
        material: unknown;

        constructor(geometry: unknown, material: unknown) {
            super();
            this.geometry = geometry;
            this.material = material;
            registry.meshes.push(this);
        }
    }

    class LineSegments extends Object3D {
        geometry: unknown;
        material: unknown;

        constructor(geometry: unknown, material: unknown) {
            super();
            this.geometry = geometry;
            this.material = material;
            registry.lineSegments.push(this);
        }
    }

    class Matrix4 {
        x = 0;
        y = 0;
        z = 0;

        makeTranslation(x: number, y: number, z: number) {
            this.x = x;
            this.y = y;
            this.z = z;

            return this;
        }
    }

    /**
     * Models three's real `InstancedMesh` bounding-sphere semantics, because
     * they are what makes instances disappear / stop being clickable:
     * `computeBoundingSphere()` unions ONLY the first `count` instance
     * matrices, the result is cached on the mesh, and NOTHING in three
     * invalidates it — not `setMatrixAt`, not assigning `count`. Both
     * consumers (the renderer's frustum test below, and
     * `InstancedMesh.raycast`, mirrored by `sphereGatedCellHits`) do
     * `if (this.boundingSphere === null) this.computeBoundingSphere()`, so a
     * sphere computed on the first frame is the one used forever unless the
     * component clears it. Instances are unioned as points (a real box's
     * extent would only grow the sphere), which keeps this conservative:
     * anything it reports as out of bounds is out of bounds for real three
     * too.
     */
    class InstancedMesh extends Object3D {
        geometry: unknown;
        material: unknown;
        count: number;
        instanceMatrix = { needsUpdate: false };
        matrices: Matrix4[];
        frustumCulled = true;
        boundingSphere: {
            x: number;
            y: number;
            z: number;
            radius: number;
        } | null = null;
        drawnInLastFrame = false;
        instanceColor: { needsUpdate: boolean } | null = null;
        colors: (Color | undefined)[] = [];

        constructor(geometry: unknown, material: unknown, count: number) {
            super();
            this.geometry = geometry;
            this.material = material;
            this.count = count;
            this.matrices = new Array(count);
            registry.instancedMeshes.push(this);
        }

        setMatrixAt(index: number, matrix: Matrix4) {
            this.matrices[index] = matrix;
        }

        getMatrixAt(index: number, target: Matrix4) {
            const matrix = this.matrices[index];

            if (matrix) {
                target.x = matrix.x;
                target.y = matrix.y;
                target.z = matrix.z;
            }
        }

        setColorAt(index: number, color: Color) {
            if (!this.instanceColor) {
                this.instanceColor = { needsUpdate: false };
            }

            this.colors[index] = color;
        }

        getColorAt(index: number, target: Color) {
            const color = this.colors[index];

            if (color) {
                target.value = color.value;
            }
        }

        computeBoundingSphere() {
            const sphere = { x: 0, y: 0, z: 0, radius: -1 };

            for (let index = 0; index < this.count; index += 1) {
                const matrix = this.matrices[index];

                if (!matrix) {
                    continue;
                }

                if (sphere.radius < 0) {
                    sphere.x = matrix.x;
                    sphere.y = matrix.y;
                    sphere.z = matrix.z;
                    sphere.radius = 0;

                    continue;
                }

                const distance = Math.hypot(
                    matrix.x - sphere.x,
                    matrix.y - sphere.y,
                    matrix.z - sphere.z,
                );

                if (distance <= sphere.radius) {
                    continue;
                }

                const grownRadius = (sphere.radius + distance) / 2;
                const ratio = (grownRadius - sphere.radius) / distance;
                sphere.x += (matrix.x - sphere.x) * ratio;
                sphere.y += (matrix.y - sphere.y) * ratio;
                sphere.z += (matrix.z - sphere.z) * ratio;
                sphere.radius = grownRadius;
            }

            this.boundingSphere = sphere;
        }
    }

    class AmbientLight extends Object3D {}
    class DirectionalLight extends Object3D {}
    class Color {
        value: unknown;

        constructor(value?: unknown) {
            this.value = value;
        }
    }

    class BoxGeometry {
        dispose = vi.fn();
    }
    class PlaneGeometry {
        dispose = vi.fn();
    }
    class EdgesGeometry {
        dispose = vi.fn();
    }

    class MeshStandardMaterial {
        color: unknown;
        transparent: unknown;
        opacity: unknown;
        dispose = vi.fn();

        constructor(options?: {
            color?: unknown;
            transparent?: unknown;
            opacity?: unknown;
        }) {
            this.color = options?.color;
            this.transparent = options?.transparent;
            this.opacity = options?.opacity;
        }
    }

    class LineBasicMaterial {
        color: unknown;
        dispose = vi.fn();

        constructor(options?: { color?: unknown }) {
            this.color = options?.color;
        }
    }

    class PerspectiveCamera extends Object3D {
        fov: number;
        aspect: number;
        near: number;
        far: number;
        lookAt = vi.fn();
        updateProjectionMatrix = vi.fn();

        constructor(fov: number, aspect: number, near: number, far: number) {
            super();
            this.fov = fov;
            this.aspect = aspect;
            this.near = near;
            this.far = far;
            registry.cameras.push(this);
        }
    }

    class WebGLRenderer {
        domElement = document.createElement('canvas');
        setSize = vi.fn();
        dispose = vi.fn();

        /**
         * Stands in for `WebGLRenderer.projectObject` → `Frustum
         * .intersectsObject`: an object whose `frustumCulled` is left at
         * three's `true` default is drawn only if its (lazily computed, then
         * cached) bounding sphere intersects the view frustum. The frustum
         * itself is stood in for by "the camera sees the whole warehouse", so
         * the only thing that culls a mesh here is an empty cached sphere —
         * which is exactly the stale-sphere failure this models. Recording it
         * per frame lets a test assert that a state's boxes are still drawn
         * after a cell moves into that state.
         */
        render = vi.fn((scene: Object3D) => {
            scene.traverse((object) => {
                if (!(object instanceof InstancedMesh)) {
                    return;
                }

                if (!object.frustumCulled) {
                    object.drawnInLastFrame = true;

                    return;
                }

                if (object.boundingSphere === null) {
                    object.computeBoundingSphere();
                }

                object.drawnInLastFrame = object.boundingSphere!.radius >= 0;
            });
        });

        constructor() {
            registry.renderers.push(this);
        }
    }

    const registry: {
        cameras: PerspectiveCamera[];
        renderers: WebGLRenderer[];
        meshes: Mesh[];
        lineSegments: LineSegments[];
        instancedMeshes: InstancedMesh[];
        raycasters: Raycaster[];
    } = {
        cameras: [],
        renderers: [],
        meshes: [],
        lineSegments: [],
        instancedMeshes: [],
        raycasters: [],
    };

    return {
        __registry: registry,
        Scene,
        Group,
        Mesh,
        LineSegments,
        InstancedMesh,
        Matrix4,
        AmbientLight,
        DirectionalLight,
        Color,
        PerspectiveCamera,
        WebGLRenderer,
        BoxGeometry,
        PlaneGeometry,
        EdgesGeometry,
        MeshStandardMaterial,
        LineBasicMaterial,
        Vector2,
        Raycaster,
    };
});

type Registry = {
    cameras: Array<{
        position: { x: number; y: number; z: number };
        lookAt: ReturnType<typeof vi.fn>;
        aspect: number;
        updateProjectionMatrix: ReturnType<typeof vi.fn>;
    }>;
    renderers: Array<{
        dispose: ReturnType<typeof vi.fn>;
        setSize: ReturnType<typeof vi.fn>;
    }>;
    meshes: Array<{
        name: string;
        position: { x: number; y: number; z: number };
        material: { color: unknown; dispose: ReturnType<typeof vi.fn> };
        userData: Record<string, unknown>;
    }>;
    lineSegments: Array<{
        name: string;
        position: { x: number; y: number; z: number };
        visible: boolean;
        material: { color: unknown };
    }>;
    instancedMeshes: Array<{
        name: string;
        count: number;
        matrices: Array<{ x: number; y: number; z: number }>;
        colors: Array<{ value: unknown } | undefined>;
        material: {
            color: unknown;
            transparent: unknown;
            opacity: unknown;
            dispose: ReturnType<typeof vi.fn>;
        };
        frustumCulled: boolean;
        boundingSphere: {
            x: number;
            y: number;
            z: number;
            radius: number;
        } | null;
        drawnInLastFrame: boolean;
        computeBoundingSphere: () => void;
    }>;
    raycasters: Array<{
        setFromCamera: ReturnType<typeof vi.fn>;
        intersectObjects: ReturnType<typeof vi.fn>;
    }>;
};

function registry(): Registry {
    return (THREE as unknown as { __registry: Registry }).__registry;
}

function lastCamera() {
    const cameras = registry().cameras;

    return cameras[cameras.length - 1];
}

function lastRaycaster() {
    const raycasters = registry().raycasters;

    return raycasters[raycasters.length - 1];
}

/**
 * A raycaster-intersection-shaped hit for the given cell — cell boxes are
 * `THREE.InstancedMesh` instances (one per state, not one per cell), so a
 * "hit" is the state's mesh plus the `instanceId` slot that cell currently
 * occupies (identified by its world Y/Z, since all tests below stand it in
 * row A / world X 0). Every test below stubs `intersectObjects` to return
 * `[cellBoxMesh(n)]` directly, matching what a real raycast hit looks like.
 */
function cellBoxMesh(
    cellNumber: number,
    flatNumber = 1,
): { object: unknown; instanceId: number } {
    const targetY = flatWorldY(flatNumber);
    const targetZ = cellWorldZ(cellNumber);

    for (const mesh of registry().instancedMeshes) {
        if (mesh.name !== 'cell-box') {
            continue;
        }

        const instanceId = mesh.matrices.findIndex(
            (matrix) => matrix && matrix.y === targetY && matrix.z === targetZ,
        );

        if (instanceId !== -1) {
            return { object: mesh, instanceId };
        }
    }

    throw new Error(`No cell-box instance found for cell ${cellNumber}`);
}

type MockInstancedMesh = Registry['instancedMeshes'][number];

/**
 * Mirrors three's `InstancedMesh.raycast` gate for a ray aimed at the centre of
 * the given cell's box: `raycast()` opens with
 * `if (this.boundingSphere === null) this.computeBoundingSphere();` followed by
 * `if (raycaster.ray.intersectsSphere(_sphere) === false) return;`, so a mesh
 * whose cached sphere no longer covers an instance yields NO intersection for
 * it however solid the box is. Tests that need those real semantics stub
 * `intersectObjects` with this instead of asserting a hit up front with
 * `mockReturnValue([cellBoxMesh(n)])`. Real three unions each instance's *box*
 * sphere, so its sphere is strictly larger than the point union modelled here;
 * the epsilon keeps a box sitting exactly on the modelled hull from missing on
 * float noise alone.
 */
function sphereGatedCellHits(
    meshes: MockInstancedMesh[],
    rowIndex: number,
    cellNumber: number,
    flatNumber = 1,
): Array<{ object: unknown; instanceId: number }> {
    const target = {
        x: rowWorldX(rowIndex),
        y: flatWorldY(flatNumber),
        z: cellWorldZ(cellNumber),
    };
    const hits: Array<{ object: unknown; instanceId: number }> = [];

    for (const mesh of meshes) {
        if (mesh.boundingSphere === null) {
            mesh.computeBoundingSphere();
        }

        const sphere = mesh.boundingSphere;

        if (!sphere || sphere.radius < 0) {
            continue;
        }

        const distanceFromSphereCenter = Math.hypot(
            target.x - sphere.x,
            target.y - sphere.y,
            target.z - sphere.z,
        );

        if (distanceFromSphereCenter > sphere.radius + 1e-9) {
            continue;
        }

        const instanceId = mesh.matrices.findIndex(
            (matrix, index) =>
                index < mesh.count &&
                matrix &&
                matrix.x === target.x &&
                matrix.y === target.y &&
                matrix.z === target.z,
        );

        if (instanceId !== -1) {
            hits.push({ object: mesh, instanceId });
        }
    }

    return hits;
}

/** jsdom's default getBoundingClientRect is all zeros, which selectAtScreenPoint treats as "not laid out yet" and bails on — stub a real size so click-to-select's NDC math runs. */
function stubViewportRect(wrapper: ReturnType<typeof mount>): void {
    const element = wrapper.get('[data-testid="map-3d-viewport"]').element;
    vi.spyOn(element, 'getBoundingClientRect').mockReturnValue({
        width: 500,
        height: 500,
        top: 0,
        left: 0,
        right: 500,
        bottom: 500,
        x: 0,
        y: 0,
        toJSON: () => ({}),
    });
}

/** Reproduces the component's own `updateBounds()`/orbit-range derivation, to compute an expected orbit center for a given set of bands. */
function orbitCenterFor(bands: CellMap3DBand[]) {
    const items = bands.flatMap((band) => band.items);
    const maxCellsCount = Math.max(
        1,
        ...items.map((entry) => entry.cellNumber),
    );
    const maxFlatNumber = Math.max(
        1,
        ...items.map((entry) => entry.flatNumber),
    );
    const bounds = boundsForWarehouse(
        bands.length,
        maxCellsCount,
        maxFlatNumber,
    );

    return orbitRangeForBounds(bounds).center;
}

/** Reproduces the component's own `updateBounds()`, to compute the mini-map's expected row-line left% positions for a given set of bands. */
function expectedMiniMapRowPercents(bands: CellMap3DBand[]): number[] {
    const items = bands.flatMap((band) => band.items);
    const maxCellsCount = Math.max(
        1,
        ...items.map((entry) => entry.cellNumber),
    );
    const maxFlatNumber = Math.max(
        1,
        ...items.map((entry) => entry.flatNumber),
    );
    const bounds = boundsForWarehouse(
        bands.length,
        maxCellsCount,
        maxFlatNumber,
    );

    return miniMapRowLeftPercents(bands.length, bounds);
}

function distanceFromCenter(
    position: { x: number; y: number; z: number },
    center: { x: number; y: number; z: number },
) {
    return Math.hypot(
        position.x - center.x,
        position.y - center.y,
        position.z - center.z,
    );
}

let rafCallback: ((time: number) => void) | null = null;
let resizeCallback: (() => void) | null = null;

function item(overrides: Partial<CellMap3DItem> = {}): CellMap3DItem {
    return {
        cellId: 1,
        cellNumber: 1,
        flatNumber: 1,
        state: 'empty',
        isActive: true,
        highlighted: false,
        dimmed: false,
        pulsing: false,
        pallet: null,
        ...overrides,
    };
}

function band(overrides: Partial<CellMap3DBand> = {}): CellMap3DBand {
    return {
        letter: 'A',
        items: [item({ cellNumber: 1 }), item({ cellNumber: 2 })],
        ...overrides,
    };
}

beforeEach(() => {
    rafCallback = null;
    resizeCallback = null;
    registry().cameras = [];
    registry().renderers = [];
    registry().meshes = [];
    registry().lineSegments = [];
    registry().instancedMeshes = [];
    registry().raycasters = [];

    vi.stubGlobal('requestAnimationFrame', (cb: (time: number) => void) => {
        rafCallback = cb;

        return 1;
    });
    vi.stubGlobal('cancelAnimationFrame', () => {
        rafCallback = null;
    });
    vi.stubGlobal(
        'ResizeObserver',
        class {
            constructor(callback: () => void) {
                resizeCallback = callback;
            }

            observe() {}
            disconnect() {}
        },
    );
});

afterEach(() => {
    vi.unstubAllGlobals();
    i18n.global.locale.value = 'en';
});

describe('CellMap3D', () => {
    it('renders a focusable viewport with a mounted canvas', () => {
        const wrapper = mount(CellMap3D, { props: { bands: [band()] } });

        const viewport = wrapper.get('[data-testid="map-3d-viewport"]');
        expect(viewport.attributes('tabindex')).toBe('0');
        expect(wrapper.find('canvas').exists()).toBe(true);
    });

    it('shows a state-color legend labelling every cell state, on both touch and non-touch devices', () => {
        const wrapper = mount(CellMap3D, { props: { bands: [band()] } });

        const legend = wrapper.get('[data-testid="map-3d-state-legend"]');
        expect(legend.text()).toContain(t('cellLog.states.empty'));
        expect(legend.text()).toContain(t('cellLog.states.full'));
        expect(legend.text()).toContain(t('cellLog.states.opened'));
    });

    it('shows a controls legend explaining movement, flying between flats, looking, and selecting', () => {
        const wrapper = mount(CellMap3D, { props: { bands: [band()] } });

        expect(wrapper.text()).toContain(t('cells.map.controls.moveLabel'));
        expect(wrapper.text()).toContain(t('cells.map.controls.flyLabel'));
        expect(wrapper.text()).toContain(t('cells.map.controls.sprintLabel'));
        expect(wrapper.text()).toContain(t('cells.map.controls.lookLabel'));
        expect(wrapper.text()).toContain(t('cells.map.controls.selectLabel'));

        const keyCaps = wrapper.findAll('kbd').map((kbd) => kbd.text());
        expect(keyCaps).toEqual(['Space', 'Shift', 'Ctrl', 'O']);
    });

    it('positions the camera at row A, cell 1, flat 1 by default', () => {
        mount(CellMap3D, {
            props: { bands: [band({ letter: 'A' }), band({ letter: 'B' })] },
        });

        rafCallback?.(0);

        expect(lastCamera().position).toEqual({ x: 0, y: EYE_HEIGHT, z: 0 });
        expect(lastCamera().lookAt).toHaveBeenCalled();
    });

    it('moves forward along Z while W is held', () => {
        const wrapper = mount(CellMap3D, { props: { bands: [band()] } });

        wrapper.get('[data-testid="map-3d-viewport"]').trigger('keydown', {
            key: 'w',
        });

        rafCallback?.(0);
        rafCallback?.(1000);

        expect(lastCamera().position.z).toBe(WALK_SPEED);
    });

    it('blocks walking forward through an occupied cell instead of passing through it', () => {
        // Default band() has a cell at cellNumber 1 (flat 1), directly ahead
        // of the default starting position (row A, cell 1, flat 1 — see
        // "positions the camera at row A, cell 1, flat 1 by default" above).
        const wrapper = mount(CellMap3D, { props: { bands: [band()] } });

        wrapper.get('[data-testid="map-3d-viewport"]').trigger('keydown', {
            key: 'w',
        });

        rafCallback?.(0);
        // Advance in small steps (not one big jump) so the per-frame
        // collision check can't tunnel straight through the box the way a
        // single huge frame delta could.
        let timeMs = 0;

        for (let i = 0; i < 30; i += 1) {
            timeMs += 50;
            rafCallback?.(timeMs);
        }

        // Cell 1's box sits at cellWorldZ(1); the camera must stop short of
        // it, never reaching or passing through its position.
        expect(lastCamera().position.z).toBeLessThan(cellWorldZ(1));

        // Confirm it actually advanced (i.e. this isn't just bounds-clamped
        // at the origin) before being stopped by the cell.
        expect(lastCamera().position.z).toBeGreaterThan(0);
    });

    it('still allows walking backward away from an occupied cell', () => {
        const wrapper = mount(CellMap3D, {
            props: { bands: [band({ letter: 'A' }), band({ letter: 'B' })] },
        });

        (
            wrapper.vm as unknown as {
                focusCell: (r: string, c: number, f: number) => void;
            }
        ).focusCell('A', 2, 1);
        rafCallback?.(0);
        const startZ = lastCamera().position.z;

        wrapper.get('[data-testid="map-3d-viewport"]').trigger('keydown', {
            key: 's',
        });
        rafCallback?.(16);

        expect(lastCamera().position.z).toBeLessThan(startZ);
    });

    it('looks forward (ahead of the camera) by default', () => {
        mount(CellMap3D, { props: { bands: [band()] } });

        rafCallback?.(0);

        const camera = lastCamera();
        const lookAtZ = camera.lookAt.mock.calls.at(-1)?.[2];
        expect(lookAtZ).toBeGreaterThan(camera.position.z);
    });

    it('looks backward after dragging horizontally by half a turn', () => {
        const wrapper = mount(CellMap3D, { props: { bands: [band()] } });
        const element = wrapper.get('[data-testid="map-3d-viewport"]').element;

        rafCallback?.(0);

        // Drag right by enough pixels (at the default sensitivity) to yaw a
        // full 180°, turning the view to face backward.
        element.dispatchEvent(new MouseEvent('pointerdown', { clientX: 0 }));
        element.dispatchEvent(
            new MouseEvent('pointermove', {
                clientX: 180 / YAW_DRAG_SENSITIVITY,
            }),
        );
        rafCallback?.(16);

        const camera = lastCamera();
        const lookAtZ = camera.lookAt.mock.calls.at(-1)?.[2];
        expect(lookAtZ).toBeLessThan(camera.position.z);
    });

    it('holding the backward key alone does not change the look direction (only dragging does)', () => {
        const wrapper = mount(CellMap3D, { props: { bands: [band()] } });
        const camera = lastCamera();

        rafCallback?.(0);
        const beforeCall = camera.lookAt.mock.calls.at(-1);
        const beforeDirectionZ = (beforeCall?.[2] ?? 0) - camera.position.z;

        // Holding backward moves the camera (position.z decreases), but the
        // look direction itself must stay forward-facing — only dragging
        // changes yaw now.
        wrapper.get('[data-testid="map-3d-viewport"]').trigger('keydown', {
            key: 's',
        });
        rafCallback?.(16);

        const afterCall = camera.lookAt.mock.calls.at(-1);
        const afterDirectionZ = (afterCall?.[2] ?? 0) - camera.position.z;

        expect(afterDirectionZ).toBeCloseTo(beforeDirectionZ);
        expect(afterDirectionZ).toBeGreaterThan(0);
    });

    it('strafes toward screen-left (world +X) while A is held', () => {
        const wrapper = mount(CellMap3D, {
            props: { bands: [band({ letter: 'A' }), band({ letter: 'B' })] },
        });

        wrapper.get('[data-testid="map-3d-viewport"]').trigger('keydown', {
            key: 'a',
        });

        rafCallback?.(0);
        rafCallback?.(1000);

        expect(lastCamera().position.x).toBe(WALK_SPEED);
    });

    it('strafes toward screen-right (world -X) while D is held', () => {
        const wrapper = mount(CellMap3D, {
            props: { bands: [band({ letter: 'A' }), band({ letter: 'B' })] },
        });

        // Start from row B (x = rowWorldX(1)) so there's room to move -X
        // without clamping against the warehouse's left edge.
        (
            wrapper.vm as unknown as {
                focusCell: (r: string, c: number, f: number) => void;
            }
        ).focusCell('B', 1, 1);
        rafCallback?.(0);
        const startX = lastCamera().position.x;

        wrapper.get('[data-testid="map-3d-viewport"]').trigger('keydown', {
            key: 'd',
        });
        rafCallback?.(1000);

        expect(lastCamera().position.x).toBe(startX - WALK_SPEED);
    });

    it('flies upward while Space is held, to view a higher flat', () => {
        const wrapper = mount(CellMap3D, {
            props: { bands: [band({ items: [item({ flatNumber: 3 })] })] },
        });

        rafCallback?.(0);
        const startY = lastCamera().position.y;

        wrapper.get('[data-testid="map-3d-viewport"]').trigger('keydown', {
            key: ' ',
        });
        rafCallback?.(1000);

        expect(lastCamera().position.y).toBe(startY + WALK_SPEED);
    });

    it('flies downward while Shift is held', () => {
        const wrapper = mount(CellMap3D, {
            props: { bands: [band({ items: [item({ flatNumber: 3 })] })] },
        });

        rafCallback?.(0);
        const startY = lastCamera().position.y;

        wrapper.get('[data-testid="map-3d-viewport"]').trigger('keydown', {
            key: 'Shift',
        });
        // A quarter-second nudge, small enough not to clamp against the
        // floor (unlike a full second, which would overshoot MIN_EYE_HEIGHT).
        rafCallback?.(250);

        expect(lastCamera().position.y).toBe(startY - WALK_SPEED / 4);
    });

    it('stops moving once the key is released', () => {
        const wrapper = mount(CellMap3D, { props: { bands: [band()] } });
        const viewport = wrapper.get('[data-testid="map-3d-viewport"]');

        viewport.trigger('keydown', { key: 'w' });
        rafCallback?.(0);
        rafCallback?.(1000);
        viewport.trigger('keyup', { key: 'w' });
        rafCallback?.(2000);

        expect(lastCamera().position.z).toBe(WALK_SPEED);
    });

    it('clears held movement keys when the viewport loses focus', () => {
        const wrapper = mount(CellMap3D, { props: { bands: [band()] } });
        const viewport = wrapper.get('[data-testid="map-3d-viewport"]');

        viewport.trigger('keydown', { key: 'w' });
        viewport.trigger('blur');
        rafCallback?.(0);
        rafCallback?.(1000);

        expect(lastCamera().position.z).toBe(0);
    });

    it('pitches the camera by dragging vertically', () => {
        const wrapper = mount(CellMap3D, { props: { bands: [band()] } });
        const viewport = wrapper.get('[data-testid="map-3d-viewport"]');
        const element = viewport.element;

        rafCallback?.(0);
        const before = lastCamera().lookAt.mock.calls.at(-1);

        // vue-test-utils' trigger() can't set clientY on a constructed
        // PointerEvent (getter-only), so dispatch real DOM events directly.
        element.dispatchEvent(new MouseEvent('pointerdown', { clientY: 200 }));
        element.dispatchEvent(new MouseEvent('pointermove', { clientY: 100 }));
        rafCallback?.(16);

        const after = lastCamera().lookAt.mock.calls.at(-1);
        expect(after?.[1]).not.toBe(before?.[1]);
    });

    it('exposes focusCell, moving the camera to the given row/cell/flat', () => {
        const wrapper = mount(CellMap3D, {
            props: { bands: [band({ letter: 'A' }), band({ letter: 'B' })] },
        });

        (
            wrapper.vm as unknown as {
                focusCell: (
                    rowLetter: string,
                    cellNumber: number,
                    flatNumber: number,
                ) => void;
            }
        ).focusCell('B', 2, 1);
        rafCallback?.(0);

        expect(lastCamera().position.x).toBe(rowWorldX(1));
        expect(lastCamera().position.z).toBe(cellWorldZ(2) - cellWorldZ(1));
    });

    it('exposes resetView, returning to row A, cell 1, flat 1', () => {
        const wrapper = mount(CellMap3D, {
            props: { bands: [band({ letter: 'A' }), band({ letter: 'B' })] },
        });
        const vm = wrapper.vm as unknown as {
            focusCell: (r: string, c: number, f: number) => void;
            resetView: () => void;
        };

        vm.focusCell('B', 2, 1);
        vm.resetView();
        rafCallback?.(0);

        expect(lastCamera().position).toEqual({ x: 0, y: EYE_HEIGHT, z: 0 });
    });

    /** Cell boxes are one `THREE.InstancedMesh` per state (see CellMap3D.vue), not one mesh per cell. */
    function cellBoxInstancedMeshes() {
        return registry().instancedMeshes.filter(
            (mesh) => mesh.name === 'cell-box',
        );
    }

    it("colors each per-state instanced mesh, sized to that state's cell count, and outlines only highlighted/pulsing cells", () => {
        mount(CellMap3D, {
            props: {
                bands: [
                    band({
                        letter: 'A',
                        items: [
                            item({
                                cellNumber: 1,
                                state: 'full',
                                highlighted: true,
                            }),
                            item({ cellNumber: 2, state: 'empty' }),
                            item({
                                cellNumber: 3,
                                state: 'opened',
                                pulsing: true,
                            }),
                        ],
                    }),
                ],
            },
        });

        const meshesByColor = new Map(
            cellBoxInstancedMeshes().map((mesh) => [mesh.material.color, mesh]),
        );
        expect(meshesByColor.get(CELL_STATE_COLOR.full.hex)?.count).toBe(1);
        expect(meshesByColor.get(CELL_STATE_COLOR.empty.hex)?.count).toBe(1);
        expect(meshesByColor.get(CELL_STATE_COLOR.opened.hex)?.count).toBe(1);

        // Empty cells stay instanced (still raycastable for click/hover/facing)
        // and render as a faint placeholder box (not full opacity, so they
        // don't look like a physical box, but not invisible either, so an
        // empty slot still reads as an obvious gap in the shelf).
        const emptyMesh = meshesByColor.get(CELL_STATE_COLOR.empty.hex);
        expect(emptyMesh?.material.opacity).toBeGreaterThan(0);
        expect(emptyMesh?.material.opacity).toBeLessThan(1);
        expect(emptyMesh?.material.transparent).toBe(true);
        const fullMesh = meshesByColor.get(CELL_STATE_COLOR.full.hex);
        expect(fullMesh?.material.opacity).toBe(1);

        // Only the highlighted cell (cell 1) and the pulsing cell (cell 3)
        // get an outline; the plain empty cell (cell 2) does not.
        expect(registry().lineSegments).toHaveLength(2);
        const outlineColors = registry().lineSegments.map(
            (line) => line.material.color,
        );
        expect(new Set(outlineColors).size).toBe(2); // highlight vs pulse use distinct colors
    });

    it('tints a non-matching (dimmed) cell differently from a normal one within the same state mesh', () => {
        mount(CellMap3D, {
            props: {
                bands: [
                    band({
                        letter: 'A',
                        items: [
                            item({ cellNumber: 1, state: 'full' }),
                            item({
                                cellNumber: 2,
                                state: 'full',
                                dimmed: true,
                            }),
                        ],
                    }),
                ],
            },
        });

        const fullMesh = cellBoxInstancedMeshes().find(
            (mesh) => mesh.material.color === CELL_STATE_COLOR.full.hex,
        );

        expect(fullMesh?.colors[0]?.value).not.toBe(fullMesh?.colors[1]?.value);
    });

    it("updates an instance's tint in place when dimmed flips without a state change", async () => {
        const wrapper = mount(CellMap3D, {
            props: {
                bands: [
                    band({
                        letter: 'A',
                        items: [
                            item({
                                cellNumber: 1,
                                state: 'full',
                                dimmed: true,
                            }),
                        ],
                    }),
                ],
            },
        });

        const fullMesh = () =>
            cellBoxInstancedMeshes().find(
                (mesh) => mesh.material.color === CELL_STATE_COLOR.full.hex,
            );
        const dimmedTint = fullMesh()?.colors[0]?.value;

        await wrapper.setProps({
            bands: [
                band({
                    letter: 'A',
                    items: [
                        item({ cellNumber: 1, state: 'full', dimmed: false }),
                    ],
                }),
            ],
        });

        expect(fullMesh()?.colors[0]?.value).not.toBe(dimmedTint);
    });

    it('outlines an inactive cell, but highlighted/pulsing take priority over the inactive outline', () => {
        mount(CellMap3D, {
            props: {
                bands: [
                    band({
                        letter: 'A',
                        items: [
                            item({
                                cellNumber: 1,
                                state: 'full',
                                isActive: false,
                            }),
                            item({ cellNumber: 2, state: 'empty' }),
                            item({
                                cellNumber: 3,
                                state: 'full',
                                isActive: false,
                                highlighted: true,
                            }),
                        ],
                    }),
                ],
            },
        });

        // Cell 1 (inactive only) gets an outline; cell 2 (active, not
        // highlighted/pulsing) does not; cell 3 (inactive AND highlighted)
        // gets the highlight outline color, not a separate inactive one.
        expect(registry().lineSegments).toHaveLength(2);
        const outlineColors = registry().lineSegments.map(
            (line) => line.material.color,
        );
        expect(new Set(outlineColors).size).toBe(2);
    });

    it("adds one shelf platform per flat level, sitting just under that flat's boxes", () => {
        mount(CellMap3D, {
            props: {
                bands: [
                    band({
                        letter: 'A',
                        items: [
                            item({ cellNumber: 1, flatNumber: 1 }),
                            item({ cellNumber: 3, flatNumber: 1 }),
                            item({ cellNumber: 1, flatNumber: 2 }),
                        ],
                    }),
                ],
            },
        });

        const shelves = registry().meshes.filter(
            (mesh) => mesh.name === 'shelf',
        );
        const boxFlatYs = [flatWorldY(1), flatWorldY(2)];

        // One shelf per distinct flat level in the row, not one per cell —
        // this row has 3 boxes across 2 flat levels.
        expect(shelves).toHaveLength(2);

        const shelfYs = shelves
            .map((shelf) => shelf.position.y)
            .sort((a, b) => a - b);

        shelfYs.forEach((shelfY, index) => {
            const boxY = boxFlatYs[index];
            expect(shelfY).toBeLessThan(boxY);
            expect(boxY - shelfY).toBeLessThan(2); // sits just under it, not floating far below
        });

        // Every shelf sits at its row's X — none drift to another row's aisle.
        shelves.forEach((shelf) => expect(shelf.position.x).toBe(rowWorldX(0)));
    });

    it('adds four corner posts per row, standing beside the boxes rather than through them', () => {
        mount(CellMap3D, {
            props: {
                bands: [band({ letter: 'A' }), band({ letter: 'B' })],
            },
        });

        const posts = registry().meshes.filter((mesh) => mesh.name === 'post');

        // One set of 4 corner posts per row.
        expect(posts).toHaveLength(8);

        const rowAPosts = posts.filter(
            (post) => Math.abs(post.position.x - rowWorldX(0)) < 2,
        );
        expect(rowAPosts).toHaveLength(4);

        // Posts stand to the side of the boxes (never at a row's own X), and
        // reach up to cover the topmost flat.
        posts.forEach((post) => {
            expect(post.position.x).not.toBe(rowWorldX(0));
            expect(post.position.x).not.toBe(rowWorldX(1));
            expect(post.position.y).toBeGreaterThan(0);
        });

        // Two distinct X offsets (left/right) and two distinct Z offsets
        // (front/back) per row — i.e. actual corners, not a single column.
        const rowAXs = new Set(rowAPosts.map((post) => post.position.x));
        const rowAZs = new Set(rowAPosts.map((post) => post.position.z));
        expect(rowAXs.size).toBe(2);
        expect(rowAZs.size).toBe(2);
    });

    it('uses a single instanced mesh (not one mesh per box) for every cell sharing a state', () => {
        mount(CellMap3D, {
            props: {
                bands: [
                    band({
                        items: [
                            item({ cellNumber: 1, state: 'full' }),
                            item({ cellNumber: 2, state: 'full' }),
                        ],
                    }),
                ],
            },
        });

        const fullMeshes = cellBoxInstancedMeshes().filter(
            (mesh) => mesh.material.color === CELL_STATE_COLOR.full.hex,
        );
        expect(fullMeshes).toHaveLength(1);
        expect(fullMeshes[0].count).toBe(2);
    });

    it("moves a cell to a different state's instanced mesh in place — no rebuild, no material dispose — when only its state changes", async () => {
        const wrapper = mount(CellMap3D, {
            props: {
                bands: [
                    band({
                        items: [item({ cellNumber: 1, state: 'full' })],
                    }),
                ],
            },
        });

        const meshesBefore = cellBoxInstancedMeshes();
        const fullMaterialBefore = meshesBefore.find(
            (mesh) => mesh.material.color === CELL_STATE_COLOR.full.hex,
        )?.material;

        await wrapper.setProps({
            bands: [
                band({
                    items: [item({ cellNumber: 1, state: 'opened' })],
                }),
            ],
        });

        // Reused, not disposed — the previous "full" material is shared and
        // stays alive for the next box that needs it — and the instanced
        // meshes themselves aren't recreated (no structural rebuild): still
        // exactly one instanced mesh per cell state, the same objects as
        // before (by reference).
        expect(fullMaterialBefore?.dispose).not.toHaveBeenCalled();
        const meshesAfter = cellBoxInstancedMeshes();
        expect(meshesAfter).toHaveLength(meshesBefore.length);
        meshesAfter.forEach((mesh, index) => {
            expect(mesh).toBe(meshesBefore[index]);
        });

        const fullMesh = meshesBefore.find(
            (mesh) => mesh.material.color === CELL_STATE_COLOR.full.hex,
        );
        const openedMesh = meshesBefore.find(
            (mesh) => mesh.material.color === CELL_STATE_COLOR.opened.hex,
        );
        expect(fullMesh?.count).toBe(0);
        expect(openedMesh?.count).toBe(1);
    });

    it('keeps drawing a state whose first cell arrives from a state-only update, instead of leaving it culled by the empty bounding sphere cached on the first frame', async () => {
        const wrapper = mount(CellMap3D, {
            props: {
                bands: [
                    band({ items: [item({ cellNumber: 1, state: 'full' })] }),
                ],
            },
        });
        const openedMesh = () =>
            cellBoxInstancedMeshes().find(
                (mesh) => mesh.material.color === CELL_STATE_COLOR.opened.hex,
            );

        // First frame: the renderer computes and caches every mesh's bounding
        // sphere. "opened" holds no cells yet, so its sphere is empty and the
        // mesh is correctly skipped for this frame.
        rafCallback?.(0);
        expect(openedMesh()?.drawnInLastFrame).toBe(false);

        // A state-only change (an admin opening a pallet) moves the cell into
        // the "opened" mesh through the in-place fast path, without rebuilding
        // the group — so nothing but an explicit invalidation can refresh that
        // cached sphere.
        await wrapper.setProps({
            bands: [
                band({ items: [item({ cellNumber: 1, state: 'opened' })] }),
            ],
        });
        rafCallback?.(16);

        expect(openedMesh()?.count).toBe(1);
        expect(openedMesh()?.drawnInLastFrame).toBe(true);
    });

    it('still hit-tests a cell that moved into a state whose bounding sphere was cached around a different row', async () => {
        const wrapper = mount(CellMap3D, {
            props: {
                bands: [
                    band({
                        letter: 'A',
                        items: [item({ cellNumber: 1, state: 'opened' })],
                    }),
                    band({
                        letter: 'B',
                        items: [item({ cellNumber: 5, state: 'full' })],
                    }),
                ],
            },
        });
        stubViewportRect(wrapper);

        // First frame caches the "opened" sphere around row A's lone opened
        // cell — the only opened cell in the warehouse so far.
        rafCallback?.(0);
        await wrapper.vm.$nextTick();

        // Row B's cell is opened too now, again via the state-only fast path.
        await wrapper.setProps({
            bands: [
                band({
                    letter: 'A',
                    items: [item({ cellNumber: 1, state: 'opened' })],
                }),
                band({
                    letter: 'B',
                    items: [item({ cellNumber: 5, state: 'opened' })],
                }),
            ],
        });

        // Unlike the other click-to-select tests, the hit isn't assumed here:
        // the raycast is gated by the mesh's cached bounding sphere exactly as
        // three gates it, so a stale sphere means the click lands on nothing
        // and clears the selection instead of selecting row B's cell.
        lastRaycaster().intersectObjects.mockImplementation(
            (meshes: MockInstancedMesh[]) => sphereGatedCellHits(meshes, 1, 5),
        );

        const element = wrapper.get('[data-testid="map-3d-viewport"]').element;
        element.dispatchEvent(
            new PointerEvent('pointerdown', {
                pointerId: 1,
                clientX: 50,
                clientY: 50,
            }),
        );
        element.dispatchEvent(
            new PointerEvent('pointerup', {
                pointerId: 1,
                clientX: 50,
                clientY: 50,
            }),
        );
        rafCallback?.(16);
        await wrapper.vm.$nextTick();

        expect(
            wrapper.get('[data-testid="map-3d-faced-cell"]').text(),
        ).toContain(formatSlot('B', 5, 1));
    });

    it('updates an outline in place — same instance, new color — rather than creating a new one when a cell switches from highlighted to pulsing', async () => {
        const wrapper = mount(CellMap3D, {
            props: {
                bands: [
                    band({
                        items: [
                            item({
                                cellNumber: 1,
                                highlighted: true,
                                pulsing: false,
                            }),
                        ],
                    }),
                ],
            },
        });

        expect(registry().lineSegments).toHaveLength(1);
        const outlineBefore = registry().lineSegments[0];
        const colorBefore = outlineBefore.material.color;

        await wrapper.setProps({
            bands: [
                band({
                    items: [
                        item({
                            cellNumber: 1,
                            highlighted: false,
                            pulsing: true,
                        }),
                    ],
                }),
            ],
        });

        expect(registry().lineSegments).toHaveLength(1);
        expect(registry().lineSegments[0]).toBe(outlineBefore);
        expect(outlineBefore.material.color).not.toBe(colorBefore);
    });

    it('fully rebuilds the cell group (fresh instanced meshes) when the set of cells changes, not just their state', async () => {
        const wrapper = mount(CellMap3D, {
            props: {
                bands: [
                    band({ items: [item({ cellNumber: 1, state: 'full' })] }),
                ],
            },
        });

        const fullMaterialBefore = cellBoxInstancedMeshes().find(
            (mesh) => mesh.material.color === CELL_STATE_COLOR.full.hex,
        )?.material;
        const instancedMeshCountBefore = cellBoxInstancedMeshes().length;

        await wrapper.setProps({
            bands: [
                band({
                    items: [
                        item({ cellNumber: 1, state: 'full' }),
                        item({ cellNumber: 2, state: 'empty' }),
                    ],
                }),
            ],
        });

        // The shared "full" material is persistent regardless of which path
        // rebuilt the group, so it's still never disposed here. The mock's
        // registry only ever grows (it doesn't model real disposal), so a
        // structural rebuild is asserted by a brand-new set of per-state
        // instanced meshes being created (CELL_STATES.length more of them),
        // not by an exact/shrinking total count.
        expect(fullMaterialBefore?.dispose).not.toHaveBeenCalled();
        expect(cellBoxInstancedMeshes().length).toBeGreaterThan(
            instancedMeshCountBefore,
        );
    });

    it('disposes the shared per-state box materials on unmount', () => {
        const wrapper = mount(CellMap3D, {
            props: {
                bands: [
                    band({ items: [item({ cellNumber: 1, state: 'full' })] }),
                ],
            },
        });

        const material = cellBoxInstancedMeshes().find(
            (mesh) => mesh.material.color === CELL_STATE_COLOR.full.hex,
        )?.material;

        wrapper.unmount();

        expect(material?.dispose).toHaveBeenCalled();
    });

    it('disposes the renderer on unmount', () => {
        const wrapper = mount(CellMap3D, { props: { bands: [band()] } });
        const renderer = registry().renderers.at(-1) as {
            dispose: ReturnType<typeof vi.fn>;
        };

        wrapper.unmount();

        expect(renderer.dispose).toHaveBeenCalled();
    });

    describe('location indicator', () => {
        it("shows the camera's standing location by default (row A, cell 1, flat 1)", async () => {
            const wrapper = mount(CellMap3D, { props: { bands: [band()] } });
            await wrapper.vm.$nextTick();

            expect(
                wrapper.get('[data-testid="map-3d-location-indicator"]').text(),
            ).toContain(formatSlot('A', 1, 1));
        });

        it('updates as the camera walks, independent of whether a cell actually exists there', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({ letter: 'A', items: [item({ cellNumber: 1 })] }),
                    ],
                },
            });
            const viewport = wrapper.get('[data-testid="map-3d-viewport"]');

            viewport.trigger('keydown', { key: 'w' });
            rafCallback?.(0);
            rafCallback?.(3000);
            await wrapper.vm.$nextTick();

            expect(
                wrapper.get('[data-testid="map-3d-location-indicator"]').text(),
            ).not.toContain(formatSlot('A', 1, 1));
        });

        it('hides while orbiting', async () => {
            const wrapper = mount(CellMap3D, { props: { bands: [band()] } });
            await wrapper.vm.$nextTick();

            (
                wrapper.vm as unknown as {
                    setCameraMode: (mode: 'walk' | 'orbit') => void;
                }
            ).setCameraMode('orbit');
            await wrapper.vm.$nextTick();

            expect(
                wrapper
                    .find('[data-testid="map-3d-location-indicator"]')
                    .exists(),
            ).toBe(false);
        });
    });

    describe('faced-cell detail panel', () => {
        it('shows the same detail as the 2D grid for the default-faced cell (row A, cell 1, flat 1)', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({
                            letter: 'A',
                            items: [
                                item({
                                    cellNumber: 1,
                                    state: 'full',
                                    pallet: {
                                        id: 9,
                                        product_id: 3,
                                        product_name: 'Widgets',
                                        product_ar_name: 'ودجات',
                                        product_image_url: null,
                                        expiration_date: '2026-09-01',
                                        added_at: '2026-07-01T10:00:00Z',
                                        remaining_boxes: 5,
                                    },
                                }),
                            ],
                        }),
                    ],
                },
            });

            rafCallback?.(0);
            await wrapper.vm.$nextTick();

            const panel = wrapper.get('[data-testid="map-3d-faced-cell"]');
            expect(panel.text()).toContain(formatSlot('A', 1, 1));
            expect(panel.text()).toContain('Widgets');
            expect(panel.find('[data-testid="cell-slot"]').exists()).toBe(true);
        });

        it("renders the panel's product label once, resolved by CellSlot from the raw columns it is handed", async () => {
            i18n.global.locale.value = 'ar';

            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({
                            letter: 'A',
                            items: [
                                item({
                                    cellNumber: 1,
                                    state: 'full',
                                    pallet: {
                                        id: 9,
                                        product_id: 3,
                                        product_name: 'Widgets',
                                        product_ar_name: 'ودجات',
                                        product_image_url: null,
                                        expiration_date: '2026-09-01',
                                        added_at: '2026-07-01T10:00:00Z',
                                        remaining_boxes: 5,
                                    },
                                }),
                            ],
                        }),
                    ],
                },
            });

            rafCallback?.(0);
            await wrapper.vm.$nextTick();

            const slot = wrapper.getComponent(CellSlot);

            // The 3D map re-projects its item into a CellPallet for CellSlot
            // and must pass both raw columns straight through — resolving here
            // as well would double-apply the locale choice.
            expect(slot.props('cell')).toMatchObject({
                pallet: { product_name: 'Widgets', product_ar_name: 'ودجات' },
            });
            expect(slot.text()).toContain('ودجات');
            expect(slot.text()).not.toContain('Widgets');
        });

        it('shows no panel when facing an empty gap with no matching cell', async () => {
            const wrapper = mount(CellMap3D, { props: { bands: [] } });

            rafCallback?.(0);
            await wrapper.vm.$nextTick();

            expect(
                wrapper.find('[data-testid="map-3d-faced-cell"]').exists(),
            ).toBe(false);
        });

        it("passes the faced item's dimmed flag straight through to the faced-cell panel", async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({
                            letter: 'A',
                            items: [item({ cellNumber: 1, dimmed: true })],
                        }),
                    ],
                },
            });

            rafCallback?.(0);
            await wrapper.vm.$nextTick();

            expect(wrapper.getComponent(CellSlot).props('dimmed')).toBe(true);
        });

        it('faces a cell behind you after dragging to turn 180°', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({
                            letter: 'A',
                            items: [
                                item({ cellNumber: 1 }),
                                item({ cellNumber: 5 }),
                                item({ cellNumber: 7 }),
                            ],
                        }),
                    ],
                },
            });

            (
                wrapper.vm as unknown as {
                    focusCell: (r: string, c: number, f: number) => void;
                }
            ).focusCell('A', 7, 1);
            rafCallback?.(0);
            await wrapper.vm.$nextTick();
            expect(
                wrapper.get('[data-testid="map-3d-faced-cell"]').text(),
            ).toContain(formatSlot('A', 7, 1));

            const element = wrapper.get(
                '[data-testid="map-3d-viewport"]',
            ).element;
            element.dispatchEvent(
                new MouseEvent('pointerdown', { clientX: 0 }),
            );
            element.dispatchEvent(
                new MouseEvent('pointermove', {
                    clientX: 180 / YAW_DRAG_SENSITIVITY,
                }),
            );
            rafCallback?.(16);
            await wrapper.vm.$nextTick();

            expect(
                wrapper.get('[data-testid="map-3d-faced-cell"]').text(),
            ).toContain(formatSlot('A', 5, 1));
        });

        it('updates the faced cell while walking forward past it', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({
                            letter: 'A',
                            items: [
                                item({ cellNumber: 1 }),
                                item({ cellNumber: 4 }),
                            ],
                        }),
                    ],
                },
            });

            const viewport = wrapper.get('[data-testid="map-3d-viewport"]');
            rafCallback?.(0);
            await wrapper.vm.$nextTick();
            expect(
                wrapper.get('[data-testid="map-3d-faced-cell"]').text(),
            ).toContain(formatSlot('A', 1, 1));

            viewport.trigger('keydown', { key: 'w' });
            rafCallback?.(0);
            // 2.25s at WALK_SPEED lands the probe point exactly on cell 4.
            rafCallback?.(2250);
            await wrapper.vm.$nextTick();

            expect(
                wrapper.get('[data-testid="map-3d-faced-cell"]').text(),
            ).toContain(formatSlot('A', 4, 1));
        });

        it('refreshes the faced cell content when its highlight/pulse state changes without moving', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({
                            letter: 'A',
                            items: [
                                item({ cellNumber: 1, highlighted: false }),
                            ],
                        }),
                    ],
                },
            });

            rafCallback?.(0);
            await wrapper.vm.$nextTick();
            expect(
                wrapper
                    .get(
                        '[data-testid="map-3d-faced-cell"] [data-testid="cell-slot"]',
                    )
                    .classes(),
            ).not.toContain('ring-blue-500');

            await wrapper.setProps({
                bands: [
                    band({
                        letter: 'A',
                        items: [item({ cellNumber: 1, highlighted: true })],
                    }),
                ],
            });
            rafCallback?.(1000);
            await wrapper.vm.$nextTick();

            expect(
                wrapper
                    .get(
                        '[data-testid="map-3d-faced-cell"] [data-testid="cell-slot"]',
                    )
                    .classes(),
            ).toContain('ring-blue-500');
        });

        it("emits manage-pallet with the real cell/pallet ids when the panel's manage button is clicked", async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({
                            letter: 'A',
                            items: [
                                item({
                                    cellId: 42,
                                    cellNumber: 1,
                                    state: 'full',
                                    pallet: {
                                        id: 9,
                                        product_id: 3,
                                        product_name: 'Widgets',
                                        product_ar_name: 'ودجات',
                                        product_image_url: null,
                                        expiration_date: '2026-09-01',
                                        added_at: '2026-07-01T10:00:00Z',
                                        remaining_boxes: 5,
                                    },
                                }),
                            ],
                        }),
                    ],
                },
            });

            rafCallback?.(0);
            await wrapper.vm.$nextTick();

            await wrapper
                .get(`[title="${t('cells.palletActions.triggerLabel')}"]`)
                .trigger('click');

            expect(wrapper.emitted('manage-pallet')).toEqual([
                [
                    expect.objectContaining({
                        id: 42,
                        pallet: expect.objectContaining({
                            id: 9,
                            remaining_boxes: 5,
                        }),
                    }),
                    formatSlot('A', 1, 1),
                ],
            ]);
        });

        it("emits toggle-active with the real cell id when the panel's toggle button is clicked", async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({
                            letter: 'A',
                            items: [item({ cellId: 42, cellNumber: 1 })],
                        }),
                    ],
                },
            });

            rafCallback?.(0);
            await wrapper.vm.$nextTick();

            await wrapper
                .get(`[title="${t('cells.toggleActive.deactivateLabel')}"]`)
                .trigger('click');

            expect(wrapper.emitted('toggle-active')).toEqual([
                [expect.objectContaining({ id: 42 }), formatSlot('A', 1, 1)],
            ]);
        });

        it("does not let a pointerdown on the panel's buttons bubble into the viewport's click-to-select handling", async () => {
            // Regression test: the viewport container captures the pointer
            // (setPointerCapture) on every pointerdown that reaches it, which
            // in a real browser retargets the resulting click event away
            // from whatever was actually pressed — silently swallowing
            // clicks on the panel's toggle-active/export-qr/manage-pallet
            // controls. `@pointerdown.stop` on the panel's CellSlot must
            // keep that pointerdown from ever reaching the container.
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({
                            letter: 'A',
                            items: [item({ cellId: 42, cellNumber: 1 })],
                        }),
                    ],
                },
            });

            rafCallback?.(0);
            await wrapper.vm.$nextTick();

            const viewportElement = wrapper.get(
                '[data-testid="map-3d-viewport"]',
            ).element as HTMLElement & { setPointerCapture?: () => void };
            const setPointerCaptureSpy = vi.fn();
            viewportElement.setPointerCapture = setPointerCaptureSpy;

            wrapper
                .get(`[title="${t('cells.toggleActive.deactivateLabel')}"]`)
                .element.dispatchEvent(
                    new PointerEvent('pointerdown', {
                        pointerId: 1,
                        bubbles: true,
                    }),
                );

            expect(setPointerCaptureSpy).not.toHaveBeenCalled();
        });
    });

    describe('click-to-select while walking', () => {
        it('selects a cell by clicking it, pinning the panel even as facedItem keeps tracking elsewhere', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({
                            letter: 'A',
                            items: [
                                item({ cellNumber: 1, state: 'full' }),
                                item({ cellNumber: 5, state: 'opened' }),
                            ],
                        }),
                    ],
                },
            });
            stubViewportRect(wrapper);
            rafCallback?.(0);
            await wrapper.vm.$nextTick();
            expect(
                wrapper.get('[data-testid="map-3d-faced-cell"]').text(),
            ).toContain(formatSlot('A', 1, 1));

            lastRaycaster().intersectObjects.mockReturnValue([cellBoxMesh(5)]);
            const cameraBefore = { ...lastCamera().position };
            const element = wrapper.get(
                '[data-testid="map-3d-viewport"]',
            ).element;
            element.dispatchEvent(
                new PointerEvent('pointerdown', {
                    pointerId: 1,
                    clientX: 50,
                    clientY: 50,
                }),
            );
            element.dispatchEvent(
                new PointerEvent('pointerup', {
                    pointerId: 1,
                    clientX: 50,
                    clientY: 50,
                }),
            );
            rafCallback?.(16);
            await wrapper.vm.$nextTick();

            expect(
                wrapper.get('[data-testid="map-3d-faced-cell"]').text(),
            ).toContain(formatSlot('A', 5, 1));
            // Selecting inspects the cell, it doesn't teleport the camera.
            expect(lastCamera().position).toEqual(cameraBefore);

            // Walking further keeps the selection pinned instead of reverting
            // to whatever facedItem is now tracking.
            wrapper.get('[data-testid="map-3d-viewport"]').trigger('keydown', {
                key: 'w',
            });
            rafCallback?.(1016);
            await wrapper.vm.$nextTick();

            expect(
                wrapper.get('[data-testid="map-3d-faced-cell"]').text(),
            ).toContain(formatSlot('A', 5, 1));
        });

        it('clears the selection by clicking empty space, reverting to the continuously-tracked faced cell', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({
                            letter: 'A',
                            items: [
                                item({ cellNumber: 1 }),
                                item({ cellNumber: 5 }),
                            ],
                        }),
                    ],
                },
            });
            stubViewportRect(wrapper);
            rafCallback?.(0);

            const element = wrapper.get(
                '[data-testid="map-3d-viewport"]',
            ).element;
            const click = () => {
                element.dispatchEvent(
                    new PointerEvent('pointerdown', {
                        pointerId: 1,
                        clientX: 50,
                        clientY: 50,
                    }),
                );
                element.dispatchEvent(
                    new PointerEvent('pointerup', {
                        pointerId: 1,
                        clientX: 50,
                        clientY: 50,
                    }),
                );
            };

            lastRaycaster().intersectObjects.mockReturnValue([cellBoxMesh(5)]);
            click();
            rafCallback?.(16);
            await wrapper.vm.$nextTick();
            expect(
                wrapper.get('[data-testid="map-3d-faced-cell"]').text(),
            ).toContain(formatSlot('A', 5, 1));

            lastRaycaster().intersectObjects.mockReturnValue([]);
            click();
            rafCallback?.(32);
            await wrapper.vm.$nextTick();

            // Falls back to facedItem (still row A, cell 1, where the camera
            // stands) instead of hiding the panel the way orbit mode would.
            expect(
                wrapper.get('[data-testid="map-3d-faced-cell"]').text(),
            ).toContain(formatSlot('A', 1, 1));
        });

        it('does not select when the pointer drags past the click threshold — still just looks around', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({
                            letter: 'A',
                            items: [
                                item({ cellNumber: 1 }),
                                item({ cellNumber: 5 }),
                            ],
                        }),
                    ],
                },
            });
            stubViewportRect(wrapper);
            rafCallback?.(0);
            await wrapper.vm.$nextTick();

            lastRaycaster().intersectObjects.mockReturnValue([cellBoxMesh(5)]);
            const element = wrapper.get(
                '[data-testid="map-3d-viewport"]',
            ).element;
            element.dispatchEvent(
                new PointerEvent('pointerdown', {
                    pointerId: 1,
                    clientX: 50,
                    clientY: 50,
                }),
            );
            element.dispatchEvent(
                new PointerEvent('pointermove', {
                    pointerId: 1,
                    clientX: 90,
                    clientY: 50,
                }),
            );
            element.dispatchEvent(
                new PointerEvent('pointerup', {
                    pointerId: 1,
                    clientX: 90,
                    clientY: 50,
                }),
            );
            rafCallback?.(16);
            await wrapper.vm.$nextTick();

            expect(lastRaycaster().intersectObjects).not.toHaveBeenCalled();
            expect(
                wrapper.get('[data-testid="map-3d-faced-cell"]').text(),
            ).toContain(formatSlot('A', 1, 1));
        });
    });

    describe('keyboard select (Enter) while walking', () => {
        it('selects the currently-faced cell by pressing Enter', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({
                            letter: 'A',
                            items: [
                                item({ cellNumber: 1, state: 'full' }),
                                item({ cellNumber: 5, state: 'opened' }),
                            ],
                        }),
                    ],
                },
            });
            rafCallback?.(0);
            await wrapper.vm.$nextTick();
            const cameraBefore = { ...lastCamera().position };

            wrapper.get('[data-testid="map-3d-viewport"]').trigger('keydown', {
                key: 'Enter',
            });
            rafCallback?.(16);
            await wrapper.vm.$nextTick();

            // Enter inspects the faced cell (row A, cell 1 by default), it
            // doesn't teleport the camera — same contract as click-to-select.
            expect(
                wrapper.get('[data-testid="map-3d-faced-cell"]').text(),
            ).toContain(formatSlot('A', 1, 1));
            expect(lastCamera().position).toEqual(cameraBefore);

            // The selection is pinned even as the camera keeps walking, just
            // like a mouse-click selection.
            wrapper.get('[data-testid="map-3d-viewport"]').trigger('keydown', {
                key: 'w',
            });
            rafCallback?.(1016);
            await wrapper.vm.$nextTick();
            expect(
                wrapper.get('[data-testid="map-3d-faced-cell"]').text(),
            ).toContain(formatSlot('A', 1, 1));
        });

        it('clears any existing selection when pressing Enter over empty space', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({
                            letter: 'A',
                            items: [item({ cellNumber: 1 })],
                        }),
                    ],
                },
            });
            rafCallback?.(0);
            await wrapper.vm.$nextTick();
            const viewport = wrapper.get('[data-testid="map-3d-viewport"]');

            viewport.trigger('keydown', { key: 'Enter' });
            rafCallback?.(16);
            await wrapper.vm.$nextTick();
            expect(
                wrapper.get('[data-testid="map-3d-faced-cell"]').text(),
            ).toContain(formatSlot('A', 1, 1));

            // Walk forward past the only cell so nothing is faced any more.
            viewport.trigger('keydown', { key: 'w' });
            rafCallback?.(3000);
            await wrapper.vm.$nextTick();

            viewport.trigger('keydown', { key: 'Enter' });
            rafCallback?.(3016);
            await wrapper.vm.$nextTick();

            expect(
                wrapper.find('[data-testid="map-3d-faced-cell"]').exists(),
            ).toBe(false);
        });

        it('does not move the camera when pressing Enter in orbit mode (see "overview / orbit camera mode" for its keyboard-select behavior)', () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({ letter: 'A', items: [item({ cellNumber: 1 })] }),
                    ],
                },
            });
            (
                wrapper.vm as unknown as {
                    setCameraMode: (mode: 'walk' | 'orbit') => void;
                }
            ).setCameraMode('orbit');
            rafCallback?.(0);
            const before = { ...lastCamera().position };

            wrapper.get('[data-testid="map-3d-viewport"]').trigger('keydown', {
                key: 'Enter',
            });
            rafCallback?.(16);

            expect(lastCamera().position).toEqual(before);
        });
    });

    describe('aria-live announcement', () => {
        it('announces standing-near + facing while walking', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({
                            letter: 'A',
                            items: [item({ cellNumber: 1, state: 'full' })],
                        }),
                    ],
                },
            });
            rafCallback?.(0);
            await wrapper.vm.$nextTick();

            const announcement = wrapper.get(
                '[data-testid="map-3d-announcement"]',
            );
            expect(announcement.attributes('aria-live')).toBe('polite');
            expect(announcement.attributes('role')).toBe('status');
            expect(announcement.text()).toContain(formatSlot('A', 1, 1));
        });

        it("announces the pallet product's Arabic name when the locale is Arabic", async () => {
            i18n.global.locale.value = 'ar';

            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({
                            letter: 'A',
                            items: [
                                item({
                                    cellNumber: 1,
                                    state: 'full',
                                    pallet: {
                                        id: 9,
                                        product_id: 3,
                                        product_name: 'Widgets',
                                        product_ar_name: 'ودجات',
                                        product_image_url: null,
                                        expiration_date: '2026-09-01',
                                        added_at: '2026-07-01T10:00:00Z',
                                        remaining_boxes: 5,
                                    },
                                }),
                            ],
                        }),
                    ],
                },
            });
            rafCallback?.(0);
            await wrapper.vm.$nextTick();

            const announced = wrapper
                .get('[data-testid="map-3d-announcement"]')
                .text();

            expect(announced).toContain('ودجات');
            expect(announced).not.toContain('Widgets');
        });

        it('announces the base product name when the store never translated it', async () => {
            i18n.global.locale.value = 'ar';

            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({
                            letter: 'A',
                            items: [
                                item({
                                    cellNumber: 1,
                                    state: 'full',
                                    pallet: {
                                        id: 9,
                                        product_id: 3,
                                        product_name: 'Widgets',
                                        product_ar_name: '',
                                        product_image_url: null,
                                        expiration_date: '2026-09-01',
                                        added_at: '2026-07-01T10:00:00Z',
                                        remaining_boxes: 5,
                                    },
                                }),
                            ],
                        }),
                    ],
                },
            });
            rafCallback?.(0);
            await wrapper.vm.$nextTick();

            expect(
                wrapper.get('[data-testid="map-3d-announcement"]').text(),
            ).toContain('Widgets');
        });

        it('is empty while orbiting with nothing selected, and announces the selection once one is clicked', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({
                            letter: 'A',
                            items: [item({ cellNumber: 5, state: 'full' })],
                        }),
                    ],
                },
            });
            (
                wrapper.vm as unknown as {
                    setCameraMode: (mode: 'walk' | 'orbit') => void;
                }
            ).setCameraMode('orbit');
            stubViewportRect(wrapper);
            rafCallback?.(0);
            await wrapper.vm.$nextTick();

            expect(
                wrapper.get('[data-testid="map-3d-announcement"]').text(),
            ).toBe('');

            lastRaycaster().intersectObjects.mockReturnValue([cellBoxMesh(5)]);
            const element = wrapper.get(
                '[data-testid="map-3d-viewport"]',
            ).element;
            element.dispatchEvent(
                new PointerEvent('pointerdown', {
                    pointerId: 1,
                    clientX: 50,
                    clientY: 50,
                }),
            );
            element.dispatchEvent(
                new PointerEvent('pointerup', {
                    pointerId: 1,
                    clientX: 50,
                    clientY: 50,
                }),
            );
            rafCallback?.(16);
            await wrapper.vm.$nextTick();

            expect(
                wrapper.get('[data-testid="map-3d-announcement"]').text(),
            ).toContain(formatSlot('A', 5, 1));
        });
    });

    describe('mini-map (walk mode orientation aid)', () => {
        it('only renders while walking, not while orbiting', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [band({ letter: 'A' }), band({ letter: 'B' })],
                },
            });
            await wrapper.vm.$nextTick();
            expect(
                wrapper.find('[data-testid="map-3d-mini-map"]').exists(),
            ).toBe(true);

            (
                wrapper.vm as unknown as {
                    setCameraMode: (mode: 'walk' | 'orbit') => void;
                }
            ).setCameraMode('orbit');
            await wrapper.vm.$nextTick();

            expect(
                wrapper.find('[data-testid="map-3d-mini-map"]').exists(),
            ).toBe(false);
        });

        it('draws one line per configured row', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({ letter: 'A' }),
                        band({ letter: 'B' }),
                        band({ letter: 'C' }),
                    ],
                },
            });
            await wrapper.vm.$nextTick();

            expect(
                wrapper.get('[data-testid="map-3d-mini-map"]').findAll('line'),
            ).toHaveLength(3);
        });

        it('positions each row line using the real warehouse bounds, not the pre-mount zero default', async () => {
            // Regression test: the row-line positions used to be derived
            // from a plain (non-reactive) `bounds` variable, so the
            // mini-map's `computed` cached its pre-mount all-zero bounds
            // forever — every row line collapsed onto the container's edges
            // (0% or clamped to 100%) instead of spreading across it.
            const bands = [
                band({ letter: 'A' }),
                band({ letter: 'B' }),
                band({ letter: 'C' }),
            ];
            const wrapper = mount(CellMap3D, { props: { bands } });
            await wrapper.vm.$nextTick();

            const lines = wrapper
                .get('[data-testid="map-3d-mini-map"]')
                .findAll('line');
            const actualPercents = lines.map((line) =>
                Number(line.attributes('x1')),
            );

            expect(actualPercents).toEqual(expectedMiniMapRowPercents(bands));
            // With 3 evenly-spaced rows, the middle row must sit strictly
            // between the edges — not collapsed onto them like the bug did.
            expect(actualPercents[1]).toBeGreaterThan(0);
            expect(actualPercents[1]).toBeLessThan(100);
        });

        it('flags rows containing an active highlight match, distinct from the current-row line', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({ letter: 'A' }),
                        band({
                            letter: 'B',
                            items: [item({ cellNumber: 1, highlighted: true })],
                        }),
                        band({ letter: 'C' }),
                    ],
                },
            });
            rafCallback?.(0);
            await wrapper.vm.$nextTick();

            const lines = wrapper
                .get('[data-testid="map-3d-mini-map"]')
                .findAll('line');

            expect(lines[0].classes()).not.toContain('stroke-emerald-500');
            expect(lines[1].classes()).toContain('stroke-emerald-500');
            expect(lines[2].classes()).not.toContain('stroke-emerald-500');
            // The camera starts standing in row A, not the matched row B, so
            // the "current row" styling and the "has a match" styling stay
            // visually distinct here.
            expect(lines[0].classes()).toContain('stroke-blue-500');
            expect(lines[1].classes()).not.toContain('stroke-blue-500');
        });

        it('highlights the line for the row the camera currently stands in', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [band({ letter: 'A' }), band({ letter: 'B' })],
                },
            });
            rafCallback?.(0);
            await wrapper.vm.$nextTick();

            const linesBefore = wrapper
                .get('[data-testid="map-3d-mini-map"]')
                .findAll('line');
            expect(linesBefore[0].classes()).toContain('stroke-blue-500');
            expect(linesBefore[1].classes()).not.toContain('stroke-blue-500');

            (
                wrapper.vm as unknown as {
                    focusCell: (r: string, c: number, f: number) => void;
                }
            ).focusCell('B', 1, 1);
            rafCallback?.(16);
            await wrapper.vm.$nextTick();

            const linesAfter = wrapper
                .get('[data-testid="map-3d-mini-map"]')
                .findAll('line');
            expect(linesAfter[0].classes()).not.toContain('stroke-blue-500');
            expect(linesAfter[1].classes()).toContain('stroke-blue-500');
        });

        it('moves the position marker as the camera moves (e.g. via focusCell)', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [band({ letter: 'A' }), band({ letter: 'B' })],
                },
            });
            rafCallback?.(0);
            await wrapper.vm.$nextTick();
            const before = wrapper
                .get('[data-testid="map-3d-mini-map-marker"]')
                .attributes('style');

            (
                wrapper.vm as unknown as {
                    focusCell: (r: string, c: number, f: number) => void;
                }
            ).focusCell('B', 5, 1);
            rafCallback?.(16);
            await wrapper.vm.$nextTick();

            const after = wrapper
                .get('[data-testid="map-3d-mini-map-marker"]')
                .attributes('style');
            expect(after).not.toBe(before);
        });

        it("moves the marker toward the mini-map's right side while strafing toward screen-right (D)", async () => {
            // Regression test: the marker used to move opposite to the
            // strafe key actually pressed (world -X, which D moves the
            // camera toward — see the "strafes toward screen-right" test
            // above — used to map to a *smaller* left%, i.e. the mini-map's
            // left side, contradicting what the player just pressed).
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [band({ letter: 'A' }), band({ letter: 'B' })],
                },
            });

            (
                wrapper.vm as unknown as {
                    focusCell: (r: string, c: number, f: number) => void;
                }
            ).focusCell('B', 1, 1);
            rafCallback?.(0);
            await wrapper.vm.$nextTick();
            const before = Number(
                wrapper
                    .get('[data-testid="map-3d-mini-map-marker"]')
                    .attributes('style')
                    ?.match(/left:\s*([\d.]+)%/)?.[1],
            );

            wrapper.get('[data-testid="map-3d-viewport"]').trigger('keydown', {
                key: 'd',
            });
            rafCallback?.(1000);
            await wrapper.vm.$nextTick();
            const after = Number(
                wrapper
                    .get('[data-testid="map-3d-mini-map-marker"]')
                    .attributes('style')
                    ?.match(/left:\s*([\d.]+)%/)?.[1],
            );

            expect(after).toBeGreaterThan(before);
        });
    });

    describe('row-label floor signage', () => {
        /** `updateRowLabels()` bails out on a zero-size container, same guard `onResize` uses — stub a real size so its screen projection actually runs. */
        function stubViewportClientSize(
            wrapper: ReturnType<typeof mount>,
            width = 500,
            height = 500,
        ): void {
            const element = wrapper.get('[data-testid="map-3d-viewport"]')
                .element as HTMLElement;
            Object.defineProperty(element, 'clientWidth', {
                value: width,
                configurable: true,
            });
            Object.defineProperty(element, 'clientHeight', {
                value: height,
                configurable: true,
            });
        }

        it('shows one label per row while walking, and none while orbiting', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [band({ letter: 'A' }), band({ letter: 'B' })],
                },
            });
            stubViewportClientSize(wrapper);
            rafCallback?.(0);
            await wrapper.vm.$nextTick();

            const labels = wrapper.findAll('[data-testid="map-3d-row-label"]');
            expect(labels.map((label) => label.text()).sort()).toEqual([
                'A',
                'B',
            ]);

            (
                wrapper.vm as unknown as {
                    setCameraMode: (mode: 'walk' | 'orbit') => void;
                }
            ).setCameraMode('orbit');
            rafCallback?.(16);
            await wrapper.vm.$nextTick();

            expect(
                wrapper.findAll('[data-testid="map-3d-row-label"]'),
            ).toHaveLength(0);
        });

        it('hides a row label once you turn to face away from it', async () => {
            const wrapper = mount(CellMap3D, {
                props: { bands: [band({ letter: 'A' })] },
            });
            stubViewportClientSize(wrapper);
            rafCallback?.(0);
            await wrapper.vm.$nextTick();

            expect(
                wrapper.findAll('[data-testid="map-3d-row-label"]'),
            ).toHaveLength(1);

            const element = wrapper.get(
                '[data-testid="map-3d-viewport"]',
            ).element;
            element.dispatchEvent(
                new MouseEvent('pointerdown', { clientX: 0 }),
            );
            element.dispatchEvent(
                new MouseEvent('pointermove', {
                    clientX: 180 / YAW_DRAG_SENSITIVITY,
                }),
            );
            rafCallback?.(16);
            await wrapper.vm.$nextTick();

            expect(
                wrapper.findAll('[data-testid="map-3d-row-label"]'),
            ).toHaveLength(0);
        });

        it('moves a row label further from center as the camera walks past it', async () => {
            const wrapper = mount(CellMap3D, {
                props: { bands: [band({ letter: 'A' })] },
            });
            stubViewportClientSize(wrapper);
            rafCallback?.(0);
            await wrapper.vm.$nextTick();
            const before = wrapper
                .get('[data-testid="map-3d-row-label"]')
                .attributes('style');

            wrapper.get('[data-testid="map-3d-viewport"]').trigger('keydown', {
                key: 'w',
            });
            rafCallback?.(1000);
            await wrapper.vm.$nextTick();

            const after = wrapper.find('[data-testid="map-3d-row-label"]');

            // Walking forward past the sign eventually puts it behind the
            // camera (out of frame) — either the position changed, or it
            // disappeared outright, but it can't be unaffected by walking.
            if (after.exists()) {
                expect(after.attributes('style')).not.toBe(before);
            } else {
                expect(after.exists()).toBe(false);
            }
        });
    });

    describe('sprint', () => {
        // A band with plenty of depth so movement never clamps against the
        // warehouse bounds mid-test, which would otherwise mask the speed
        // difference these tests are asserting on.
        function spaciousBand() {
            return band({ items: [item({ cellNumber: 100 })] });
        }

        it('covers more distance per frame while Ctrl is held', () => {
            const wrapper = mount(CellMap3D, {
                props: { bands: [spaciousBand()] },
            });
            const viewport = wrapper.get('[data-testid="map-3d-viewport"]');

            viewport.trigger('keydown', { key: 'Control' });
            viewport.trigger('keydown', { key: 'w' });
            rafCallback?.(0);
            rafCallback?.(1000);

            expect(lastCamera().position.z).toBeGreaterThan(WALK_SPEED);
        });

        it('drops back to normal speed once Ctrl is released', () => {
            const wrapper = mount(CellMap3D, {
                props: { bands: [spaciousBand()] },
            });
            const viewport = wrapper.get('[data-testid="map-3d-viewport"]');

            viewport.trigger('keydown', { key: 'Control' });
            viewport.trigger('keydown', { key: 'w' });
            rafCallback?.(0);
            rafCallback?.(1000);
            viewport.trigger('keyup', { key: 'Control' });
            const sprintedZ = lastCamera().position.z;

            rafCallback?.(2000);

            expect(lastCamera().position.z - sprintedZ).toBeCloseTo(WALK_SPEED);
        });

        it('resets the held-Ctrl sprint on blur, like other held keys', () => {
            const wrapper = mount(CellMap3D, {
                props: { bands: [spaciousBand()] },
            });
            const viewport = wrapper.get('[data-testid="map-3d-viewport"]');

            viewport.trigger('keydown', { key: 'Control' });
            viewport.trigger('blur');
            viewport.trigger('keydown', { key: 'w' });
            rafCallback?.(0);
            rafCallback?.(1000);

            expect(lastCamera().position.z).toBe(WALK_SPEED);
        });

        function stubTouchDeviceForSprint() {
            vi.stubGlobal('matchMedia', () => ({
                matches: true,
                media: '',
                addEventListener: () => {},
                removeEventListener: () => {},
            }));
        }

        it('tapping the touch sprint button boosts speed without needing Ctrl', async () => {
            stubTouchDeviceForSprint();
            const wrapper = mount(CellMap3D, {
                props: { bands: [spaciousBand()] },
            });
            await wrapper.vm.$nextTick();

            await wrapper
                .get('[data-testid="touch-sprint-toggle"]')
                .trigger('click');
            wrapper.get('[data-testid="map-3d-viewport"]').trigger('keydown', {
                key: 'w',
            });
            rafCallback?.(0);
            rafCallback?.(1000);

            expect(lastCamera().position.z).toBeGreaterThan(WALK_SPEED);
        });

        it('labels the sprint toggle for assistive tech', async () => {
            stubTouchDeviceForSprint();
            const wrapper = mount(CellMap3D, {
                props: { bands: [spaciousBand()] },
            });
            await wrapper.vm.$nextTick();

            expect(
                wrapper
                    .get('[data-testid="touch-sprint-toggle"]')
                    .attributes('aria-label'),
            ).toBe(t('cells.map.controls.touch.sprint'));
        });

        it('toggles back off on a second tap', async () => {
            stubTouchDeviceForSprint();
            const wrapper = mount(CellMap3D, {
                props: { bands: [spaciousBand()] },
            });
            await wrapper.vm.$nextTick();
            const toggle = wrapper.get('[data-testid="touch-sprint-toggle"]');

            await toggle.trigger('click');
            expect(toggle.attributes('aria-pressed')).toBe('true');

            await toggle.trigger('click');
            expect(toggle.attributes('aria-pressed')).toBe('false');

            wrapper.get('[data-testid="map-3d-viewport"]').trigger('keydown', {
                key: 'w',
            });
            rafCallback?.(0);
            rafCallback?.(1000);

            expect(lastCamera().position.z).toBe(WALK_SPEED);
        });
    });

    describe('orbit-mode hover', () => {
        it('shows a hover outline over a box before it is clicked', () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({ letter: 'A', items: [item({ cellNumber: 5 })] }),
                    ],
                },
            });
            (
                wrapper.vm as unknown as {
                    setCameraMode: (mode: 'walk' | 'orbit') => void;
                }
            ).setCameraMode('orbit');
            stubViewportRect(wrapper);
            rafCallback?.(0);

            expect(registry().lineSegments).toHaveLength(0);

            lastRaycaster().intersectObjects.mockReturnValue([cellBoxMesh(5)]);
            const element = wrapper.get(
                '[data-testid="map-3d-viewport"]',
            ).element;
            element.dispatchEvent(
                new PointerEvent('pointermove', {
                    pointerId: 1,
                    clientX: 50,
                    clientY: 50,
                }),
            );

            expect(registry().lineSegments).toHaveLength(1);
            expect(registry().lineSegments[0].visible).toBe(true);
        });

        it('clears the hover outline when the pointer moves off the box', () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({ letter: 'A', items: [item({ cellNumber: 5 })] }),
                    ],
                },
            });
            (
                wrapper.vm as unknown as {
                    setCameraMode: (mode: 'walk' | 'orbit') => void;
                }
            ).setCameraMode('orbit');
            stubViewportRect(wrapper);
            rafCallback?.(0);

            lastRaycaster().intersectObjects.mockReturnValue([cellBoxMesh(5)]);
            const element = wrapper.get(
                '[data-testid="map-3d-viewport"]',
            ).element;
            element.dispatchEvent(
                new PointerEvent('pointermove', {
                    pointerId: 1,
                    clientX: 50,
                    clientY: 50,
                }),
            );
            expect(registry().lineSegments[0].visible).toBe(true);

            lastRaycaster().intersectObjects.mockReturnValue([]);
            element.dispatchEvent(
                new PointerEvent('pointermove', {
                    pointerId: 1,
                    clientX: 200,
                    clientY: 200,
                }),
            );

            expect(registry().lineSegments[0].visible).toBe(false);
        });

        it('clears a stale hover outline when the hovered cell is removed by a data change', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({
                            letter: 'A',
                            items: [
                                item({ cellNumber: 1 }),
                                item({ cellNumber: 5 }),
                            ],
                        }),
                    ],
                },
            });
            (
                wrapper.vm as unknown as {
                    setCameraMode: (mode: 'walk' | 'orbit') => void;
                }
            ).setCameraMode('orbit');
            stubViewportRect(wrapper);
            rafCallback?.(0);

            lastRaycaster().intersectObjects.mockReturnValue([cellBoxMesh(5)]);
            const element = wrapper.get(
                '[data-testid="map-3d-viewport"]',
            ).element;
            element.dispatchEvent(
                new PointerEvent('pointermove', {
                    pointerId: 1,
                    clientX: 50,
                    clientY: 50,
                }),
            );

            const hoverOutline = () =>
                registry().lineSegments.find(
                    (line) => line.name === 'hover-outline',
                );
            expect(hoverOutline()?.visible).toBe(true);

            await wrapper.setProps({
                bands: [
                    band({ letter: 'A', items: [item({ cellNumber: 1 })] }),
                ],
            });

            expect(hoverOutline()?.visible).toBe(false);
        });

        it('never shows a hover outline while walking', () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({ letter: 'A', items: [item({ cellNumber: 5 })] }),
                    ],
                },
            });
            stubViewportRect(wrapper);
            rafCallback?.(0);

            lastRaycaster().intersectObjects.mockReturnValue([cellBoxMesh(5)]);
            const element = wrapper.get(
                '[data-testid="map-3d-viewport"]',
            ).element;
            element.dispatchEvent(
                new PointerEvent('pointermove', {
                    pointerId: 1,
                    clientX: 50,
                    clientY: 50,
                }),
            );

            expect(registry().lineSegments).toHaveLength(0);
            expect(lastRaycaster().intersectObjects).not.toHaveBeenCalled();
        });
    });

    describe('touch controls', () => {
        function stubTouchDevice(matches = true) {
            vi.stubGlobal('matchMedia', (query: string) => ({
                matches,
                media: query,
                addEventListener: () => {},
                removeEventListener: () => {},
            }));
        }

        it('shows on-screen move/fly buttons instead of the keyboard legend on touch devices', async () => {
            stubTouchDevice();
            const wrapper = mount(CellMap3D, { props: { bands: [band()] } });
            await wrapper.vm.$nextTick();

            expect(
                wrapper.find('[data-testid="map-3d-move-pad"]').exists(),
            ).toBe(true);
            expect(
                wrapper.find('[data-testid="map-3d-fly-pad"]').exists(),
            ).toBe(true);
            expect(
                wrapper.find('[data-testid="map-3d-controls-legend"]').exists(),
            ).toBe(false);
            expect(
                wrapper
                    .get('[data-testid="map-3d-viewport"]')
                    .attributes('aria-label'),
            ).toBe(t('cells.map.walkHintTouch'));
            expect(
                wrapper.find('[data-testid="map-3d-state-legend"]').exists(),
            ).toBe(true);
        });

        it('keeps the keyboard legend and hides touch buttons on non-touch devices', async () => {
            stubTouchDevice(false);
            const wrapper = mount(CellMap3D, { props: { bands: [band()] } });
            await wrapper.vm.$nextTick();

            expect(
                wrapper.find('[data-testid="map-3d-controls-legend"]').exists(),
            ).toBe(true);
            expect(
                wrapper.find('[data-testid="map-3d-move-pad"]').exists(),
            ).toBe(false);
        });

        it('moves forward while the on-screen forward button is held, like W', async () => {
            stubTouchDevice();
            const wrapper = mount(CellMap3D, { props: { bands: [band()] } });
            await wrapper.vm.$nextTick();

            wrapper
                .get('[data-testid="map-3d-move-pad"] button')
                .trigger('pointerdown');

            rafCallback?.(0);
            rafCallback?.(1000);

            expect(lastCamera().position.z).toBe(WALK_SPEED);
        });

        it('stops moving once the on-screen button is released', async () => {
            stubTouchDevice();
            const wrapper = mount(CellMap3D, { props: { bands: [band()] } });
            await wrapper.vm.$nextTick();
            const forwardButton = wrapper.get(
                '[data-testid="map-3d-move-pad"] button',
            );

            forwardButton.trigger('pointerdown');
            rafCallback?.(0);
            rafCallback?.(1000);
            forwardButton.trigger('pointerup');
            rafCallback?.(2000);

            expect(lastCamera().position.z).toBe(WALK_SPEED);
        });

        it('flies upward while the on-screen up button is held', async () => {
            stubTouchDevice();
            const wrapper = mount(CellMap3D, {
                props: { bands: [band({ items: [item({ flatNumber: 3 })] })] },
            });
            await wrapper.vm.$nextTick();

            rafCallback?.(0);
            const startY = lastCamera().position.y;

            wrapper
                .get('[data-testid="map-3d-fly-pad"] button')
                .trigger('pointerdown');
            rafCallback?.(1000);

            expect(lastCamera().position.y).toBe(startY + WALK_SPEED);
        });

        it('does not start a look-drag when pressing an on-screen move button', async () => {
            stubTouchDevice();
            const wrapper = mount(CellMap3D, { props: { bands: [band()] } });
            await wrapper.vm.$nextTick();

            rafCallback?.(0);
            const camera = lastCamera();
            const beforeCall = camera.lookAt.mock.calls.at(-1);
            const beforeDirectionZ = (beforeCall?.[2] ?? 0) - camera.position.z;

            // Holding the button moves the camera forward (position.z
            // increases), but the look direction itself must stay
            // unchanged — pressing it must not also yaw/pitch the camera
            // the way dragging the viewport does.
            wrapper
                .get('[data-testid="map-3d-move-pad"] button')
                .trigger('pointerdown');
            rafCallback?.(16);

            const afterCall = camera.lookAt.mock.calls.at(-1);
            const afterDirectionZ = (afterCall?.[2] ?? 0) - camera.position.z;

            expect(afterDirectionZ).toBeCloseTo(beforeDirectionZ);
        });
    });

    describe('overview / orbit camera mode', () => {
        function setCameraMode(
            wrapper: ReturnType<typeof mount>,
            mode: 'walk' | 'orbit',
        ) {
            (
                wrapper.vm as unknown as {
                    setCameraMode: (mode: 'walk' | 'orbit') => void;
                }
            ).setCameraMode(mode);
        }

        it('emits camera-mode-change when switching to orbit and back', () => {
            const wrapper = mount(CellMap3D, { props: { bands: [band()] } });

            setCameraMode(wrapper, 'orbit');
            setCameraMode(wrapper, 'walk');

            expect(wrapper.emitted('camera-mode-change')).toEqual([
                ['orbit'],
                ['walk'],
            ]);
        });

        it('toggles walk/orbit via the O key, without needing the toolbar button', () => {
            const wrapper = mount(CellMap3D, { props: { bands: [band()] } });
            const viewport = wrapper.get('[data-testid="map-3d-viewport"]');

            viewport.trigger('keydown', { key: 'o' });
            expect(wrapper.emitted('camera-mode-change')).toEqual([['orbit']]);

            viewport.trigger('keydown', { key: 'O' });
            expect(wrapper.emitted('camera-mode-change')).toEqual([
                ['orbit'],
                ['walk'],
            ]);
        });

        it('positions the camera away from the warehouse center, looking at it', () => {
            const bands = [band({ letter: 'A' }), band({ letter: 'B' })];
            const wrapper = mount(CellMap3D, { props: { bands } });

            setCameraMode(wrapper, 'orbit');
            rafCallback?.(0);

            const center = orbitCenterFor(bands);
            const camera = lastCamera();
            expect(distanceFromCenter(camera.position, center)).toBeGreaterThan(
                0,
            );
            expect(camera.lookAt).toHaveBeenLastCalledWith(
                center.x,
                center.y,
                center.z,
            );
        });

        it('orbits (does not walk) on WASD/arrow keys, and zooms on Space/Shift', () => {
            const bands = [band()];
            const wrapper = mount(CellMap3D, { props: { bands } });
            setCameraMode(wrapper, 'orbit');
            rafCallback?.(0);
            const center = orbitCenterFor(bands);
            const before = { ...lastCamera().position };
            const startDistance = distanceFromCenter(before, center);
            const viewport = wrapper.get('[data-testid="map-3d-viewport"]');

            // Orbiting via keyboard moves the camera (unlike walk mode's
            // stepPosition), and the position changes — not staying still the
            // way a no-op would.
            viewport.trigger('keydown', { key: 'd' });
            rafCallback?.(1000);
            expect(lastCamera().position).not.toEqual(before);
            viewport.trigger('keyup', { key: 'd' });

            const afterYaw = { ...lastCamera().position };

            // Space zooms in (shrinks the distance from center) rather than
            // flying up like it does in walk mode.
            viewport.trigger('keydown', { key: ' ' });
            rafCallback?.(2000);
            const zoomedInDistance = distanceFromCenter(
                lastCamera().position,
                center,
            );
            expect(zoomedInDistance).toBeLessThan(
                distanceFromCenter(afterYaw, center),
            );
            expect(zoomedInDistance).toBeLessThan(startDistance);
        });

        it('orbits the camera around the center when dragging', () => {
            const wrapper = mount(CellMap3D, { props: { bands: [band()] } });
            setCameraMode(wrapper, 'orbit');
            rafCallback?.(0);
            const before = { ...lastCamera().position };

            const element = wrapper.get(
                '[data-testid="map-3d-viewport"]',
            ).element;
            element.dispatchEvent(
                new MouseEvent('pointerdown', { clientX: 0, clientY: 0 }),
            );
            element.dispatchEvent(
                new MouseEvent('pointermove', { clientX: 120, clientY: 0 }),
            );
            rafCallback?.(16);

            expect(lastCamera().position).not.toEqual(before);
        });

        it('zooms out on wheel scroll down and in on scroll up', () => {
            const bands = [band()];
            const wrapper = mount(CellMap3D, { props: { bands } });
            setCameraMode(wrapper, 'orbit');
            rafCallback?.(0);
            const center = orbitCenterFor(bands);
            const element = wrapper.get(
                '[data-testid="map-3d-viewport"]',
            ).element;
            const startDistance = distanceFromCenter(
                lastCamera().position,
                center,
            );

            element.dispatchEvent(new WheelEvent('wheel', { deltaY: 100 }));
            rafCallback?.(16);
            const zoomedOut = distanceFromCenter(lastCamera().position, center);
            expect(zoomedOut).toBeGreaterThan(startDistance);

            element.dispatchEvent(new WheelEvent('wheel', { deltaY: -200 }));
            rafCallback?.(32);
            const zoomedIn = distanceFromCenter(lastCamera().position, center);
            expect(zoomedIn).toBeLessThan(zoomedOut);
        });

        it('does not zoom on wheel scroll while walking', () => {
            const wrapper = mount(CellMap3D, { props: { bands: [band()] } });
            rafCallback?.(0);
            const before = { ...lastCamera().position };

            wrapper
                .get('[data-testid="map-3d-viewport"]')
                .element.dispatchEvent(
                    new WheelEvent('wheel', { deltaY: 500 }),
                );
            rafCallback?.(16);

            expect(lastCamera().position).toEqual(before);
        });

        it('zooms in as a two-finger pinch spreads apart', () => {
            const bands = [band()];
            const wrapper = mount(CellMap3D, { props: { bands } });
            setCameraMode(wrapper, 'orbit');
            rafCallback?.(0);
            const center = orbitCenterFor(bands);
            const element = wrapper.get(
                '[data-testid="map-3d-viewport"]',
            ).element;
            const startDistance = distanceFromCenter(
                lastCamera().position,
                center,
            );

            element.dispatchEvent(
                new PointerEvent('pointerdown', {
                    pointerId: 1,
                    clientX: 100,
                    clientY: 100,
                }),
            );
            element.dispatchEvent(
                new PointerEvent('pointerdown', {
                    pointerId: 2,
                    clientX: 200,
                    clientY: 100,
                }),
            );
            element.dispatchEvent(
                new PointerEvent('pointermove', {
                    pointerId: 1,
                    clientX: 50,
                    clientY: 100,
                }),
            );
            element.dispatchEvent(
                new PointerEvent('pointermove', {
                    pointerId: 2,
                    clientX: 250,
                    clientY: 100,
                }),
            );
            rafCallback?.(16);

            const zoomedIn = distanceFromCenter(lastCamera().position, center);
            expect(zoomedIn).toBeLessThan(startDistance);
        });

        it('hides the panel on entering orbit mode until a cell is clicked', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({ letter: 'A', items: [item({ cellNumber: 1 })] }),
                    ],
                },
            });
            rafCallback?.(0);
            await wrapper.vm.$nextTick();
            expect(
                wrapper.find('[data-testid="map-3d-faced-cell"]').exists(),
            ).toBe(true);

            setCameraMode(wrapper, 'orbit');
            rafCallback?.(16);
            await wrapper.vm.$nextTick();

            expect(
                wrapper.find('[data-testid="map-3d-faced-cell"]').exists(),
            ).toBe(false);
        });

        it('selects the clicked cell into the panel without moving the camera', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({
                            letter: 'A',
                            items: [item({ cellNumber: 5, state: 'full' })],
                        }),
                    ],
                },
            });
            setCameraMode(wrapper, 'orbit');
            stubViewportRect(wrapper);
            rafCallback?.(0);
            await wrapper.vm.$nextTick();

            lastRaycaster().intersectObjects.mockReturnValue([cellBoxMesh(5)]);
            const cameraBefore = { ...lastCamera().position };

            const element = wrapper.get(
                '[data-testid="map-3d-viewport"]',
            ).element;
            element.dispatchEvent(
                new PointerEvent('pointerdown', {
                    pointerId: 1,
                    clientX: 50,
                    clientY: 50,
                }),
            );
            element.dispatchEvent(
                new PointerEvent('pointerup', {
                    pointerId: 1,
                    clientX: 50,
                    clientY: 50,
                }),
            );
            rafCallback?.(16);
            await wrapper.vm.$nextTick();

            expect(
                wrapper.get('[data-testid="map-3d-faced-cell"]').text(),
            ).toContain(formatSlot('A', 5, 1));
            expect(lastCamera().position).toEqual(cameraBefore);
        });

        it('clears the selection when clicking empty space', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({ letter: 'A', items: [item({ cellNumber: 5 })] }),
                    ],
                },
            });
            setCameraMode(wrapper, 'orbit');
            stubViewportRect(wrapper);
            rafCallback?.(0);

            lastRaycaster().intersectObjects.mockReturnValue([cellBoxMesh(5)]);
            const element = wrapper.get(
                '[data-testid="map-3d-viewport"]',
            ).element;
            const click = () => {
                element.dispatchEvent(
                    new PointerEvent('pointerdown', {
                        pointerId: 1,
                        clientX: 50,
                        clientY: 50,
                    }),
                );
                element.dispatchEvent(
                    new PointerEvent('pointerup', {
                        pointerId: 1,
                        clientX: 50,
                        clientY: 50,
                    }),
                );
            };

            click();
            rafCallback?.(16);
            await wrapper.vm.$nextTick();
            expect(
                wrapper.find('[data-testid="map-3d-faced-cell"]').exists(),
            ).toBe(true);

            lastRaycaster().intersectObjects.mockReturnValue([]);
            click();
            rafCallback?.(32);
            await wrapper.vm.$nextTick();

            expect(
                wrapper.find('[data-testid="map-3d-faced-cell"]').exists(),
            ).toBe(false);
        });

        it('does not select when the pointer drags past the click threshold', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({ letter: 'A', items: [item({ cellNumber: 5 })] }),
                    ],
                },
            });
            setCameraMode(wrapper, 'orbit');
            stubViewportRect(wrapper);
            rafCallback?.(0);

            lastRaycaster().intersectObjects.mockReturnValue([cellBoxMesh(5)]);
            const element = wrapper.get(
                '[data-testid="map-3d-viewport"]',
            ).element;
            element.dispatchEvent(
                new PointerEvent('pointerdown', {
                    pointerId: 1,
                    clientX: 50,
                    clientY: 50,
                }),
            );
            element.dispatchEvent(
                new PointerEvent('pointermove', {
                    pointerId: 1,
                    clientX: 90,
                    clientY: 50,
                }),
            );
            element.dispatchEvent(
                new PointerEvent('pointerup', {
                    pointerId: 1,
                    clientX: 90,
                    clientY: 50,
                }),
            );
            rafCallback?.(16);
            await wrapper.vm.$nextTick();

            expect(lastRaycaster().intersectObjects).not.toHaveBeenCalled();
            expect(
                wrapper.find('[data-testid="map-3d-faced-cell"]').exists(),
            ).toBe(false);
        });

        it('clears the selection when switching back to walk mode', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({
                            letter: 'A',
                            items: [
                                item({ cellNumber: 1 }),
                                item({ cellNumber: 5 }),
                            ],
                        }),
                    ],
                },
            });
            setCameraMode(wrapper, 'orbit');
            stubViewportRect(wrapper);
            rafCallback?.(0);

            lastRaycaster().intersectObjects.mockReturnValue([cellBoxMesh(5)]);
            const element = wrapper.get(
                '[data-testid="map-3d-viewport"]',
            ).element;
            element.dispatchEvent(
                new PointerEvent('pointerdown', {
                    pointerId: 1,
                    clientX: 50,
                    clientY: 50,
                }),
            );
            element.dispatchEvent(
                new PointerEvent('pointerup', {
                    pointerId: 1,
                    clientX: 50,
                    clientY: 50,
                }),
            );
            rafCallback?.(16);
            await wrapper.vm.$nextTick();
            expect(
                wrapper.find('[data-testid="map-3d-faced-cell"]').exists(),
            ).toBe(true);

            setCameraMode(wrapper, 'walk');
            rafCallback?.(32);
            await wrapper.vm.$nextTick();

            // Walking resumes its own auto-tracked faced cell (row A, cell 1,
            // where the walk camera has stood the whole time) instead of the
            // orbit-clicked cell 5.
            const panelText = wrapper
                .get('[data-testid="map-3d-faced-cell"]')
                .text();
            expect(panelText).toContain(formatSlot('A', 1, 1));
            expect(panelText).not.toContain(formatSlot('A', 5, 1));
        });

        it('shows the orbit legend instead of the walk controls/touch pad while orbiting', async () => {
            const wrapper = mount(CellMap3D, { props: { bands: [band()] } });
            await wrapper.vm.$nextTick();

            setCameraMode(wrapper, 'orbit');
            await wrapper.vm.$nextTick();

            const legend = wrapper.get('[data-testid="map-3d-orbit-legend"]');
            expect(legend.text()).toContain(
                t('cells.map.controls.orbit.rotateLabel'),
            );
            expect(legend.text()).toContain(
                t('cells.map.controls.orbit.zoomLabel'),
            );
            expect(legend.text()).toContain(
                t('cells.map.controls.orbit.keyboardLabel'),
            );
            expect(legend.text()).toContain(
                t('cells.map.controls.orbit.selectLabel'),
            );
            expect(
                wrapper.find('[data-testid="map-3d-controls-legend"]').exists(),
            ).toBe(false);
            expect(
                wrapper
                    .get('[data-testid="map-3d-viewport"]')
                    .attributes('aria-label'),
            ).toBe(t('cells.map.orbitHint'));
        });

        it('selects the cell centered in the viewport when pressing Enter, the keyboard equivalent of click-to-select', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({
                            letter: 'A',
                            items: [item({ cellNumber: 5, state: 'full' })],
                        }),
                    ],
                },
            });
            setCameraMode(wrapper, 'orbit');
            stubViewportRect(wrapper);
            rafCallback?.(0);
            await wrapper.vm.$nextTick();

            lastRaycaster().intersectObjects.mockReturnValue([cellBoxMesh(5)]);
            const cameraBefore = { ...lastCamera().position };

            wrapper.get('[data-testid="map-3d-viewport"]').trigger('keydown', {
                key: 'Enter',
            });
            rafCallback?.(16);
            await wrapper.vm.$nextTick();

            expect(
                wrapper.get('[data-testid="map-3d-faced-cell"]').text(),
            ).toContain(formatSlot('A', 5, 1));
            // Selecting inspects the cell, it doesn't teleport the camera.
            expect(lastCamera().position).toEqual(cameraBefore);
        });

        it('clears the selection when pressing Enter over empty space', async () => {
            const wrapper = mount(CellMap3D, {
                props: {
                    bands: [
                        band({ letter: 'A', items: [item({ cellNumber: 5 })] }),
                    ],
                },
            });
            setCameraMode(wrapper, 'orbit');
            stubViewportRect(wrapper);
            rafCallback?.(0);

            lastRaycaster().intersectObjects.mockReturnValue([cellBoxMesh(5)]);
            wrapper.get('[data-testid="map-3d-viewport"]').trigger('keydown', {
                key: 'Enter',
            });
            rafCallback?.(16);
            await wrapper.vm.$nextTick();
            expect(
                wrapper.find('[data-testid="map-3d-faced-cell"]').exists(),
            ).toBe(true);

            lastRaycaster().intersectObjects.mockReturnValue([]);
            wrapper.get('[data-testid="map-3d-viewport"]').trigger('keydown', {
                key: 'Enter',
            });
            rafCallback?.(32);
            await wrapper.vm.$nextTick();

            expect(
                wrapper.find('[data-testid="map-3d-faced-cell"]').exists(),
            ).toBe(false);
        });
    });

    describe('resizing', () => {
        it("resizes the renderer and updates the camera's aspect when the container size changes", () => {
            const wrapper = mount(CellMap3D, { props: { bands: [band()] } });
            const container = wrapper.get('[data-testid="map-3d-viewport"]')
                .element as HTMLElement;
            Object.defineProperty(container, 'clientWidth', {
                value: 800,
                configurable: true,
            });
            Object.defineProperty(container, 'clientHeight', {
                value: 400,
                configurable: true,
            });

            resizeCallback?.();

            expect(lastCamera().aspect).toBe(2);
            expect(lastCamera().updateProjectionMatrix).toHaveBeenCalled();
            expect(registry().renderers.at(-1)?.setSize).toHaveBeenCalledWith(
                800,
                400,
            );
        });

        it('ignores a resize while the container still reports zero size', () => {
            mount(CellMap3D, { props: { bands: [band()] } });
            const renderer = registry().renderers.at(-1) as {
                setSize: ReturnType<typeof vi.fn>;
            };
            renderer.setSize.mockClear();

            resizeCallback?.();

            expect(renderer.setSize).not.toHaveBeenCalled();
        });
    });
});

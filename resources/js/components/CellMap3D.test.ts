import { mount } from '@vue/test-utils';
import * as THREE from 'three';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { CELL_STATE_COLOR } from '@/lib/cellStateColor';
import { t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import {
    cellWorldZ,
    EYE_HEIGHT,
    flatWorldY,
    rowWorldX,
    WALK_SPEED,
    YAW_DRAG_SENSITIVITY,
} from '@/lib/mapWalker';
import type { CellMap3DBand, CellMap3DItem } from '@/types/admin';
import CellMap3D from './CellMap3D.vue';

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
        dispose = vi.fn();

        constructor(options?: { color?: unknown }) {
            this.color = options?.color;
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
        render = vi.fn();

        constructor() {
            registry.renderers.push(this);
        }
    }

    const registry: {
        cameras: PerspectiveCamera[];
        renderers: WebGLRenderer[];
        meshes: Mesh[];
        lineSegments: LineSegments[];
    } = { cameras: [], renderers: [], meshes: [], lineSegments: [] };

    return {
        __registry: registry,
        Scene,
        Group,
        Mesh,
        LineSegments,
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
    };
});

type Registry = {
    cameras: Array<{
        position: { x: number; y: number; z: number };
        lookAt: ReturnType<typeof vi.fn>;
    }>;
    renderers: Array<{ dispose: ReturnType<typeof vi.fn> }>;
    meshes: Array<{
        name: string;
        position: { x: number; y: number; z: number };
        material: { color: unknown; dispose: ReturnType<typeof vi.fn> };
    }>;
    lineSegments: Array<{ material: { color: unknown } }>;
};

function registry(): Registry {
    return (THREE as unknown as { __registry: Registry }).__registry;
}

function lastCamera() {
    const cameras = registry().cameras;

    return cameras[cameras.length - 1];
}

let rafCallback: ((time: number) => void) | null = null;

function item(overrides: Partial<CellMap3DItem> = {}): CellMap3DItem {
    return {
        cellNumber: 1,
        flatNumber: 1,
        state: 'empty',
        highlighted: false,
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
    registry().cameras = [];
    registry().renderers = [];
    registry().meshes = [];
    registry().lineSegments = [];

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
            observe() {}
            disconnect() {}
        },
    );
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('CellMap3D', () => {
    it('renders a focusable viewport with a mounted canvas', () => {
        const wrapper = mount(CellMap3D, { props: { bands: [band()] } });

        const viewport = wrapper.get('[data-testid="map-3d-viewport"]');
        expect(viewport.attributes('tabindex')).toBe('0');
        expect(wrapper.find('canvas').exists()).toBe(true);
    });

    it('shows a controls legend explaining movement, flying between flats, and looking', () => {
        const wrapper = mount(CellMap3D, { props: { bands: [band()] } });

        expect(wrapper.text()).toContain(t('cells.map.controls.moveLabel'));
        expect(wrapper.text()).toContain(t('cells.map.controls.flyLabel'));
        expect(wrapper.text()).toContain(t('cells.map.controls.lookLabel'));

        const keyCaps = wrapper.findAll('kbd').map((kbd) => kbd.text());
        expect(keyCaps).toEqual(['Space', 'Shift']);
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

    it('colors each cell mesh by state and outlines only highlighted/pulsing cells', () => {
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

        // Only cell boxes (not the floor or the shelf platforms) carry the
        // per-state color.
        const meshColors = registry()
            .meshes.filter((mesh) => mesh.name === 'cell-box')
            .map((mesh) => mesh.material.color);
        expect(meshColors).toEqual([
            CELL_STATE_COLOR.full.hex,
            CELL_STATE_COLOR.empty.hex,
            CELL_STATE_COLOR.opened.hex,
        ]);

        // Only the highlighted cell (index 0) and the pulsing cell (index 2)
        // get an outline; the plain empty cell (index 1) does not.
        expect(registry().lineSegments).toHaveLength(2);
        const outlineColors = registry().lineSegments.map(
            (line) => line.material.color,
        );
        expect(new Set(outlineColors).size).toBe(2); // highlight vs pulse use distinct colors
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
        const boxFlatYs = [
            ...new Set(
                registry()
                    .meshes.filter((mesh) => mesh.name === 'cell-box')
                    .map((mesh) => mesh.position.y),
            ),
        ].sort((a, b) => a - b);

        // One shelf per distinct flat level in the row, not one per cell —
        // this row has 3 boxes across 2 flat levels.
        expect(shelves).toHaveLength(2);
        expect(boxFlatYs).toEqual([flatWorldY(1), flatWorldY(2)]);

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
        const boxXs = new Set(
            registry()
                .meshes.filter((mesh) => mesh.name === 'cell-box')
                .map((mesh) => mesh.position.x),
        );

        // One set of 4 corner posts per row.
        expect(posts).toHaveLength(8);

        const rowAPosts = posts.filter(
            (post) => Math.abs(post.position.x - rowWorldX(0)) < 2,
        );
        expect(rowAPosts).toHaveLength(4);

        // Posts stand to the side of the boxes (never at the exact box X), and
        // reach up to cover the topmost flat.
        posts.forEach((post) => {
            expect(boxXs.has(post.position.x)).toBe(false);
            expect(post.position.y).toBeGreaterThan(0);
        });

        // Two distinct X offsets (left/right) and two distinct Z offsets
        // (front/back) per row — i.e. actual corners, not a single column.
        const rowAXs = new Set(rowAPosts.map((post) => post.position.x));
        const rowAZs = new Set(rowAPosts.map((post) => post.position.z));
        expect(rowAXs.size).toBe(2);
        expect(rowAZs.size).toBe(2);
    });

    it('disposes the previous meshes/materials when the bands prop changes', async () => {
        const wrapper = mount(CellMap3D, {
            props: {
                bands: [
                    band({
                        items: [item({ cellNumber: 1, state: 'full' })],
                    }),
                ],
            },
        });

        function cellBoxes() {
            return registry().meshes.filter((mesh) => mesh.name === 'cell-box');
        }

        const firstMaterial = cellBoxes()[0].material;

        await wrapper.setProps({
            bands: [
                band({
                    items: [item({ cellNumber: 1, state: 'opened' })],
                }),
            ],
        });

        expect(firstMaterial.dispose).toHaveBeenCalled();
        expect(cellBoxes().at(-1)?.material.color).toBe(
            CELL_STATE_COLOR.opened.hex,
        );
    });

    it('disposes the renderer on unmount', () => {
        const wrapper = mount(CellMap3D, { props: { bands: [band()] } });
        const renderer = registry().renderers.at(-1) as {
            dispose: ReturnType<typeof vi.fn>;
        };

        wrapper.unmount();

        expect(renderer.dispose).toHaveBeenCalled();
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
                                        product_name: 'Widgets',
                                        product_image_url: null,
                                        expiration_date: '2026-09-01',
                                        added_at: '2026-07-01T10:00:00Z',
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

        it('shows no panel when facing an empty gap with no matching cell', async () => {
            const wrapper = mount(CellMap3D, { props: { bands: [] } });

            rafCallback?.(0);
            await wrapper.vm.$nextTick();

            expect(
                wrapper.find('[data-testid="map-3d-faced-cell"]').exists(),
            ).toBe(false);
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
});

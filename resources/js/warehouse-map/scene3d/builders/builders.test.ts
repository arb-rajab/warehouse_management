import * as THREE from 'three';
import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { WarehouseGeometry } from '../../domain/geometry';
import { buildMatchIndex } from '../../domain/MatchIndex';
import { installFakeCanvas } from '../../testing/canvas';
import { makeWarehouse } from '../../testing/fixtures';
import { disposeTree } from '../dispose';
import { DEFAULT_PALETTE } from '../Palette';
import { SlotPicker } from '../picking/SlotPicker';
import { CeilingBuilder } from './CeilingBuilder';
import { FloorBuilder } from './FloorBuilder';
import { GaugeBuilder } from './GaugeBuilder';
import { LabelAtlas } from './LabelAtlas';
import { MarkerBuilder } from './MarkerBuilder';
import { PalletBuilder } from './PalletBuilder';
import { RackBuilder } from './RackBuilder';
import { SignBuilder } from './SignBuilder';

let restore: () => void;

beforeAll(() => {
    restore = installFakeCanvas();
});
afterAll(() => restore());

function drawCalls(root: THREE.Object3D): number {
    let count = 0;

    root.traverse((node) => {
        if (
            (node as THREE.Mesh).isMesh ||
            (node as THREE.LineSegments).isLineSegments
        ) {
            count++;
        }
    });

    return count;
}

function world(rows: number, cols: number, levels: number) {
    const warehouse = makeWarehouse(rows, cols, levels);
    const geometry = new WarehouseGeometry(rows, cols, levels);

    return { warehouse, geometry, atlas: new LabelAtlas() };
}

describe('scene builders', () => {
    it('draws the same number of meshes whatever the size of the warehouse (instancing)', () => {
        const build = (rows: number, cols: number) => {
            const { warehouse, geometry, atlas } = world(rows, cols, 3);
            const group = new THREE.Group();

            group.add(
                new FloorBuilder(geometry, DEFAULT_PALETTE, atlas).build(),
                new RackBuilder(geometry, DEFAULT_PALETTE).build(),
                new GaugeBuilder(geometry).build(warehouse.slots),
                new CeilingBuilder(geometry, DEFAULT_PALETTE).build(),
            );

            return drawCalls(group);
        };

        // the floor adds a few planes per aisle, but nothing grows with the number of slots
        const small = build(2, 4);
        const large = build(2, 44);

        expect(large).toBe(small);
    });

    it('draws one pallet, one carton stack and one marker per slot in single InstancedMeshes', () => {
        const { warehouse, geometry, atlas } = world(4, 10, 3);
        const builder = new PalletBuilder(geometry, DEFAULT_PALETTE, atlas);
        const group = builder.build(warehouse.slots);
        const occupied = warehouse.slots.filter(
            (s) => s.state !== 'empty',
        ).length;
        const empty = warehouse.slots.length - occupied;
        const instanced = group.children.filter(
            (c): c is THREE.InstancedMesh => c instanceof THREE.InstancedMesh,
        );
        const counts = instanced.map((m) => m.count);

        expect(counts).toContain(occupied); // pallets and cartons
        expect(
            counts.filter((c) => c === occupied).length,
        ).toBeGreaterThanOrEqual(2);
        expect(counts).toContain(empty);
        expect(warehouse.slots.some((s) => !s.active)).toBe(true);
        expect(counts).toContain(
            warehouse.slots.filter((s) => !s.active).length,
        );
        // one label mesh per product, never per slot
        expect(instanced.length).toBeLessThan(12);
    });

    it('colours opened cartons amber and sealed ones green, greying out what the filter hides', () => {
        const { warehouse, geometry, atlas } = world(2, 6, 3);
        const builder = new PalletBuilder(geometry, DEFAULT_PALETTE, atlas);
        const group = builder.build(warehouse.slots);
        const cargo = group.children.filter(
            (c): c is THREE.InstancedMesh => c instanceof THREE.InstancedMesh,
        )[1];
        const occupied = warehouse.slots.filter((s) => s.state !== 'empty');
        const read = (n: number) => {
            const c = new THREE.Color();

            cargo.getColorAt(n, c);

            return c.getHSL({ h: 0, s: 0, l: 0 });
        };

        builder.applyFilter({
            match: buildMatchIndex(warehouse.slots, {
                terms: [],
                state: 'all',
                expiring: false,
                productIds: [],
            }),
            floor: null,
        });

        const opened = occupied.findIndex((s) => s.state === 'opened');
        const full = occupied.findIndex((s) => s.state === 'full');

        expect(read(opened).h).toBeLessThan(0.2); // amber / yellow
        expect(read(full).h).toBeGreaterThan(0.3); // green

        const match = buildMatchIndex(warehouse.slots, {
            terms: ['nothing-matches'],
            state: 'all',
            expiring: false,
            productIds: [],
        });

        builder.applyFilter({ match, floor: null });

        expect(read(full).l).toBeLessThan(0.2); // greyed out
    });

    it('dims every other floor when one floor is selected', () => {
        const { warehouse, geometry, atlas } = world(2, 6, 3);
        const builder = new PalletBuilder(geometry, DEFAULT_PALETTE, atlas);
        const cargo = builder
            .build(warehouse.slots)
            .children.filter(
                (c): c is THREE.InstancedMesh =>
                    c instanceof THREE.InstancedMesh,
            )[1];
        const occupied = warehouse.slots.filter((s) => s.state !== 'empty');
        const none = buildMatchIndex(warehouse.slots, {
            terms: [],
            state: 'all',
            expiring: false,
            productIds: [],
        });

        builder.applyFilter({ match: none, floor: 2 });

        const lightness = (n: number) => {
            const c = new THREE.Color();

            cargo.getColorAt(n, c);

            return c.getHSL({ h: 0, s: 0, l: 0 }).l;
        };
        const onFloor = occupied.findIndex((s) => s.level === 2);
        const offFloor = occupied.findIndex((s) => s.level === 1);

        expect(lightness(onFloor)).toBeGreaterThan(lightness(offFloor));
    });

    it('gauges only the opened pallets that report their boxes, in the right colour band', () => {
        const { warehouse, geometry } = world(2, 6, 3);
        const builder = new GaugeBuilder(geometry);
        const group = builder.build(warehouse.slots);
        const fill = group.children[1] as THREE.InstancedMesh;
        const expected = warehouse.slots.filter(
            (s) =>
                s.state === 'opened' &&
                s.pallet?.remainingBoxes != null &&
                s.pallet.productBoxesCount,
        );

        expect(fill.count).toBe(expected.length);

        builder.applyFilter({
            match: buildMatchIndex(warehouse.slots, {
                terms: [],
                state: 'all',
                expiring: false,
                productIds: [],
            }),
            floor: null,
        });

        const colour = new THREE.Color();

        fill.getColorAt(0, colour);
        const fraction =
            expected[0].pallet!.remainingBoxes! /
            expected[0].pallet!.productBoxesCount!;
        const want =
            fraction > 0.5 ? 0x34d399 : fraction > 0.25 ? 0xfbbf24 : 0xfb7185;

        expect(colour.getHex()).toBe(want);
    });

    it('builds no gauge when nothing is opened', () => {
        const { geometry } = world(2, 2, 1);
        const group = new GaugeBuilder(geometry).build([]);

        expect((group.children[1] as THREE.InstancedMesh).count).toBe(0);
    });

    it('lays out the racks: two uprights per bay line and two beams per level, per row', () => {
        const { geometry } = world(4, 10, 3);
        const group = new RackBuilder(geometry, DEFAULT_PALETTE).build();
        const [uprights, beams, backs] =
            group.children as THREE.InstancedMesh[];

        expect(uprights.count).toBe(4 * (10 + 1) * 2);
        expect(beams.count).toBe(4 * 3 * 2);
        expect(backs.count).toBe(4);
    });

    it('closes each rack on the side away from its aisle, so the shelves face the aisle', () => {
        const { geometry } = world(4, 10, 3);
        const group = new RackBuilder(geometry, DEFAULT_PALETTE).build();
        const backs = group.children[2] as THREE.InstancedMesh;
        const matrix = new THREE.Matrix4();
        const position = new THREE.Vector3();

        for (let i = 0; i < 4; i++) {
            backs.getMatrixAt(i, matrix);
            position.setFromMatrixPosition(matrix);

            const aisle = geometry.aisleCx(i >> 1);

            expect(Math.abs(position.x - aisle)).toBeGreaterThan(
                Math.abs(geometry.rowX(i) - aisle),
            );
        }

        // row A (on the walker's right, -x) is backed on its -x side
        backs.getMatrixAt(0, matrix);
        expect(
            new THREE.Vector3().setFromMatrixPosition(matrix).x,
        ).toBeLessThan(geometry.rowX(0));
    });

    it('relabels the aisle signs in the current language', () => {
        const { warehouse, geometry, atlas } = world(4, 4, 2);
        let label = 'Aisle';
        const signs = new SignBuilder(
            geometry,
            warehouse.rows,
            atlas,
            (names) => `${label} ${names}`,
        );
        const group = signs.build();

        // 2 aisles x (sign + pole) + 4 row plates
        expect(group.children).toHaveLength(2 * 2 + 4);

        const sign = group.children[0] as THREE.Mesh;
        const before = (sign.material as THREE.MeshBasicMaterial).map;

        label = 'الممر';
        signs.relabel();

        expect((sign.material as THREE.MeshBasicMaterial).map).not.toBe(before);
    });

    it('shows the ceiling only on request', () => {
        const { geometry } = world(2, 4, 2);
        const builder = new CeilingBuilder(geometry, DEFAULT_PALETTE);
        const group = builder.build();

        expect(group.visible).toBe(false);
        builder.setVisible(true);
        expect(group.visible).toBe(true);
    });

    it('moves the hover frame and target marker, and pulses the target for a few seconds', () => {
        const { geometry, atlas } = world(2, 4, 2);
        const markers = new MarkerBuilder(
            DEFAULT_PALETTE,
            atlas,
            geometry.layout.rackDepth,
        );
        const group = markers.build();

        expect(group.children.every((c) => !c.visible)).toBe(true);

        markers.setHover({ x: 1, y: 2, z: 3 });
        expect(group.children[0].visible).toBe(true);
        expect(group.children[0].position.z).toBe(3);

        markers.setHover(null);
        expect(group.children[0].visible).toBe(false);

        markers.setTarget(
            { center: { x: 1, y: 2, z: 3 }, code: 'A1- 1', side: 1 },
            1000,
        );
        expect(markers.hasTarget).toBe(true);
        expect(markers.pulse(2000)).toBe(true);
        expect(markers.pulse(1000 + 6000)).toBe(true); // one frame after it ends, to settle
        expect(markers.pulse(1000 + 6100)).toBe(false);

        markers.setTarget(null, 0);
        expect(markers.hasTarget).toBe(false);
        expect(markers.pulse(0)).toBe(false);
    });

    it('frees every geometry, material and texture it made', () => {
        const { warehouse, geometry, atlas } = world(2, 4, 2);
        const group = new THREE.Group();

        group.add(
            new FloorBuilder(geometry, DEFAULT_PALETTE, atlas).build(),
            new PalletBuilder(geometry, DEFAULT_PALETTE, atlas).build(
                warehouse.slots,
            ),
        );

        const disposed: string[] = [];

        group.traverse((node) => {
            const mesh = node as THREE.Mesh;

            mesh.geometry?.addEventListener('dispose', () =>
                disposed.push('geometry'),
            );

            const material = mesh.material as
                THREE.MeshBasicMaterial | undefined;

            material?.addEventListener('dispose', () =>
                disposed.push('material'),
            );
            material?.map?.addEventListener('dispose', () =>
                disposed.push('texture'),
            );
        });
        disposeTree(group);

        expect(disposed).toContain('geometry');
        expect(disposed).toContain('material');
        expect(disposed).toContain('texture');
    });
});

describe('SlotPicker', () => {
    it('finds the slot a ray hits, and none where nothing is', () => {
        const { warehouse, geometry } = world(2, 4, 2);
        const picker = new SlotPicker(geometry, warehouse.slots);
        const slot = warehouse.slots.find(
            (s) => s.rowIdx === 0 && s.col === 2 && s.level === 1,
        )!;
        const center = geometry.slotCenter(slot.rowIdx, slot.col, slot.level);
        const camera = new THREE.PerspectiveCamera(60, 1, 0.1, 100);

        // stand in the aisle, look straight at the slot
        camera.position.set(geometry.aisleCx(0), center.y, center.z);
        camera.lookAt(center.x, center.y, center.z);
        camera.updateMatrixWorld();
        picker.mesh.updateMatrixWorld();

        const rect = { left: 0, top: 0, width: 100, height: 100 };

        expect(picker.pickCentre(20, camera, rect)?.id).toBe(slot.id);
        expect(picker.pickCentre(1, camera, rect)).toBeNull(); // out of reach

        camera.lookAt(geometry.aisleCx(0), center.y, center.z + 50); // along the aisle
        camera.updateMatrixWorld();
        expect(picker.pickCentre(20, camera, rect)).toBeNull();
    });

    it('does not pick on a canvas with no size', () => {
        const { warehouse, geometry } = world(2, 4, 2);
        const picker = new SlotPicker(geometry, warehouse.slots);

        expect(
            picker.pickAt(5, 5, 20, new THREE.PerspectiveCamera(), {
                left: 0,
                top: 0,
                width: 0,
                height: 0,
            }),
        ).toBeNull();
    });
});

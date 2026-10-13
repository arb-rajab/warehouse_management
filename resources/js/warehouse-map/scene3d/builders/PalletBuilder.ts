import * as THREE from 'three';
import { productHue } from '../../domain/expiry';
import type { WarehouseGeometry } from '../../domain/geometry';
import { isMatch } from '../../domain/MatchIndex';
import type { MatchIndex, Slot } from '../../domain/types';
import type { ScenePalette } from '../Palette';
import type { LabelAtlas } from './LabelAtlas';

const DIM = new THREE.Color(0x1a2438);
const WHITE = new THREE.Color(0xffffff);
const GREY = new THREE.Color(0x2a3550);

export interface FilterView {
    match: MatchIndex;
    floor: number | null;
}

/** A slot is greyed out when the filter excludes it or it is on another floor. */
export function isFaded(slot: Slot, view: FilterView): boolean {
    return (
        (view.match.filtering && !isMatch(view.match, slot)) ||
        (view.floor !== null && slot.level !== view.floor)
    );
}

interface LabelRef {
    mesh: THREE.InstancedMesh;
    index: number;
}

/**
 * Pallets, the carton stack on each, the markers of empty slots, the product
 * label on the aisle-facing side and the red cross of out-of-service slots.
 * One InstancedMesh per kind (one draw call each); recolouring for the filter
 * rewrites instance colours, it never rebuilds.
 */
export class PalletBuilder {
    private occupied: Slot[] = [];
    private cargo!: THREE.InstancedMesh;
    private labelRefs = new Map<number, LabelRef>();
    private labelMeshes: THREE.InstancedMesh[] = [];

    constructor(
        private readonly geometry: WarehouseGeometry,
        private readonly palette: ScenePalette,
        private readonly atlas: LabelAtlas,
    ) {}

    build(slots: readonly Slot[]): THREE.Group {
        const group = new THREE.Group();
        const geo = this.geometry;
        const { layout } = geo;
        const occupied = slots.filter((s) => s.state !== 'empty');
        const empty = slots.filter((s) => s.state === 'empty');
        const palletDepth = layout.rackDepth * 0.94;
        const palletWidth = layout.rackWidth * 0.9;
        const cargoDepth = layout.rackDepth * 0.86;
        const cargoWidth = layout.rackWidth * 0.84;
        const matrix = new THREE.Matrix4();

        this.occupied = occupied;

        const pallets = new THREE.InstancedMesh(
            new THREE.BoxGeometry(palletDepth, layout.palletH, palletWidth),
            new THREE.MeshLambertMaterial({ color: this.palette.wood }),
            Math.max(occupied.length, 1),
        );

        this.cargo = new THREE.InstancedMesh(
            new THREE.BoxGeometry(cargoDepth, 1, cargoWidth),
            new THREE.MeshLambertMaterial({ color: 0xffffff }),
            Math.max(occupied.length, 1),
        );

        const empties = new THREE.InstancedMesh(
            new THREE.BoxGeometry(palletDepth, 0.02, palletWidth),
            new THREE.MeshBasicMaterial({
                color: this.palette.empty,
                transparent: true,
                opacity: 0.55,
            }),
            Math.max(empty.length, 1),
        );

        occupied.forEach((slot, n) => {
            const x = geo.rowX(slot.rowIdx);
            const z = geo.zOf(slot.col);
            const base = geo.beamY(slot.level) + 0.05;

            // every carton stack is the same size: what is left on an opened pallet is a gauge, not a smaller box
            pallets.setMatrixAt(
                n,
                matrix.makeTranslation(x, base + layout.palletH / 2, z),
            );
            this.cargo.setMatrixAt(
                n,
                matrix.compose(
                    new THREE.Vector3(
                        x,
                        base + layout.palletH + layout.cargoH / 2,
                        z,
                    ),
                    new THREE.Quaternion(),
                    new THREE.Vector3(1, layout.cargoH, 1),
                ),
            );
        });

        empty.forEach((slot, n) => {
            empties.setMatrixAt(
                n,
                matrix.makeTranslation(
                    geo.rowX(slot.rowIdx),
                    geo.beamY(slot.level) + 0.06,
                    geo.zOf(slot.col),
                ),
            );
        });
        empties.count = empty.length;

        group.add(
            pallets,
            this.cargo,
            empties,
            ...this.labels(occupied, cargoDepth),
            this.inactiveMarks(slots),
        );
        group.name = 'pallets';

        return group;
    }

    /** One instanced plane per product, so each keeps its own texture. */
    private labels(
        occupied: Slot[],
        cargoDepth: number,
    ): THREE.InstancedMesh[] {
        const geo = this.geometry;
        const { layout } = geo;
        const byProduct = new Map<number, Slot[]>();
        const matrix = new THREE.Matrix4();

        occupied.forEach((slot) => {
            const list = byProduct.get(slot.pallet!.productId) ?? [];

            list.push(slot);
            byProduct.set(slot.pallet!.productId, list);
        });

        this.labelMeshes = [];

        byProduct.forEach((list, productId) => {
            const first = list[0].pallet!;
            const mesh = new THREE.InstancedMesh(
                new THREE.PlaneGeometry(0.8, 0.34),
                new THREE.MeshBasicMaterial({
                    map: this.atlas.product({
                        id: productId,
                        name: first.productName,
                        arName: first.productArName,
                        boxes: first.productBoxesCount,
                    }),
                }),
                list.length,
            );

            list.forEach((slot, n) => {
                const toward = -geo.rowSide(slot.rowIdx);
                const base = geo.beamY(slot.level) + 0.05 + layout.palletH;

                matrix
                    .makeRotationY(toward < 0 ? -Math.PI / 2 : Math.PI / 2)
                    .setPosition(
                        geo.rowX(slot.rowIdx) +
                            toward * (cargoDepth / 2 + 0.006),
                        base + Math.min(layout.cargoH * 0.5, 0.3),
                        geo.zOf(slot.col),
                    );
                mesh.setMatrixAt(n, matrix);
                this.labelRefs.set(slot.id, { mesh, index: n });
            });
            this.labelMeshes.push(mesh);
        });

        return this.labelMeshes;
    }

    private inactiveMarks(slots: readonly Slot[]): THREE.InstancedMesh {
        const geo = this.geometry;
        const bad = slots.filter((s) => !s.active);
        const mesh = new THREE.InstancedMesh(
            new THREE.PlaneGeometry(0.95, 0.95),
            new THREE.MeshBasicMaterial({
                map: this.atlas.inactive(),
                transparent: true,
                depthWrite: false,
            }),
            Math.max(bad.length, 1),
        );
        const matrix = new THREE.Matrix4();

        bad.forEach((slot, n) => {
            const toward = -geo.rowSide(slot.rowIdx);

            matrix
                .makeRotationY(toward < 0 ? -Math.PI / 2 : Math.PI / 2)
                .setPosition(
                    geo.rowX(slot.rowIdx) +
                        toward * (geo.layout.rackDepth / 2 + 0.02),
                    geo.beamY(slot.level) + 0.55,
                    geo.zOf(slot.col),
                );
            mesh.setMatrixAt(n, matrix);
        });
        mesh.count = bad.length;

        return mesh;
    }

    /** Recolours the carton stacks and labels: state colour, a slight per-product tint, greyed out when filtered away. */
    applyFilter(view: FilterView): void {
        const color = new THREE.Color();
        const hsl = { h: 0, s: 0, l: 0 };

        this.occupied.forEach((slot, n) => {
            const tint = ((productHue(slot.pallet!.productId) % 11) - 5) / 100;
            const faded = isFaded(slot, view);

            color.set(
                slot.state === 'opened'
                    ? this.palette.opened
                    : this.palette.full,
            );
            color.getHSL(hsl);
            color.setHSL(
                hsl.h,
                hsl.s,
                Math.min(0.7, Math.max(0.3, hsl.l + tint)),
            );

            if (faded) {
                color.lerp(DIM, 0.86);
            }

            this.cargo.setColorAt(n, color);

            const ref = this.labelRefs.get(slot.id);

            ref?.mesh.setColorAt(ref.index, faded ? GREY : WHITE);
        });

        if (this.cargo.instanceColor) {
            this.cargo.instanceColor.needsUpdate = true;
        }

        for (const mesh of this.labelMeshes) {
            if (mesh.instanceColor) {
                mesh.instanceColor.needsUpdate = true;
            }
        }
    }
}

import * as THREE from 'three';
import { gaugeBand } from '../../domain/expiry';
import type { WarehouseGeometry } from '../../domain/geometry';
import type { Slot } from '../../domain/types';
import { isFaded } from './PalletBuilder';
import type { FilterView } from './PalletBuilder';

const BAND_COLOR = { high: 0x34d399, mid: 0xfbbf24, low: 0xfb7185 } as const;
const FADED = 0x2a3550;
const WIDTH = 0.8;
const HEIGHT = 0.075;

/**
 * What is left on an opened pallet: a gauge along the top of its face (full
 * width = a full pallet), coloured green above half, amber from a quarter and
 * red below. The carton stack itself stays full size: a smaller box read as a
 * different product.
 */
export class GaugeBuilder {
    private gauged: Array<{ slot: Slot; fraction: number }> = [];
    private fill!: THREE.InstancedMesh;

    constructor(private readonly geometry: WarehouseGeometry) {}

    build(slots: readonly Slot[]): THREE.Group {
        const geo = this.geometry;
        const { layout } = geo;
        const cargoDepth = layout.rackDepth * 0.86;
        const group = new THREE.Group();

        this.gauged = slots
            .filter(
                (s) =>
                    s.state === 'opened' &&
                    s.pallet?.remainingBoxes != null &&
                    !!s.pallet.productBoxesCount,
            )
            .map((slot) => ({
                slot,
                fraction: Math.max(
                    0.05,
                    Math.min(
                        1,
                        slot.pallet!.remainingBoxes! /
                            slot.pallet!.productBoxesCount!,
                    ),
                ),
            }));

        const track = new THREE.InstancedMesh(
            new THREE.PlaneGeometry(1, 1),
            new THREE.MeshBasicMaterial({
                color: 0x0b1120,
                transparent: true,
                opacity: 0.8,
            }),
            Math.max(this.gauged.length, 1),
        );

        this.fill = new THREE.InstancedMesh(
            new THREE.PlaneGeometry(1, 1),
            new THREE.MeshBasicMaterial({ color: 0xffffff }),
            Math.max(this.gauged.length, 1),
        );

        const quaternion = new THREE.Quaternion();
        const yAxis = new THREE.Vector3(0, 1, 0);
        const matrix = new THREE.Matrix4();

        this.gauged.forEach(({ slot, fraction }, n) => {
            const toward = -geo.rowSide(slot.rowIdx);
            // the face's own left-to-right runs along +z on one side of an aisle and -z on the other
            const along = toward < 0 ? 1 : -1;
            const y = geo.beamY(slot.level) + 0.05 + layout.palletH + 0.585;
            const x = geo.rowX(slot.rowIdx) + toward * (cargoDepth / 2 + 0.007);
            const z = geo.zOf(slot.col);

            quaternion.setFromAxisAngle(
                yAxis,
                toward < 0 ? -Math.PI / 2 : Math.PI / 2,
            );
            matrix.compose(
                new THREE.Vector3(x, y, z),
                quaternion,
                new THREE.Vector3(WIDTH + 0.03, HEIGHT + 0.03, 1),
            );
            track.setMatrixAt(n, matrix);
            matrix.compose(
                new THREE.Vector3(
                    x + toward * 0.004,
                    y,
                    z + along * (-WIDTH / 2 + (WIDTH * fraction) / 2),
                ),
                quaternion,
                new THREE.Vector3(WIDTH * fraction, HEIGHT, 1),
            );
            this.fill.setMatrixAt(n, matrix);
        });

        track.count = this.fill.count = this.gauged.length;
        group.add(track, this.fill);
        group.name = 'gauges';

        return group;
    }

    applyFilter(view: FilterView): void {
        const color = new THREE.Color();

        this.gauged.forEach(({ slot, fraction }, n) => {
            color.set(
                isFaded(slot, view) ? FADED : BAND_COLOR[gaugeBand(fraction)],
            );
            this.fill.setColorAt(n, color);
        });

        if (this.gauged.length && this.fill.instanceColor) {
            this.fill.instanceColor.needsUpdate = true;
        }
    }
}

import * as THREE from 'three';
import type { WarehouseGeometry } from '../../domain/geometry';
import type { Slot } from '../../domain/types';

/**
 * Finds the slot under a screen point or the crosshair. One invisible pick
 * volume per slot (instanced) so empty slots can be clicked too.
 */
export class SlotPicker {
    readonly mesh: THREE.InstancedMesh;
    private readonly raycaster = new THREE.Raycaster();
    private readonly ndc = new THREE.Vector2();

    constructor(
        geometry: WarehouseGeometry,
        private readonly slots: readonly Slot[],
    ) {
        this.mesh = new THREE.InstancedMesh(
            new THREE.BoxGeometry(geometry.layout.rackDepth, 1, 1.5),
            new THREE.MeshBasicMaterial({ visible: false }),
            Math.max(slots.length, 1),
        );

        const matrix = new THREE.Matrix4();

        slots.forEach((slot, n) => {
            this.mesh.setMatrixAt(
                n,
                matrix.makeTranslation(
                    geometry.rowX(slot.rowIdx),
                    geometry.beamY(slot.level) + 0.55,
                    geometry.zOf(slot.col),
                ),
            );
        });
        this.mesh.count = slots.length;
        this.mesh.name = 'pick';
    }

    /** The slot under a client-space point, within `maxDistance` metres. */
    pickAt(
        clientX: number,
        clientY: number,
        maxDistance: number,
        camera: THREE.Camera,
        rect: { left: number; top: number; width: number; height: number },
    ): Slot | null {
        if (!rect.width || !rect.height) {
            return null;
        }

        this.ndc.set(
            ((clientX - rect.left) / rect.width) * 2 - 1,
            -((clientY - rect.top) / rect.height) * 2 + 1,
        );
        this.raycaster.setFromCamera(this.ndc, camera);
        this.raycaster.far = maxDistance || 600;

        const hit = this.raycaster.intersectObject(this.mesh)[0];

        return hit?.instanceId === undefined
            ? null
            : (this.slots[hit.instanceId] ?? null);
    }

    /** The slot in the middle of the view. */
    pickCentre(
        maxDistance: number,
        camera: THREE.Camera,
        rect: { left: number; top: number; width: number; height: number },
    ): Slot | null {
        return this.pickAt(
            rect.left + rect.width / 2,
            rect.top + rect.height / 2,
            maxDistance,
            camera,
            rect,
        );
    }
}

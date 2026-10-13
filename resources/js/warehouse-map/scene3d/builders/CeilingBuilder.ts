import * as THREE from 'three';
import type { WarehouseGeometry } from '../../domain/geometry';
import type { ScenePalette } from '../Palette';

/** The ceiling and its strip lights: only drawn from inside, an overview from above would see a lid. */
export class CeilingBuilder {
    private group = new THREE.Group();

    constructor(
        private readonly geometry: WarehouseGeometry,
        private readonly palette: ScenePalette,
    ) {}

    build(): THREE.Group {
        const geo = this.geometry;
        const y = geo.height + geo.layout.ceilClear;
        const lid = new THREE.Mesh(
            new THREE.PlaneGeometry(geo.bounds.w + 20, geo.bounds.d + 20),
            new THREE.MeshBasicMaterial({ color: this.palette.ceil }),
        );

        lid.rotation.x = Math.PI / 2;
        lid.position.set(geo.bounds.cx, y, geo.bounds.cz);

        const length = geo.zMax - geo.zMin;
        const step = geo.layout.colSpacing * 4;
        const lights = new THREE.InstancedMesh(
            new THREE.BoxGeometry(0.5, 0.06, 2.4),
            new THREE.MeshBasicMaterial({ color: this.palette.ceilLight }),
            Math.max(geo.aisles * (Math.ceil(length / step) + 2), 1),
        );
        const matrix = new THREE.Matrix4();
        let index = 0;

        for (let a = 0; a < geo.aisles; a++) {
            for (let z = geo.zMin + 1.5; z < geo.zMax; z += step) {
                lights.setMatrixAt(
                    index++,
                    matrix.makeTranslation(geo.aisleCx(a), y - 0.05, z),
                );
            }
        }

        lights.count = index;
        this.group.add(lid, lights);
        this.group.visible = false;
        this.group.name = 'ceiling';

        return this.group;
    }

    setVisible(visible: boolean): void {
        this.group.visible = visible;
    }
}

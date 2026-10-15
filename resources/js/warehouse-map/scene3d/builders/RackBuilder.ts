import * as THREE from 'three';
import type { WarehouseGeometry } from '../../domain/geometry';
import type { ScenePalette } from '../Palette';

/** The steel: uprights, the orange beams of every level, and the panel closing each rack on its outer side. */
export class RackBuilder {
    constructor(
        private readonly geometry: WarehouseGeometry,
        private readonly palette: ScenePalette,
    ) {}

    build(): THREE.Group {
        const geo = this.geometry;
        const { rowCount, cols, levels, layout } = geo;
        const group = new THREE.Group();
        const matrix = new THREE.Matrix4();
        const scale = new THREE.Vector3();
        const quaternion = new THREE.Quaternion();
        const position = new THREE.Vector3();
        const put = (
            mesh: THREE.InstancedMesh,
            index: number,
            x: number,
            y: number,
            z: number,
            sx: number,
            sy: number,
            sz: number,
        ) => {
            matrix.compose(
                position.set(x, y, z),
                quaternion,
                scale.set(sx, sy, sz),
            );
            mesh.setMatrixAt(index, matrix);
        };

        const uprights = new THREE.InstancedMesh(
            new THREE.BoxGeometry(1, 1, 1),
            new THREE.MeshLambertMaterial({ color: this.palette.upright }),
            Math.max(rowCount * (cols + 1) * 2, 1),
        );
        const beams = new THREE.InstancedMesh(
            new THREE.BoxGeometry(1, 1, 1),
            new THREE.MeshLambertMaterial({ color: this.palette.beam }),
            Math.max(rowCount * levels * 2, 1),
        );
        const backs = new THREE.InstancedMesh(
            new THREE.BoxGeometry(1, 1, 1),
            new THREE.MeshLambertMaterial({ color: this.palette.back }),
            Math.max(rowCount, 1),
        );
        const length = geo.zMax - geo.zMin;
        const midZ = (geo.zMin + geo.zMax) / 2;
        let u = 0;
        let b = 0;

        for (let i = 0; i < rowCount; i++) {
            const x = geo.rowX(i);

            for (let k = 0; k <= cols; k++) {
                for (const dx of [-1, 1]) {
                    put(
                        uprights,
                        u++,
                        x + (dx * layout.rackDepth) / 2,
                        geo.height / 2,
                        geo.zOf(1) -
                            layout.colSpacing / 2 +
                            k * layout.colSpacing,
                        layout.upright,
                        geo.height,
                        layout.upright,
                    );
                }
            }

            for (let l = 1; l <= levels; l++) {
                for (const dx of [-1, 1]) {
                    put(
                        beams,
                        b++,
                        x + (dx * layout.rackDepth) / 2,
                        geo.beamY(l),
                        midZ,
                        0.05,
                        layout.beamT,
                        length,
                    );
                }
            }

            put(
                backs,
                i,
                x + geo.rowSide(i) * (layout.rackDepth / 2 + 0.03),
                geo.height / 2,
                midZ,
                0.03,
                geo.height,
                length,
            );
        }

        uprights.count = u;
        beams.count = b;
        group.add(uprights, beams, backs);
        group.name = 'racks';

        return group;
    }
}

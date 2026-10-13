import * as THREE from 'three';
import type { WarehouseGeometry } from '../../domain/geometry';
import type { ScenePalette } from '../Palette';
import type { LabelAtlas } from './LabelAtlas';

/** The floor, the lanes, the centre line, the rack footprints and the bay numbers. */
export class FloorBuilder {
    constructor(
        private readonly geometry: WarehouseGeometry,
        private readonly palette: ScenePalette,
        private readonly atlas: LabelAtlas,
    ) {}

    build(): THREE.Group {
        const group = new THREE.Group();

        group.name = 'floor';
        group.add(...this.groundPlanes(), this.centreLine(), this.bayNumbers());

        return group;
    }

    private flat(
        group: THREE.Object3D[],
        width: number,
        depth: number,
        color: number,
        x: number,
        z: number,
        y: number,
    ): void {
        const mesh = new THREE.Mesh(
            new THREE.PlaneGeometry(width, depth),
            new THREE.MeshBasicMaterial({ color }),
        );

        mesh.rotation.x = -Math.PI / 2;
        mesh.position.set(x, y, z);
        group.push(mesh);
    }

    private groundPlanes(): THREE.Object3D[] {
        const { geometry: geo, palette: C } = this;
        const out: THREE.Object3D[] = [];
        const floor = new THREE.Mesh(
            new THREE.PlaneGeometry(640, 640),
            new THREE.MeshBasicMaterial({
                map: this.atlas.grid('#131D31', C.grid),
            }),
        );

        floor.rotation.x = -Math.PI / 2;
        floor.position.set(geo.bounds.cx, 0, geo.bounds.cz);
        out.push(floor);

        const glow = new THREE.Mesh(
            new THREE.PlaneGeometry(geo.bounds.w * 1.8, geo.bounds.d * 1.8),
            new THREE.MeshBasicMaterial({
                map: this.atlas.glow(),
                transparent: true,
                opacity: 0.55,
                depthWrite: false,
            }),
        );

        glow.rotation.x = -Math.PI / 2;
        glow.position.set(geo.bounds.cx, 0.01, geo.bounds.cz);
        out.push(glow);

        const length = geo.zMax - geo.zMin;
        const { aisleWidth, apron, rackDepth } = geo.layout;

        for (let a = 0; a < geo.aisles; a++) {
            // the lane starts under the entrance camera
            this.flat(
                out,
                aisleWidth,
                length + apron + 5,
                a % 2 ? C.laneAlt : C.lane,
                geo.aisleCx(a),
                (geo.zMin - apron + geo.zMax + 5) / 2,
                0.02,
            );
        }

        for (let i = 0; i < geo.rowCount; i++) {
            this.flat(
                out,
                rackDepth + 0.35,
                length + 0.4,
                C.foot,
                geo.rowX(i),
                (geo.zMin + geo.zMax) / 2,
                0.03,
            );
        }

        return out;
    }

    private centreLine(): THREE.InstancedMesh {
        const geo = this.geometry;
        const length = geo.zMax - geo.zMin;
        const capacity = geo.aisles * (Math.ceil((length + 14) / 1.9) + 2);
        const dashes = new THREE.InstancedMesh(
            new THREE.PlaneGeometry(0.14, 0.9),
            new THREE.MeshBasicMaterial({ color: this.palette.aisleLine }),
            Math.max(capacity, 1),
        );
        const matrix = new THREE.Matrix4();
        let index = 0;

        for (let a = 0; a < geo.aisles; a++) {
            for (let z = geo.zMin - 6; z < geo.zMax + 3; z += 1.9) {
                matrix
                    .makeRotationX(-Math.PI / 2)
                    .setPosition(geo.aisleCx(a), 0.04, z);
                dashes.setMatrixAt(index++, matrix);
            }
        }

        dashes.count = index;

        return dashes;
    }

    /** One merged mesh and one atlas texture for every bay number: a single draw call. */
    private bayNumbers(): THREE.Mesh {
        const geo = this.geometry;
        const { texture, perRow, rows } = this.atlas.bayNumbers(geo.cols);
        const quads = geo.rowCount * geo.cols;
        const positions = new Float32Array(quads * 12);
        const uvs = new Float32Array(quads * 8);
        const indices = new Uint32Array(quads * 6);
        const { rackDepth } = geo.layout;
        const w = 0.9;
        const h = 0.45;
        const y = 0.06;
        let q = 0;

        for (let i = 0; i < geo.rowCount; i++) {
            for (let c = 1; c <= geo.cols; c++) {
                const cx =
                    geo.rowX(i) - geo.rowSide(i) * (rackDepth / 2 + 0.55);
                const cz = geo.zOf(c);
                const col = (c - 1) % perRow;
                const row = Math.floor((c - 1) / perRow);
                const u0 = col / perRow;
                const u1 = (col + 1) / perRow;
                const vBottom = 1 - (row + 1) / rows;
                const vTop = 1 - row / rows;

                // the text reads left to right for someone facing +z (their right is -x) and runs up the aisle
                positions.set(
                    [
                        cx + w / 2,
                        y,
                        cz - h / 2,
                        cx - w / 2,
                        y,
                        cz - h / 2,
                        cx - w / 2,
                        y,
                        cz + h / 2,
                        cx + w / 2,
                        y,
                        cz + h / 2,
                    ],
                    q * 12,
                );
                uvs.set([u0, vBottom, u1, vBottom, u1, vTop, u0, vTop], q * 8);
                indices.set(
                    [q * 4, q * 4 + 1, q * 4 + 2, q * 4, q * 4 + 2, q * 4 + 3],
                    q * 6,
                );
                q++;
            }
        }

        const buffer = new THREE.BufferGeometry();

        buffer.setAttribute(
            'position',
            new THREE.BufferAttribute(positions, 3),
        );
        buffer.setAttribute('uv', new THREE.BufferAttribute(uvs, 2));
        buffer.setIndex(new THREE.BufferAttribute(indices, 1));

        return new THREE.Mesh(
            buffer,
            new THREE.MeshBasicMaterial({
                map: texture,
                transparent: true,
                depthWrite: false,
            }),
        );
    }
}

import * as THREE from 'three';
import type { WarehouseGeometry } from '../../domain/geometry';
import type { WarehouseRow } from '../../domain/types';
import type { LabelAtlas } from './LabelAtlas';

interface AisleSign {
    mesh: THREE.Mesh;
    names: string;
}

/** The hanging aisle signs with their poles and the row plate on the entrance end of every rack. */
export class SignBuilder {
    private signs: AisleSign[] = [];

    constructor(
        private readonly geometry: WarehouseGeometry,
        private readonly rows: readonly WarehouseRow[],
        private readonly atlas: LabelAtlas,
        private readonly aisleLabel: (names: string) => string,
    ) {}

    build(): THREE.Group {
        const geo = this.geometry;
        const group = new THREE.Group();

        group.name = 'signs';
        this.signs = [];

        for (let a = 0; a < geo.aisles; a++) {
            const names = geo
                .aisleRows(a)
                .map((i) => this.rows[i]?.letter)
                .filter(Boolean)
                .join(' - ');
            const sign = this.plane(
                this.aisleLabel(names),
                3.4,
                0.78,
                '#1E3A8A',
            );

            sign.position.set(geo.aisleCx(a), geo.height + 0.9, geo.zMin - 1.2);
            sign.rotation.y = Math.PI;

            const pole = new THREE.Mesh(
                new THREE.BoxGeometry(0.05, 1.1, 0.05),
                new THREE.MeshBasicMaterial({ color: 0x64748b }),
            );

            pole.position.set(
                geo.aisleCx(a),
                geo.height + 0.35,
                geo.zMin - 1.2,
            );
            group.add(sign, pole);
            this.signs.push({ mesh: sign, names });
        }

        this.rows.forEach((row, i) => {
            const plate = this.plane(row.letter, 0.55, 0.42, '#2563EB');

            plate.position.set(geo.rowX(i), geo.height - 0.45, geo.zMin - 0.05);
            plate.rotation.y = Math.PI;
            group.add(plate);
        });

        return group;
    }

    /** Redraws the aisle signs in the current language. */
    relabel(): void {
        for (const { mesh, names } of this.signs) {
            const material = mesh.material as THREE.MeshBasicMaterial;

            material.map?.dispose();
            material.map = this.atlas.text(
                this.aisleLabel(names),
                435,
                100,
                '#1E3A8A',
                '#fff',
                50,
            );
            material.needsUpdate = true;
        }
    }

    private plane(
        text: string,
        w: number,
        h: number,
        background: string,
    ): THREE.Mesh {
        const texture = this.atlas.text(
            text,
            Math.round(w * 128),
            Math.round(h * 128),
            background,
            '#fff',
            Math.round(h * 128 * 0.56),
        );

        return new THREE.Mesh(
            new THREE.PlaneGeometry(w, h),
            new THREE.MeshBasicMaterial({ map: texture, transparent: true }),
        );
    }
}

import * as THREE from 'three';
import type { Point3 } from '../../domain/geometry';
import type { ScenePalette } from '../Palette';
import type { LabelAtlas } from './LabelAtlas';

const PULSE_MS = 5600;

/** The white hover frame and the yellow target frame, glow and code pin around a slot. */
export class MarkerBuilder {
    private readonly hoverFrame: THREE.LineSegments;
    private readonly targetFrame = new THREE.Group();
    private readonly targetGlow: THREE.Mesh;
    private readonly pin: THREE.Mesh;
    private pulseUntil = 0;
    private lastLive = false;

    constructor(
        private readonly palette: ScenePalette,
        private readonly atlas: LabelAtlas,
        private readonly rackDepth: number,
    ) {
        const edges = new THREE.EdgesGeometry(new THREE.BoxGeometry(1, 1, 1));

        this.hoverFrame = new THREE.LineSegments(
            edges,
            new THREE.LineBasicMaterial({ color: 0xffffff }),
        );
        this.hoverFrame.visible = false;

        const frame = new THREE.LineSegments(
            edges.clone(),
            new THREE.LineBasicMaterial({ color: palette.target }),
        );

        this.targetGlow = new THREE.Mesh(
            new THREE.BoxGeometry(1, 1, 1),
            new THREE.MeshBasicMaterial({
                color: palette.target,
                transparent: true,
                opacity: 0.2,
                depthWrite: false,
            }),
        );
        this.targetFrame.add(frame, this.targetGlow);
        this.targetFrame.visible = false;

        this.pin = new THREE.Mesh(
            new THREE.PlaneGeometry(1.5, 0.42),
            new THREE.MeshBasicMaterial({ transparent: true }),
        );
        this.pin.visible = false;
    }

    build(): THREE.Group {
        const group = new THREE.Group();

        group.name = 'markers';
        group.add(this.hoverFrame, this.targetFrame, this.pin);

        return group;
    }

    setHover(center: Point3 | null): void {
        if (!center) {
            this.hoverFrame.visible = false;

            return;
        }

        this.hoverFrame.position.set(center.x, center.y, center.z);
        this.hoverFrame.scale.set(this.rackDepth + 0.06, 1, 1.56);
        this.hoverFrame.visible = true;
    }

    /** Puts the yellow frame and the code pin on a slot; null removes them. */
    setTarget(
        target: { center: Point3; code: string; side: 1 | -1 } | null,
        now: number,
    ): void {
        if (!target) {
            this.targetFrame.visible = false;
            this.pin.visible = false;

            return;
        }

        const { center: c, code, side } = target;

        this.targetFrame.position.set(c.x, c.y, c.z);
        this.targetFrame.scale.set(this.rackDepth + 0.1, 1.02, 1.56);
        this.targetFrame.visible = true;

        const material = this.pin.material as THREE.MeshBasicMaterial;

        material.map?.dispose();
        material.map = this.atlas.text(code, 256, 72, '#FACC15', '#0F172A', 38);
        material.needsUpdate = true;
        this.pin.position.set(
            c.x - side * (this.rackDepth / 2 + 0.06),
            c.y + 0.78,
            c.z,
        );
        this.pin.rotation.y = side > 0 ? -Math.PI / 2 : Math.PI / 2;
        this.pin.visible = true;
        this.pulseUntil = now + PULSE_MS;
    }

    get hasTarget(): boolean {
        return this.targetFrame.visible;
    }

    /** Pulses the target glow for a few seconds. Returns true while it still needs frames. */
    pulse(now: number): boolean {
        if (!this.targetFrame.visible) {
            return false;
        }

        const live = now < this.pulseUntil;
        const beat = live
            ? 0.5 +
              0.5 *
                  Math.sin(
                      ((now - (this.pulseUntil - PULSE_MS)) / PULSE_MS) *
                          Math.PI *
                          12,
                  )
            : 1;

        (this.targetGlow.material as THREE.MeshBasicMaterial).opacity =
            0.12 + 0.24 * beat;

        const needsFrame = live || this.lastLive;

        this.lastLive = live;

        return needsFrame;
    }
}

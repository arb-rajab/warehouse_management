import * as THREE from 'three';
import type { UserSettings } from '../config';
import { THRESHOLDS } from '../config';
import type { FrameLoop } from '../core/FrameLoop';
import { View } from '../core/View';
import { WarehouseGeometry } from '../domain/geometry';
import type { Point3 } from '../domain/geometry';
import { LAYOUT } from '../domain/layout';
import type { MatchIndex, Slot, Warehouse } from '../domain/types';
import type { Intent, PointerCapture } from '../input/Intent';
import type {
    InputKindName,
    NavMode,
    RackRef,
    SceneState,
} from '../state/state';
import { CeilingBuilder } from './builders/CeilingBuilder';
import { FloorBuilder } from './builders/FloorBuilder';
import { GaugeBuilder } from './builders/GaugeBuilder';
import { LabelAtlas } from './builders/LabelAtlas';
import { MarkerBuilder } from './builders/MarkerBuilder';
import { PalletBuilder } from './builders/PalletBuilder';
import type { FilterView } from './builders/PalletBuilder';
import { RackBuilder } from './builders/RackBuilder';
import { SignBuilder } from './builders/SignBuilder';
import { CameraRig } from './camera/CameraRig';
import type { RigState } from './camera/CameraRig';
import { FreeWalkMode } from './camera/FreeWalkMode';
import { GuidedWalkMode } from './camera/GuidedWalkMode';
import { OverviewMode } from './camera/OverviewMode';
import type { Orbit } from './camera/OverviewMode';
import { forwardVector } from './camera/types';
import type { CameraContext, ModeId, Pose } from './camera/types';
import { WalkState } from './camera/WalkState';
import { WallCollider } from './collision/WallCollider';
import { disposeTree } from './dispose';
import { MinimapView } from './MinimapView';
import type { MinimapLabels } from './MinimapView';
import { readPalette } from './Palette';
import type { ScenePalette } from './Palette';
import { SlotPicker } from './picking/SlotPicker';
import { QualityGovernor } from './QualityGovernor';

/** What the 3D scene needs from the rest of the page. */
export interface SceneHost {
    settings(): UserSettings;
    inputKind(): InputKindName;
    patch(patch: Partial<SceneState>): void;
    openRack(ref: RackRef): void;
    setTarget(slot: Slot | null): void;
    /** The toolbars step aside while the map is being moved. */
    setChromeAway(away: boolean): void;
    toggleKeys(): void;
    buzz(ms: number): void;
    t(key: string, params?: Record<string, string | number>): string;
}

export interface SceneDeps {
    canvas: HTMLCanvasElement;
    warehouse: Warehouse;
    frames: FrameLoop;
    capture: PointerCapture;
    host: SceneHost;
    /** Reads the CSS tokens from here. */
    styleRoot: Element;
    createRenderer?: (canvas: HTMLCanvasElement) => THREE.WebGLRenderer;
    /** The clock the frame loop's timestamps come from (tests drive their own). */
    clock?: () => number;
}

const HOVER_REACH_WALK = 14;
const HOVER_REACH_CROSSHAIR = 11;
const FAR_CARD_DISTANCE = 60;
const DEFAULT_HOVER_REACH = 600;

/**
 * The 3D facade: mount, dispose, setFilter, focusSlot. It builds the scene
 * from the warehouse, owns the camera rig and wires picking, hover and the
 * HUD state; the camera modes, builders and picker do the actual work.
 */
export class SceneView extends View {
    readonly minimap: MinimapView;

    private readonly clock: () => number;
    private readonly renderer: THREE.WebGLRenderer;
    private readonly scene = new THREE.Scene();
    private readonly camera = new THREE.PerspectiveCamera(
        LAYOUT.fovOver,
        1,
        0.25,
        500,
    );
    private readonly atlas = new LabelAtlas();
    private readonly palette: ScenePalette;
    private readonly walk = new WalkState();
    private readonly governor: QualityGovernor;
    private readonly maxPixelRatio: number;
    private readonly content = new THREE.Group();

    private context!: CameraContext;
    private geometry!: WarehouseGeometry;
    private collider!: WallCollider;
    private warehouse: Warehouse;
    private rig!: CameraRig;
    private overview!: OverviewMode;
    private freeWalk!: FreeWalkMode;
    private guidedWalk!: GuidedWalkMode;
    private pallets!: PalletBuilder;
    private gauges!: GaugeBuilder;
    private signs!: SignBuilder;
    private ceiling!: CeilingBuilder;
    private markers!: MarkerBuilder;
    private picker!: SlotPicker;

    private filter: FilterView = {
        match: {
            filtering: false,
            ids: new Set(),
            count: 0,
            byRow: new Map(),
            byLevel: new Map(),
        },
        floor: null,
    };
    private nav: NavMode = 'free';
    private visible = false;
    private hoverSlot: Slot | null = null;
    private targetSlot: Slot | null = null;
    private prevOrbit: Orbit | null = null;
    private activeAisle: number | null = null;
    private pendingHover: [number, number] | null = null;
    private lastPointerAt = 0;
    private lastAutoHover = 0;
    private lastMinimap = 0;
    private walkedEnough = false;
    private minimapHidden = false;

    constructor(private readonly deps: SceneDeps) {
        super();
        this.warehouse = deps.warehouse;
        this.clock = deps.clock ?? (() => performance.now());
        this.palette = readPalette(deps.styleRoot);
        this.renderer =
            deps.createRenderer?.(deps.canvas) ??
            new THREE.WebGLRenderer({
                canvas: deps.canvas,
                antialias: true,
                powerPreference: 'high-performance',
            });
        this.maxPixelRatio = Math.min(window.devicePixelRatio || 1, 2);
        this.renderer.setPixelRatio(this.maxPixelRatio);
        this.renderer.setClearColor(this.palette.background);
        this.scene.background = new THREE.Color(this.palette.background);
        this.scene.fog = new THREE.Fog(this.palette.background, 140, 360);
        this.governor = new QualityGovernor({
            maxPixelRatio: this.maxPixelRatio,
            slowMs: THRESHOLDS.slowFrameMs,
            fastMs: THRESHOLDS.fastFrameMs,
        });
        this.addLights();
        this.scene.add(this.content);
        this.setupGeometry();
        this.minimap = new MinimapView(
            this.geometry,
            this.warehouse.rows,
            this.minimapLabels(),
        );
        this.setupCamera();
        this.buildContent();
    }

    /* ---------- life cycle ---------- */

    mount(): void {
        this.own(this.deps.frames.onFrame((now, dt) => this.tick(now, dt)));
        this.own(this.deps.frames.onRender((now) => this.render(now)));
        this.minimap.mount();
        this.rig.activate('overview');
        this.resize();
    }

    protected onDispose(): void {
        this.minimap.dispose();
        disposeTree(this.content);
        this.scene.clear();
        this.renderer.dispose();
    }

    /** The 3D view became visible (the 2D / 3D toggle). */
    show(): void {
        this.visible = true;
        this.resize();

        if (!this.rig.isFlying) {
            this.enterMode(this.rig.activeId ?? 'overview');
        }

        this.deps.frames.requestRender();
    }

    /** The 2D view took over: let go of the mouse and stop drawing. */
    hide(): void {
        this.visible = false;
        this.deps.capture.release();
        this.setHover(null);
    }

    /** A refetch brought a different warehouse: rebuild what is drawn, keep the camera. */
    setWarehouse(warehouse: Warehouse): void {
        this.warehouse = warehouse;
        disposeTree(this.content);
        this.content.clear();
        this.scene.add(this.content);
        this.setupGeometry();
        this.minimap.setWorld(this.geometry, warehouse.rows);
        this.buildContent();
        this.hoverSlot = null;
        this.activeAisle = null;
        this.resize();
        this.deps.frames.requestRender();
    }

    /* ---------- what the page tells the scene ---------- */

    setFilter(match: MatchIndex, floor: number | null): void {
        this.filter = { match, floor };
        this.pallets.applyFilter(this.filter);
        this.gauges.applyFilter(this.filter);
        this.deps.frames.requestRender();
    }

    /** Puts the yellow target frame on a slot (null removes it). */
    setTarget(slot: Slot | null): void {
        this.targetSlot = slot;
        this.markers.setTarget(
            slot
                ? {
                      center: this.geometry.slotCenter(
                          slot.rowIdx,
                          slot.col,
                          slot.level,
                      ),
                      code: slot.code,
                      side: this.geometry.rowSide(slot.rowIdx),
                  }
                : null,
            this.clock(),
        );
        this.deps.frames.requestRender();
    }

    applySettings(): void {
        this.freeWalk.applySettings();
        this.deps.frames.requestRender();
    }

    onLocale(): void {
        this.signs.relabel();
        this.minimap.setLabels(this.minimapLabels());
        this.deps.frames.requestRender();
    }

    onInputKind(): void {
        if (this.deps.host.inputKind() === 'touch') {
            this.deps.capture.release();
        }

        this.refreshCard();
    }

    resize(): void {
        const canvas = this.deps.canvas;
        const w = canvas.clientWidth;
        const h = canvas.clientHeight;

        if (!w || !h) {
            return;
        }

        this.renderer.setSize(w, h, false);
        this.camera.aspect = w / h;
        this.camera.updateProjectionMatrix();
        this.overview.setFit(this.geometry.fitDistance(this.camera.aspect));

        if (this.rig.activeId === 'overview' && !this.rig.isFlying) {
            this.context.apply(this.overview.pose());
        }

        this.deps.frames.requestRender();
    }

    /* ---------- commands the UI gives ---------- */

    /** Flies into an aisle and starts walking. The click that calls this is the user gesture that lets the browser capture the mouse. */
    enterAisle(aisle: number): void {
        const geo = this.geometry;

        if (aisle < 0 || aisle >= geo.aisles) {
            return;
        }

        this.rememberOrbit();
        this.deps.host.patch({ selectedId: null });
        this.activeAisle = aisle;
        this.requestCapture();

        const guided = this.nav === 'guided';
        const pose: Pose = {
            x: geo.aisleCx(aisle),
            y: guided ? geo.levelEye(Math.min(1, geo.levels - 1)) : LAYOUT.eye,
            z: geo.zMin - 8.8,
            yaw: 0,
            pitch: guided ? 0 : -0.03,
            fov: this.settings.fov,
        };

        this.flyToWalk(pose);
    }

    /** Flies in front of a slot and starts walking there. */
    focusSlot(slot: Slot): void {
        const geo = this.geometry;
        const c = geo.slotCenter(slot.rowIdx, slot.col, slot.level);
        const side = geo.rowSide(slot.rowIdx);
        const cx = geo.aisleCx(slot.rowIdx >> 1);
        let pose: Pose;

        this.rememberOrbit();
        this.requestCapture();

        if (this.nav === 'guided') {
            // in the middle of the aisle, square on to the rack, at that shelf's height
            pose = {
                x: cx,
                y: geo.levelEye(slot.level - 1),
                z: c.z,
                yaw: side > 0 ? Math.PI / 2 : -Math.PI / 2,
                pitch: 0,
                fov: this.settings.fov,
            };
        } else {
            const x = cx - side * 2;
            const z = c.z - 1.6;
            const y = Math.min(3, Math.max(1.5, c.y));
            const dx = c.x - x;
            const dz = c.z - z;

            pose = {
                x,
                y,
                z,
                yaw: Math.atan2(dx, dz),
                pitch: Math.atan2(c.y - y, Math.hypot(dx, dz)),
                fov: this.settings.fov,
            };
        }

        this.activeAisle = slot.rowIdx >> 1;
        this.deps.host.setTarget(slot);
        this.flyToWalk(pose);
    }

    goOverview(): void {
        const geo = this.geometry;
        const orbit = this.prevOrbit
            ? { ...this.prevOrbit }
            : this.overview.fitted(geo.bounds.cx, geo.bounds.cz);

        this.overview.setOrbit(orbit);
        this.deps.host.patch({ selectedId: null });
        this.deps.host.patch({ landing: 'overview' });
        this.rig.flyTo(this.overview.poseOf(orbit), {
            done: () => this.enterMode('overview'),
        });
    }

    setNav(nav: NavMode): void {
        if (this.nav === nav) {
            return;
        }

        this.nav = nav;

        if (this.rig.state !== 'walk') {
            this.deps.host.patch({ nav });

            return;
        }

        if (nav === 'guided') {
            this.deps.capture.release();
            this.rig.activate('guided');
            this.guidedWalk.snap();
        } else {
            this.rig.activate('free');
            this.requestCapture();
        }

        this.deps.host.patch({ nav });
        this.deps.frames.requestRender();
    }

    /** One entry point for everything the input adapters say. */
    handleIntent(intent: Intent): void {
        if (!this.visible) {
            return;
        }

        switch (intent.type) {
            case 'hover':
                this.lastPointerAt = this.clock();

                if (this.rig.state !== 'fly') {
                    this.pendingHover = [intent.x, intent.y];
                }

                return;
            case 'tap':
                this.deps.host.setChromeAway(false);
                this.onTap(intent.x, intent.y, intent.pointer === 'mouse');

                return;
            case 'inspect':
                this.openUnderCrosshair();

                return;
            case 'toggleNav':
                if (this.rig.state === 'walk') {
                    this.setNav(this.nav === 'free' ? 'guided' : 'free');
                }

                return;
            case 'goOverview':
                if (this.rig.state === 'walk') {
                    this.goOverview();
                }

                return;
            case 'toggleMinimap':
                this.minimapHidden = !this.minimapHidden;
                this.deps.host.patch({ minimapHidden: this.minimapHidden });

                return;
            case 'toggleKeys':
                this.deps.host.toggleKeys();

                return;
            case 'aisle':
                this.enterAisle(intent.index);

                return;
            case 'captureChange':
                this.deps.host.patch({ captured: intent.captured });
                this.deps.host.setChromeAway(intent.captured);
                this.deps.frames.requestRender();

                return;
            default:
                this.forwardToCamera(intent);
        }
    }

    private forwardToCamera(intent: Intent): void {
        const handled = this.rig.handle(intent);

        if (!handled) {
            return;
        }

        if (intent.type === 'drag' || intent.type === 'pinch') {
            this.lastPointerAt = this.clock();
            this.deps.host.setChromeAway(true);

            if (this.rig.state === 'overview') {
                this.setHover(null);
            }
        }

        if (intent.type === 'dragEnd') {
            this.deps.host.setChromeAway(false);
        }

        if (
            intent.type === 'wheel' ||
            intent.type === 'zoomStep' ||
            intent.type === 'pinch'
        ) {
            this.refreshCard();
        }

        this.deps.frames.requestRender();
    }

    /* ---------- internals ---------- */

    private get settings(): UserSettings {
        return this.deps.host.settings();
    }

    private setupGeometry(): void {
        const w = this.warehouse;

        this.geometry = new WarehouseGeometry(w.rows.length, w.cols, w.levels);
        this.collider = new WallCollider(this.geometry);
    }

    private addLights(): void {
        // Three r155+ reads light intensities physically: x PI keeps the look of the original r152 legacy lights
        this.scene.add(
            new THREE.HemisphereLight(0xcfe0ff, 0x1b2438, 0.82 * Math.PI),
        );

        const sun = new THREE.DirectionalLight(0xffffff, 0.68 * Math.PI);

        sun.position.set(-30, 70, -40);

        const fill = new THREE.DirectionalLight(0x8aa4ff, 0.22 * Math.PI);

        fill.position.set(40, 30, 50);
        this.scene.add(sun, fill);
    }

    private setupCamera(): void {
        const context = {
            settings: () => this.settings,
            apply: (pose: Pose, bob = 0) => this.applyPose(pose, bob),
            viewHeight: () =>
                this.deps.canvas.clientHeight || window.innerHeight,
            buzz: (ms: number) => this.deps.host.buzz(ms),
        } as CameraContext;

        // the geometry is rebuilt when a refetch brings a different warehouse: always read the current one
        Object.defineProperties(context, {
            geometry: { get: () => this.geometry, enumerable: true },
            collider: { get: () => this.collider, enumerable: true },
        });
        this.context = context;

        const geo = this.geometry;
        const start: Orbit = {
            px: geo.bounds.cx,
            pz: geo.bounds.cz,
            yaw: 0,
            pitch: -0.72,
            dist: geo.fitDistance(1.6),
            fov: LAYOUT.fovOver,
        };

        this.overview = new OverviewMode(start);
        this.freeWalk = new FreeWalkMode(this.walk);
        this.guidedWalk = new GuidedWalkMode(this.walk, this.clock);
        this.rig = new CameraRig(
            this.context,
            (state) => this.onRigState(state),
            this.clock,
        );
        this.rig.register(this.overview);
        this.rig.register(this.freeWalk);
        this.rig.register(this.guidedWalk);
    }

    private buildContent(): void {
        const geo = this.geometry;
        const slots = this.warehouse.slots;
        const rows = this.warehouse.rows;

        this.pallets = new PalletBuilder(geo, this.palette, this.atlas);
        this.gauges = new GaugeBuilder(geo);
        this.signs = new SignBuilder(
            geo,
            rows,
            this.atlas,
            (names) => this.deps.host.t('aisle') + ' ' + names,
        );
        this.ceiling = new CeilingBuilder(geo, this.palette);
        this.markers = new MarkerBuilder(
            this.palette,
            this.atlas,
            geo.layout.rackDepth,
        );
        this.picker = new SlotPicker(geo, slots);

        this.content.add(
            new FloorBuilder(geo, this.palette, this.atlas).build(),
            new RackBuilder(geo, this.palette).build(),
            this.pallets.build(slots),
            this.gauges.build(slots),
            this.signs.build(),
            this.ceiling.build(),
            this.markers.build(),
            this.picker.mesh,
        );
        this.pallets.applyFilter(this.filter);
        this.gauges.applyFilter(this.filter);

        if (this.targetSlot) {
            this.setTarget(this.warehouse.byId.get(this.targetSlot.id) ?? null);
        }
    }

    private minimapLabels(): MinimapLabels {
        return {
            entrance: this.deps.host.t('entrance'),
            aisle: (names) => `${this.deps.host.t('aisle')} ${names}`,
            column: this.deps.host.t('column'),
        };
    }

    private applyPose(pose: Pose, bob: number): void {
        const camera = this.camera;
        const f = forwardVector(pose.yaw, pose.pitch);

        camera.position.set(pose.x, pose.y + bob, pose.z);
        camera.lookAt(
            camera.position.x + f.x,
            camera.position.y + f.y,
            camera.position.z + f.z,
        );

        if (Math.abs(camera.fov - pose.fov) > 0.01) {
            camera.fov = pose.fov;
            camera.updateProjectionMatrix();
        }

        this.deps.frames.requestRender();
    }

    /* ---------- modes ---------- */

    private get walkingId(): ModeId {
        return this.nav === 'guided' ? 'guided' : 'free';
    }

    private onRigState(state: RigState): void {
        this.deps.host.patch({ mode: state, nav: this.nav });
    }

    private flyToWalk(pose: Pose): void {
        this.deps.host.patch({
            landing: 'walk',
            activeAisle: this.activeAisle,
        });
        this.rig.flyTo(pose, {
            done: () => {
                this.walk.set(pose);
                this.enterMode(this.walkingId);
            },
        });
    }

    /**
     * Switches to a camera mode and sets the scene around it: the ceiling and
     * fog for walking, the mouse for free walk, the welcome for a first visit.
     */
    private enterMode(id: ModeId): void {
        const walking = id !== 'overview';

        this.rig.activate(id);
        this.ceiling.setVisible(walking);

        if (this.scene.fog instanceof THREE.Fog) {
            this.scene.fog.near = walking ? 38 : 160;
            this.scene.fog.far = walking ? 120 : 400;
        }

        this.walkedEnough = false;
        this.deps.host.patch({
            walkedEnough: false,
            activeAisle: this.activeAisle,
        });

        if (walking) {
            this.walk.eyeIndex = this.geometry.nearestEye(this.walk.pose.y);
            this.deps.canvas.focus({ preventScroll: true });

            if (id === 'free') {
                this.requestCapture();
            }

            this.applyWalkPose();
        } else {
            this.deps.capture.release();
            this.deps.canvas.blur();
        }

        this.refreshCard();
        this.deps.frames.requestRender();
    }

    private applyWalkPose(): void {
        this.context.apply(this.walk.pose, this.walk.bobY);
    }

    private rememberOrbit(): void {
        if (this.rig.activeId === 'overview' && !this.rig.isFlying) {
            this.prevOrbit = this.overview.snapshot();
        }
    }

    private requestCapture(): void {
        if (this.deps.host.inputKind() === 'touch' || this.nav !== 'free') {
            return;
        }

        this.deps.capture.request();
    }

    /* ---------- picking, hover, selection ---------- */

    private pick(x: number, y: number, reach: number): Slot | null {
        return this.picker.pickAt(
            x,
            y,
            reach,
            this.camera,
            this.deps.canvas.getBoundingClientRect(),
        );
    }

    private pickCentre(reach: number): Slot | null {
        return this.picker.pickCentre(
            reach,
            this.camera,
            this.deps.canvas.getBoundingClientRect(),
        );
    }

    private setHover(slot: Slot | null): void {
        if (slot === this.hoverSlot) {
            return;
        }

        this.hoverSlot = slot;
        this.markers.setHover(
            slot
                ? this.geometry.slotCenter(slot.rowIdx, slot.col, slot.level)
                : null,
        );
        this.refreshCard();
        this.deps.frames.requestRender();
    }

    /** Publishes the hovered slot and whether it is worth describing in the details card. */
    private refreshCard(): void {
        const overviewFar =
            this.rig.state === 'overview' &&
            this.overview.distance > FAR_CARD_DISTANCE;
        const cardVisible =
            !!this.hoverSlot && !overviewFar && this.rig.state !== 'fly';

        this.deps.host.patch({
            hoverId: this.hoverSlot?.id ?? null,
            cardVisible,
        });
    }

    private openUnderCrosshair(): void {
        if (this.rig.state !== 'walk') {
            return;
        }

        const slot = this.hoverSlot ?? this.pickCentre(HOVER_REACH_WALK);

        if (slot) {
            this.deps.host.openRack({
                row: slot.row,
                col: slot.col,
                level: slot.level,
            });
        }
    }

    private onTap(x: number, y: number, mouse: boolean): void {
        const state = this.rig.state;

        if (state === 'fly') {
            return;
        }

        if (state === 'walk') {
            const captured = this.deps.capture.captured;

            if (mouse && this.nav === 'free' && !captured) {
                // the first click only brings the mouse back
                this.requestCapture();

                return;
            }

            if (mouse && captured) {
                return;
            }

            const slot = this.pick(x, y, HOVER_REACH_WALK);

            if (slot) {
                this.deps.host.openRack({
                    row: slot.row,
                    col: slot.col,
                    level: slot.level,
                });
            }

            return;
        }

        const slot = this.pick(x, y, DEFAULT_HOVER_REACH);

        if (!slot) {
            this.deps.host.patch({ selectedId: null });

            return;
        }

        // from far away a pallet is a few pixels wide: a tap flies in to its column and offers the next step
        if (this.overview.distance > THRESHOLDS.flyInDistance) {
            this.flyInOn(slot);
        } else {
            this.deps.host.setTarget(slot);
            this.deps.host.openRack({
                row: slot.row,
                col: slot.col,
                level: slot.level,
            });
        }
    }

    private flyInOn(slot: Slot): void {
        const c: Point3 = this.geometry.slotCenter(
            slot.rowIdx,
            slot.col,
            slot.level,
        );
        const current = this.overview.snapshot();
        const orbit: Orbit = {
            px: c.x,
            pz: c.z,
            yaw: current.yaw,
            pitch: Math.min(current.pitch, -0.55),
            dist: 18,
            fov: LAYOUT.fovOver,
        };

        this.rememberOrbit();
        this.deps.host.setTarget(slot);
        this.overview.setOrbit(orbit);
        this.deps.host.patch({ landing: 'overview' });
        this.rig.flyTo(this.overview.poseOf(orbit), {
            done: () => {
                this.enterMode('overview');
                this.deps.host.patch({ selectedId: slot.id });
            },
        });
    }

    /* ---------- per frame ---------- */

    private tick(now: number, dt: number): void {
        if (!this.visible) {
            return;
        }

        const moved = this.rig.update(dt, now);

        if (moved) {
            this.deps.frames.requestRender();
        }

        if (this.pendingHover && this.rig.state !== 'fly') {
            const [x, y] = this.pendingHover;

            this.pendingHover = null;
            this.setHover(
                this.pick(
                    x,
                    y,
                    this.rig.state === 'walk'
                        ? HOVER_REACH_WALK
                        : DEFAULT_HOVER_REACH,
                ),
            );
        }

        if (this.rig.state === 'walk') {
            this.autoHover(now);
            this.trackWalking();
        }

        if (this.markers.pulse(now)) {
            this.deps.frames.requestRender();
        }

        const ratio = this.governor.sample(
            now,
            this.deps.frames.needsRender,
            this.renderer.getPixelRatio(),
        );

        if (ratio !== null) {
            this.renderer.setPixelRatio(ratio);
            this.resize();
        }
    }

    /** What is in front of you is described automatically: under the crosshair while the mouse is captured, and in guided mode. */
    private autoHover(now: number): void {
        const captured = this.deps.capture.captured;
        const guided = this.nav === 'guided';
        const idle =
            this.deps.host.inputKind() === 'touch' ||
            now - this.lastPointerAt > 1500;

        if (!(captured || (guided && idle))) {
            return;
        }

        if (now - this.lastAutoHover < 80) {
            return;
        }

        this.lastAutoHover = now;
        this.setHover(
            this.pickCentre(guided ? HOVER_REACH_WALK : HOVER_REACH_CROSSHAIR),
        );
    }

    /** After a few seconds of walking the welcome card has done its job. */
    private trackWalking(): void {
        if (!this.walkedEnough && this.freeWalk.movedSeconds > 2.5) {
            this.walkedEnough = true;
            this.deps.host.patch({ walkedEnough: true });
        }
    }

    private render(now: number): void {
        if (!this.visible) {
            return;
        }

        this.renderer.render(this.scene, this.camera);

        if (now - this.lastMinimap > 40) {
            this.lastMinimap = now;
            this.drawMinimap();
        }
    }

    private drawMinimap(): void {
        const state = this.rig.state;
        const pose = this.rig.currentPose();
        const orbit = this.overview.snapshot();
        const target = this.targetSlot
            ? this.geometry.slotCenter(
                  this.targetSlot.rowIdx,
                  this.targetSlot.col,
                  this.targetSlot.level,
              )
            : null;

        this.minimap.draw({
            mode: state,
            x: state === 'overview' ? orbit.px : pose.x,
            z: state === 'overview' ? orbit.pz : pose.z,
            yaw: pose.yaw,
            target: target ? { x: target.x, z: target.z } : null,
        });
    }
}

<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronUp,
    Navigation,
    Zap,
} from '@lucide/vue';
import * as THREE from 'three';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import CellSlot from '@/components/CellSlot.vue';
import {
    CELL_STATES,
    CELL_STATE_COLOR,
    cellStateLabel,
} from '@/lib/cellStateColor';
import { mapToolbarButtonClass, selectedToggleClass } from '@/lib/filters';
import { distanceBetween } from '@/lib/geometry';
import { t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import {
    BOX_SIZE,
    boundsForWarehouse,
    CAMERA_FOV_DEGREES,
    cellWorldZ,
    clamp,
    defaultOrbitState,
    EYE_HEIGHT,
    facedGridCoordinate,
    facedKey,
    flatWorldY,
    lookDirection,
    maxOf,
    miniMapHeadingDegrees,
    miniMapPosition,
    miniMapRowLeftPercents,
    minOf,
    moveDirectionForKey,
    nearestCellNumber,
    nearestFlatNumber,
    nearestRowIndex,
    orbitCameraPosition,
    orbitDistanceFromPinch,
    orbitRangeForBounds,
    pitchToLookAt,
    rowWorldX,
    SPRINT_MULTIPLIER,
    stepOrbitDistance,
    stepOrbitPitch,
    stepOrbitStateByKeys,
    stepPitch,
    stepPosition,
    stepYaw,
    WALK_SPEED,
    worldPointToScreenPercent,
} from '@/lib/mapWalker';
import type {
    FacedGridCoordinate,
    MoveDirection,
    OrbitRange,
    OrbitState,
    WalkerBounds,
} from '@/lib/mapWalker';
import { productName } from '@/lib/productName';
import type { Cell, CellMap3DBand, CellMap3DItem } from '@/types/admin';

const props = defineProps<{
    bands: CellMap3DBand[];
}>();

const emit = defineEmits<{
    'camera-mode-change': [mode: 'walk' | 'orbit'];
    'manage-pallet': [cell: Cell, label: string];
    'toggle-active': [cell: Cell, label: string];
}>();

const containerRef = ref<HTMLElement | null>(null);

/** Touch devices get on-screen move/fly buttons instead of WASD/Space/Shift. */
const isTouchDevice = ref(false);

type Disposable = { dispose: () => void };

interface FacedItem {
    rowLetter: string;
    item: CellMap3DItem;
}

const VIEW_DISTANCE = cellWorldZ(1);
const HIGHLIGHT_COLOR = 0x3b82f6;
const PULSE_COLOR = 0x10b981;
/** Outline drawn on an inactive (corrupted) cell — same red as CELL_STATE_COLOR's overlay badge. */
const INACTIVE_COLOR = 0xef4444;
const SELECT_COLOR = 0x8b5cf6;
const SELECT_OUTLINE_SCALE = 1.15;
/** Orbit-mode hover outline — same weight as highlight/pulse outlines, just a distinct neutral color. */
const HOVER_COLOR = 0xffffff;
/**
 * Empty cells still render a faint box (rather than being fully invisible)
 * so an empty slot reads as an obvious gap in the shelf, matching how the 2D
 * grid (CellSlot.vue) already shows empty cells as a visible, lightly
 * colored tile rather than blank space — and so a highlight/pulse outline on
 * an empty cell has an actual box to frame instead of floating in open air.
 */
const EMPTY_CELL_OPACITY = 0.18;
/**
 * Per-instance color tint (multiplied onto the state's base material color
 * via `InstancedMesh.setColorAt`) for a cell that doesn't match an active
 * highlight filter — mirrors the 2D grid's `grayscale opacity-40` fade
 * (CellSlot.vue) so a non-match reads the same way in both views. White
 * (no-op multiply) is the untinted default.
 */
const NORMAL_TINT = 0xffffff;
const DIMMED_TINT = 0x666666;
/** Walk-mode mini-map: below this delta the reactive mirror isn't updated, to avoid a reactive write on every animation frame. */
const MINI_MAP_UPDATE_EPSILON = 0.05;
const MINI_MAP_UPDATE_EPSILON_DEGREES = 1;
/** Row-label overlay: below this screen-percent delta the reactive mirror isn't updated, same rationale as MINI_MAP_UPDATE_EPSILON. */
const ROW_LABEL_UPDATE_EPSILON_PERCENT = 0.5;
/** How far above a row's topmost shelf its floor-aisle-sign label floats. */
const ROW_LABEL_HEIGHT_ABOVE_SHELVES = 1;
/**
 * How far into the aisle (in cell-spacings) a row's sign is anchored, rather
 * than right at the entrance (Z=0/half a cell in) — high enough above the
 * shelves and close enough to the entrance that a sign right overhead would
 * need an unrealistically steep look-up angle to stay in frame at the
 * default straight-ahead pitch; anchoring a couple of cells further down
 * the aisle keeps the look-up angle within the camera's vertical FOV for
 * the default standing-at-the-entrance view.
 */
const ROW_LABEL_DEPTH_IN_CELLS = 2;
const SKY_COLOR = 0xe5e7eb;
const SHELF_COLOR = 0x64748b;
const SHELF_THICKNESS = 0.15;
/** A pointer that moved less than this while held counts as a click, not a drag. */
const CLICK_MOVE_THRESHOLD_PIXELS = 4;
/** How far a shelf platform overhangs the outermost box it carries, on every side. */
const SHELF_MARGIN = BOX_SIZE * 0.3;
const POST_SIZE = 0.15;
/** How far a row's corner posts stand out from its boxes, in X (beside the aisle). */
const POST_OFFSET = BOX_SIZE / 2 + SHELF_MARGIN;

/** Key-cap badge for the controls legend (e.g. the `Space`/`Shift` keys). */
const kbdClass =
    'rounded border border-gray-400 bg-white px-1.5 py-0.5 font-mono text-[10px] font-medium text-gray-700 shadow-sm dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-200';

function stateHexColor(state: Cell['state']): string {
    return `#${CELL_STATE_COLOR[state].hex.toString(16).padStart(6, '0')}`;
}

let renderer: THREE.WebGLRenderer | null = null;
let scene: THREE.Scene | null = null;
let camera: THREE.PerspectiveCamera | null = null;
let animationFrameId: number | null = null;
let resizeObserver: ResizeObserver | null = null;
let cellGroup: THREE.Group | null = null;
let cellDisposables: Disposable[] = [];
const sceneDisposables: Disposable[] = [];
let cellLookup = new Map<string, FacedItem>();

interface InstanceSlot {
    state: Cell['state'];
    index: number;
}

interface StateInstances {
    mesh: THREE.InstancedMesh;
    /** `keys[instanceId]` — the cell key currently occupying that instance slot. */
    keys: string[];
}

/**
 * One `THREE.InstancedMesh` per cell state (rather than one `THREE.Mesh` per
 * cell) — hundreds/thousands of individual boxes sharing geometry/material
 * were still one draw call each; instancing collapses all boxes of a given
 * state into a single draw call. Each mesh is allocated at the *total* cell
 * count (not just that state's current count) so any cell can move into any
 * state in place (`placeCellInstance`/`removeCellInstance`) without ever
 * needing to reallocate — `.count` tracks how many of the allocated slots are
 * actually in use.
 */
let instancesByState = new Map<Cell['state'], StateInstances>();

/**
 * Flattened `instancesByState` meshes, rebuilt only alongside
 * `instancesByState` itself (`disposeCellGroup`/`buildCellGroup`) rather than
 * re-derived on every call — `cellBoxAtScreenPoint` reads this on every
 * orbit-mode `pointermove`, so it shouldn't allocate a fresh array per hover
 * frame.
 */
let cellInstanceMeshes: THREE.InstancedMesh[] = [];

/**
 * Every currently-built cell's instance slot, keyed by `facedKey`. Lets
 * `rebuildCells` update an existing cell's state/outline in place
 * (`updateCellStatesAndLookup`) instead of tearing down and rebuilding the
 * whole group whenever the *set* of cells hasn't actually changed (e.g. a
 * highlight-filter toggle or a single cell's state changing).
 */
let instanceSlotByKey = new Map<string, InstanceSlot>();
let cellOutlineLookup = new Map<string, THREE.LineSegments>();

/**
 * Box geometry and per-state/per-outline-color materials are identical
 * across every rebuild (fixed box size, one draw call per occupancy state,
 * one outline color per highlight/pulse/inactive kind) — built once lazily
 * and reused (disposed only on unmount via
 * `sceneDisposables`) instead of one new geometry/material instance per box
 * on every rebuild.
 */
let sharedBoxGeometry: THREE.BoxGeometry | null = null;
let sharedEdgesGeometry: THREE.EdgesGeometry | null = null;
const boxMaterialsByState = new Map<
    Cell['state'],
    THREE.MeshStandardMaterial
>();
let highlightOutlineMaterial: THREE.LineBasicMaterial | null = null;
let pulseOutlineMaterial: THREE.LineBasicMaterial | null = null;
let inactiveOutlineMaterial: THREE.LineBasicMaterial | null = null;

const pressedDirections = new Set<MoveDirection>();
const position = { x: 0, y: EYE_HEIGHT, z: 0 };
let pitchDegrees = 0;
let yawDegrees = 0;
/**
 * Reactive (not a plain `let`) because the mini-map's row-line positions
 * (`miniMapRowPositions`) are derived from it in a `computed` — a plain
 * reassigned variable wouldn't be tracked, so `updateBounds()` mutating a
 * plain object left that computed permanently cached at its pre-mount
 * all-zero default (rows all clamped to the mini-map's edges, effectively
 * invisible) even after the real bounds were computed on mount.
 */
const bounds: WalkerBounds = reactive({
    minX: 0,
    maxX: 0,
    minY: 0,
    maxY: 0,
    minZ: 0,
    maxZ: 0,
});
let lastFrameTime: number | null = null;
let isDragging = false;
let lastPointerX = 0;
let lastPointerY = 0;
let dragStartX = 0;
let dragStartY = 0;
let dragMoved = false;

/**
 * Sprint (fixes WALK_SPEED being a flat constant regardless of warehouse
 * size). `isSprinting` is a held-key state (Ctrl on desktop) read directly
 * in `animate()` like `position`/`pitchDegrees` — no reactivity needed since
 * nothing in the template reflects it — and reset on blur like
 * `pressedDirections`. `touchSprintEnabled` is a deliberate tap-to-toggle
 * setting for touch (no "hold Ctrl" equivalent gesture there), so it IS a
 * ref (the toggle button's pressed state reads it) and is NOT reset on
 * blur.
 */
let isSprinting = false;
const touchSprintEnabled = ref(false);

/**
 * Overview mode: an orbit camera around the whole warehouse instead of a
 * first-person walk. `orbitRange` (center/zoom limits) is derived from
 * `bounds` in `updateBounds()`; `orbitState` (angle/distance) resets to a
 * default overview every time orbit mode is (re)entered — see
 * `setCameraMode`.
 */
const cameraMode = ref<'walk' | 'orbit'>('walk');
let orbitRange: OrbitRange = {
    center: { x: 0, y: 0, z: 0 },
    minDistance: 0,
    maxDistance: 0,
    defaultDistance: 0,
};
let orbitState: OrbitState = { yawDegrees: 0, pitchDegrees: 0, distance: 0 };

/** Multi-pointer tracking for orbit's two-finger pinch-to-zoom (touch). */
const activePointers = new Map<number, { x: number; y: number }>();
let pinchStartGap: number | null = null;
let orbitDistanceAtPinchStart = 0;

const raycaster = new THREE.Raycaster();
const pointerNdc = new THREE.Vector2();

/**
 * Orbit-mode hover: shows which box the pointer is over before clicking, so
 * selection isn't blind-clicking. `hoveredKey` mirrors the `lastFacedKey`/
 * `selectedKey` key-diffing style; `isHoveringSelectable` is the reactive
 * mirror the template reads to show a pointer cursor.
 */
let hoveredKey: string | null = null;
let hoverOutline: THREE.LineSegments | null = null;
const isHoveringSelectable = ref(false);

/**
 * Walk-mode mini-map: a reactive mirror of `position.x`/`position.z`/
 * `yawDegrees` (all otherwise-plain, per-frame-mutated values), updated only
 * past MINI_MAP_UPDATE_EPSILON so a small overlay doesn't trigger a reactive
 * write on every single animation frame.
 */
const miniMap = reactive({ x: 0, y: 0, z: 0, yawDegrees: 0 });

/**
 * Floor-aisle-sign labels — floating row-letter signage over the 3D scene
 * itself (not just the walk-mode mini-map), so a row is identifiable at a
 * glance while walking without flying up to a cell and reading the faced-
 * cell panel or checking the mini-map. Only rendered/updated in walk mode,
 * like the mini-map and location indicator (`updateMiniMap`,
 * `updateCurrentLocation`) — an orbit overview already sees every row at
 * once. Each entry is a live projection (`worldPointToScreenPercent`) of the
 * row's anchor point (`rowLabelWorldPosition`) onto the walk camera's
 * screen, refreshed every frame but only written past
 * ROW_LABEL_UPDATE_EPSILON_PERCENT to avoid a reactive write every frame.
 */
interface RowLabel {
    letter: string;
    leftPercent: number;
    topPercent: number;
    visible: boolean;
}

const rowLabels = reactive<RowLabel[]>([]);

/**
 * A row's floor sign floats above its own topmost shelf, halfway into the
 * aisle entrance (not exactly at Z=0, the near edge of `bounds`) — the
 * default walk position stands exactly at Z=0 facing forward
 * (`resetView`/`focusCell`), where a sign coplanar with the camera would sit
 * at an undefined/zero depth instead of visibly ahead of it.
 */
function rowLabelWorldPosition(
    rowIndex: number,
    band: CellMap3DBand,
): { x: number; y: number; z: number } {
    const topFlat = maxOf(
        band.items.map((item) => item.flatNumber),
        1,
    );

    return {
        x: rowWorldX(rowIndex),
        y: flatWorldY(topFlat) + SHELF_MARGIN + ROW_LABEL_HEIGHT_ABOVE_SHELVES,
        z: cellWorldZ(ROW_LABEL_DEPTH_IN_CELLS),
    };
}

function updateRowLabels(): void {
    const container = containerRef.value;

    if (!container || container.clientWidth === 0) {
        return;
    }

    const aspect = container.clientWidth / (container.clientHeight || 1);

    props.bands.forEach((band, rowIndex) => {
        const projected = worldPointToScreenPercent(
            position,
            pitchDegrees,
            yawDegrees,
            aspect,
            rowLabelWorldPosition(rowIndex, band),
        );
        const existing: RowLabel | undefined = rowLabels[rowIndex];

        if (
            existing &&
            existing.letter === band.letter &&
            existing.visible === projected.visible &&
            Math.abs(existing.leftPercent - projected.leftPercent) <
                ROW_LABEL_UPDATE_EPSILON_PERCENT &&
            Math.abs(existing.topPercent - projected.topPercent) <
                ROW_LABEL_UPDATE_EPSILON_PERCENT
        ) {
            return;
        }

        rowLabels[rowIndex] = { letter: band.letter, ...projected };
    });

    rowLabels.length = props.bands.length;
}

const visibleRowLabels = computed(() =>
    rowLabels.filter((label) => label.visible),
);

function getBoxGeometry(): THREE.BoxGeometry {
    if (!sharedBoxGeometry) {
        sharedBoxGeometry = new THREE.BoxGeometry(BOX_SIZE, BOX_SIZE, BOX_SIZE);
        sceneDisposables.push(sharedBoxGeometry);
    }

    return sharedBoxGeometry;
}

function getEdgesGeometry(): THREE.EdgesGeometry {
    if (!sharedEdgesGeometry) {
        sharedEdgesGeometry = new THREE.EdgesGeometry(
            new THREE.BoxGeometry(
                BOX_SIZE * 1.05,
                BOX_SIZE * 1.05,
                BOX_SIZE * 1.05,
            ),
        );
        sceneDisposables.push(sharedEdgesGeometry);
    }

    return sharedEdgesGeometry;
}

function boxMaterialForState(state: Cell['state']): THREE.MeshStandardMaterial {
    let material = boxMaterialsByState.get(state);

    if (!material) {
        material = new THREE.MeshStandardMaterial({
            color: CELL_STATE_COLOR[state].hex,
            // Empty cells stay raycastable (click/hover/facing still work on
            // them) and render as a faint placeholder box (EMPTY_CELL_OPACITY)
            // — a full-opacity gray box would wrongly read as "something is
            // stored here" when the slot is actually empty, but leaving it
            // fully invisible (opacity 0) left no visible shelf slot at all.
            transparent: state === 'empty',
            opacity: state === 'empty' ? EMPTY_CELL_OPACITY : 1,
        });
        boxMaterialsByState.set(state, material);
        sceneDisposables.push(material);
    }

    return material;
}

function outlineMaterialFor(
    kind: 'highlight' | 'pulse' | 'inactive',
): THREE.LineBasicMaterial {
    if (kind === 'pulse') {
        if (!pulseOutlineMaterial) {
            pulseOutlineMaterial = new THREE.LineBasicMaterial({
                color: PULSE_COLOR,
            });
            sceneDisposables.push(pulseOutlineMaterial);
        }

        return pulseOutlineMaterial;
    }

    if (kind === 'inactive') {
        if (!inactiveOutlineMaterial) {
            inactiveOutlineMaterial = new THREE.LineBasicMaterial({
                color: INACTIVE_COLOR,
            });
            sceneDisposables.push(inactiveOutlineMaterial);
        }

        return inactiveOutlineMaterial;
    }

    if (!highlightOutlineMaterial) {
        highlightOutlineMaterial = new THREE.LineBasicMaterial({
            color: HIGHLIGHT_COLOR,
        });
        sceneDisposables.push(highlightOutlineMaterial);
    }

    return highlightOutlineMaterial;
}

/**
 * Which outline (if any) a cell should draw — pulse/highlight take priority
 * over the inactive marker since they're a deliberate, momentary user focus
 * (search match, jump target) that shouldn't be visually crowded out by the
 * more persistent inactive-cell indicator.
 */
function outlineKindFor(
    item: CellMap3DItem,
): 'highlight' | 'pulse' | 'inactive' | null {
    if (item.pulsing) {
        return 'pulse';
    }

    if (item.highlighted) {
        return 'highlight';
    }

    if (!item.isActive) {
        return 'inactive';
    }

    return null;
}

function disposeCellGroup(): void {
    if (cellGroup && scene) {
        scene.remove(cellGroup);
    }

    cellDisposables.forEach((disposable) => disposable.dispose());
    cellDisposables = [];
    cellGroup = null;
    instancesByState = new Map();
    cellInstanceMeshes = [];
    instanceSlotByKey = new Map();
    cellOutlineLookup = new Map();
}

/**
 * Drops a state mesh's cached bounding sphere after its `.count` or instance
 * matrices change. `THREE.InstancedMesh` derives that sphere lazily — both
 * `Frustum.intersectsObject` (the renderer's per-frame culling test) and
 * `InstancedMesh.raycast` do `if (this.boundingSphere === null)
 * this.computeBoundingSphere()` — and neither `setMatrixAt` nor assigning
 * `.count` clears it, so without this a mesh keeps whatever sphere it had on
 * its first frame forever. A state that started out with no cells keeps an
 * *empty* sphere and is culled at every camera angle once a cell moves into it
 * (the cell silently vanishes from the map), and a state whose cells all sat
 * in one row keeps a sphere around that row and stops hit-testing
 * (click-to-select, hover outline) cells that later move into it elsewhere.
 *
 * Nulling it for a lazy recompute rather than calling `computeBoundingSphere()`
 * here: that recompute unions every one of the mesh's `.count` instance
 * matrices, so doing it eagerly per instance would make building a warehouse
 * O(cells²); nulling is O(1) per mutation and three recomputes once, on the
 * next frame or raycast that actually needs it. Frustum culling deliberately
 * stays enabled — clearing `frustumCulled` would only mask the rendering half
 * and leave raycasting broken, since `raycast()` reads the same cached sphere.
 */
function invalidateInstanceBounds(mesh: THREE.InstancedMesh): void {
    mesh.boundingSphere = null;
}

/**
 * Sets (or clears) the dimmed tint on an already-placed instance — used both
 * when a cell is first placed and whenever a highlight-filter change flips
 * `dimmed` without the cell's state/position changing at all.
 */
function setInstanceTint(
    state: Cell['state'],
    index: number,
    dimmed: boolean,
): void {
    const stateEntry = instancesByState.get(state);

    if (!stateEntry) {
        return;
    }

    stateEntry.mesh.setColorAt(
        index,
        new THREE.Color(dimmed ? DIMMED_TINT : NORMAL_TINT),
    );

    if (stateEntry.mesh.instanceColor) {
        stateEntry.mesh.instanceColor.needsUpdate = true;
    }
}

/**
 * Occupies the next free instance slot for `state` with a box at
 * (x, y, z), recording it in `instanceSlotByKey` — shared by the initial
 * build (`buildCellGroup`) and by `updateCellStatesAndLookup` when an
 * existing cell moves to a different state in place.
 */
function placeCellInstance(
    key: string,
    state: Cell['state'],
    x: number,
    y: number,
    z: number,
    dimmed: boolean,
): void {
    const stateEntry = instancesByState.get(state);

    if (!stateEntry) {
        return;
    }

    const index = stateEntry.mesh.count;
    stateEntry.mesh.setMatrixAt(
        index,
        new THREE.Matrix4().makeTranslation(x, y, z),
    );
    stateEntry.mesh.count = index + 1;
    stateEntry.mesh.instanceMatrix.needsUpdate = true;
    invalidateInstanceBounds(stateEntry.mesh);
    stateEntry.keys[index] = key;
    instanceSlotByKey.set(key, { state, index });
    setInstanceTint(state, index, dimmed);
}

/**
 * Frees `key`'s instance slot via swap-remove (move the last-occupied slot's
 * matrix/key into the freed slot, then shrink `.count` by one) rather than
 * leaving a gap — `THREE.InstancedMesh` only ever renders its first `.count`
 * instances, so a gap in the middle would either hide a real box or require
 * a full re-pack anyway.
 */
function removeCellInstance(key: string): void {
    const slot = instanceSlotByKey.get(key);

    if (!slot) {
        return;
    }

    const stateEntry = instancesByState.get(slot.state);

    if (!stateEntry) {
        return;
    }

    const lastIndex = stateEntry.mesh.count - 1;

    if (slot.index !== lastIndex) {
        const movedMatrix = new THREE.Matrix4();
        stateEntry.mesh.getMatrixAt(lastIndex, movedMatrix);
        stateEntry.mesh.setMatrixAt(slot.index, movedMatrix);

        if (stateEntry.mesh.instanceColor) {
            const movedColor = new THREE.Color();
            stateEntry.mesh.getColorAt(lastIndex, movedColor);
            stateEntry.mesh.setColorAt(slot.index, movedColor);
        }

        const movedKey = stateEntry.keys[lastIndex];
        stateEntry.keys[slot.index] = movedKey;
        instanceSlotByKey.set(movedKey, {
            state: slot.state,
            index: slot.index,
        });
    }

    stateEntry.keys.pop();
    stateEntry.mesh.count = lastIndex;
    stateEntry.mesh.instanceMatrix.needsUpdate = true;
    invalidateInstanceBounds(stateEntry.mesh);
    instanceSlotByKey.delete(key);
}

function buildCellGroup(bands: CellMap3DBand[]): THREE.Group {
    const group = new THREE.Group();
    const boxGeometry = getBoxGeometry();
    const edgesGeometry = getEdgesGeometry();
    const totalCells = bands.reduce((sum, band) => sum + band.items.length, 0);
    instancesByState = new Map();
    cellInstanceMeshes = [];
    instanceSlotByKey = new Map();
    cellOutlineLookup = new Map();

    for (const state of CELL_STATES) {
        const mesh = new THREE.InstancedMesh(
            boxGeometry,
            boxMaterialForState(state),
            Math.max(totalCells, 1),
        );
        mesh.name = 'cell-box';
        mesh.count = 0;
        group.add(mesh);
        instancesByState.set(state, { mesh, keys: [] });
        cellInstanceMeshes.push(mesh);
    }

    bands.forEach((band, rowIndex) => {
        for (const item of band.items) {
            const key = facedKey(rowIndex, item.cellNumber, item.flatNumber);
            const x = rowWorldX(rowIndex);
            const y = flatWorldY(item.flatNumber);
            const z = cellWorldZ(item.cellNumber);
            placeCellInstance(key, item.state, x, y, z, item.dimmed);

            const outlineKind = outlineKindFor(item);

            if (outlineKind) {
                const outline = new THREE.LineSegments(
                    edgesGeometry,
                    outlineMaterialFor(outlineKind),
                );
                outline.position.set(x, y, z);
                group.add(outline);
                cellOutlineLookup.set(key, outline);
            }
        }
    });

    addShelves(group, bands);
    addPosts(group, bands);

    return group;
}

/**
 * One flat platform per (row, flat) — spanning every cell that flat actually
 * has, sitting just under its boxes — so each flat reads as a real shelf
 * level rather than a loose cluster of floating boxes.
 */
function addShelves(group: THREE.Group, bands: CellMap3DBand[]): void {
    const shelfMaterial = new THREE.MeshStandardMaterial({
        color: SHELF_COLOR,
    });
    cellDisposables.push(shelfMaterial);

    bands.forEach((band, rowIndex) => {
        const cellNumbersByFlat = new Map<number, number[]>();

        for (const item of band.items) {
            const cellNumbers = cellNumbersByFlat.get(item.flatNumber) ?? [];
            cellNumbers.push(item.cellNumber);
            cellNumbersByFlat.set(item.flatNumber, cellNumbers);
        }

        for (const [flatNumber, cellNumbers] of cellNumbersByFlat) {
            const minCell = minOf(cellNumbers);
            const maxCell = maxOf(cellNumbers);
            const depth =
                cellWorldZ(maxCell) -
                cellWorldZ(minCell) +
                BOX_SIZE +
                SHELF_MARGIN * 2;

            const shelfGeometry = new THREE.BoxGeometry(
                BOX_SIZE + SHELF_MARGIN * 2,
                SHELF_THICKNESS,
                depth,
            );
            cellDisposables.push(shelfGeometry);

            const shelf = new THREE.Mesh(shelfGeometry, shelfMaterial);
            shelf.name = 'shelf';
            shelf.position.set(
                rowWorldX(rowIndex),
                flatWorldY(flatNumber) - BOX_SIZE / 2 - SHELF_THICKNESS / 2,
                (cellWorldZ(minCell) + cellWorldZ(maxCell)) / 2,
            );
            group.add(shelf);
        }
    });
}

/**
 * Four corner support posts per row — standing beside the boxes (not
 * through them), running from the floor up to the topmost shelf — so the
 * shelves read as one connected rack frame instead of platforms floating
 * in mid-air.
 */
function addPosts(group: THREE.Group, bands: CellMap3DBand[]): void {
    const postMaterial = new THREE.MeshStandardMaterial({ color: SHELF_COLOR });
    cellDisposables.push(postMaterial);

    bands.forEach((band, rowIndex) => {
        if (band.items.length === 0) {
            return;
        }

        const cellNumbers = band.items.map((item) => item.cellNumber);
        const flatNumbers = band.items.map((item) => item.flatNumber);
        const minCell = minOf(cellNumbers);
        const maxCell = maxOf(cellNumbers);
        const postHeight = flatWorldY(maxOf(flatNumbers)) + SHELF_MARGIN;

        const postGeometry = new THREE.BoxGeometry(
            POST_SIZE,
            postHeight,
            POST_SIZE,
        );
        cellDisposables.push(postGeometry);

        const rowX = rowWorldX(rowIndex);
        const xPositions = [rowX - POST_OFFSET, rowX + POST_OFFSET];
        const zPositions = [
            cellWorldZ(minCell) - SHELF_MARGIN,
            cellWorldZ(maxCell) + SHELF_MARGIN,
        ];

        for (const x of xPositions) {
            for (const z of zPositions) {
                const post = new THREE.Mesh(postGeometry, postMaterial);
                post.name = 'post';
                post.position.set(x, postHeight / 2, z);
                group.add(post);
            }
        }
    });
}

function updateBounds(): void {
    const rowCount = props.bands.length;
    const items = props.bands.flatMap((band) => band.items);
    const maxCellsCount = maxOf(
        items.map((item) => item.cellNumber),
        1,
    );
    const maxFlatNumber = maxOf(
        items.map((item) => item.flatNumber),
        1,
    );

    Object.assign(
        bounds,
        boundsForWarehouse(rowCount, maxCellsCount, maxFlatNumber),
    );
    orbitRange = orbitRangeForBounds(bounds);
}

function rebuildCellLookup(bands: CellMap3DBand[]): void {
    cellLookup = new Map();

    bands.forEach((band, rowIndex) => {
        for (const item of band.items) {
            cellLookup.set(
                facedKey(rowIndex, item.cellNumber, item.flatNumber),
                { rowLetter: band.letter, item },
            );
        }
    });

    invalidateAfterCellDataChange();
}

function bandsCellKeys(bands: CellMap3DBand[]): Set<string> {
    const keys = new Set<string>();

    bands.forEach((band, rowIndex) => {
        for (const item of band.items) {
            keys.add(facedKey(rowIndex, item.cellNumber, item.flatNumber));
        }
    });

    return keys;
}

function sameCellKeys(a: Set<string>, b: Set<string>): boolean {
    if (a.size !== b.size) {
        return false;
    }

    for (const key of a) {
        if (!b.has(key)) {
            return false;
        }
    }

    return true;
}

/**
 * Updates every existing cell's state/outline in place AND rebuilds
 * `cellLookup` in the SAME pass over `bands`/items — used whenever the *set*
 * of cells hasn't changed (see `rebuildCells`), so a highlight-filter toggle
 * or a single cell's state changing doesn't flicker/rebuild the entire
 * warehouse, and doesn't walk every band/item twice (this used to be two
 * separate full passes — `updateCellStates` then `rebuildCellLookup`).
 * Shelves/posts are untouched here since they only depend on which cells
 * exist, not their state/highlight/pulse.
 */
function updateCellStatesAndLookup(bands: CellMap3DBand[]): void {
    if (!cellGroup) {
        return;
    }

    const group = cellGroup;
    const newLookup = new Map<string, FacedItem>();

    bands.forEach((band, rowIndex) => {
        for (const item of band.items) {
            const key = facedKey(rowIndex, item.cellNumber, item.flatNumber);
            newLookup.set(key, { rowLetter: band.letter, item });

            const slot = instanceSlotByKey.get(key);

            if (!slot) {
                continue;
            }

            if (slot.state !== item.state) {
                removeCellInstance(key);
                placeCellInstance(
                    key,
                    item.state,
                    rowWorldX(rowIndex),
                    flatWorldY(item.flatNumber),
                    cellWorldZ(item.cellNumber),
                    item.dimmed,
                );
            } else {
                setInstanceTint(slot.state, slot.index, item.dimmed);
            }

            const existingOutline = cellOutlineLookup.get(key);
            const outlineKind = outlineKindFor(item);

            if (!outlineKind) {
                if (existingOutline) {
                    group.remove(existingOutline);
                    cellOutlineLookup.delete(key);
                }

                continue;
            }

            const outlineMaterial = outlineMaterialFor(outlineKind);

            if (existingOutline) {
                existingOutline.material = outlineMaterial;
            } else {
                const outline = new THREE.LineSegments(
                    getEdgesGeometry(),
                    outlineMaterial,
                );
                outline.position.set(
                    rowWorldX(rowIndex),
                    flatWorldY(item.flatNumber),
                    cellWorldZ(item.cellNumber),
                );
                group.add(outline);
                cellOutlineLookup.set(key, outline);
            }
        }
    });

    cellLookup = newLookup;

    invalidateAfterCellDataChange();
}

function rebuildCells(): void {
    if (!scene) {
        return;
    }

    const newKeys = bandsCellKeys(props.bands);

    if (cellGroup && sameCellKeys(newKeys, new Set(instanceSlotByKey.keys()))) {
        updateCellStatesAndLookup(props.bands);

        return;
    }

    disposeCellGroup();
    cellGroup = buildCellGroup(props.bands);
    scene.add(cellGroup);
    updateBounds();
    rebuildCellLookup(props.bands);
}

/** Moves the camera to face the given cell and pitches to look at its flat level. */
function focusCell(
    rowLetter: string,
    cellNumber: number,
    flatNumber: number,
): void {
    const rowIndex = props.bands.findIndex((band) => band.letter === rowLetter);

    if (rowIndex === -1) {
        return;
    }

    position.x = clamp(rowWorldX(rowIndex), bounds.minX, bounds.maxX);
    position.y = clamp(EYE_HEIGHT, bounds.minY, bounds.maxY);
    position.z = clamp(
        cellWorldZ(cellNumber) - VIEW_DISTANCE,
        bounds.minZ,
        bounds.maxZ,
    );
    pitchDegrees = pitchToLookAt(flatWorldY(flatNumber), VIEW_DISTANCE);
    yawDegrees = 0;
}

/** Cell A1, flat 1 is the 3D view's orientation anchor, mirroring the 2D map's. */
function resetView(): void {
    const firstBand = props.bands[0];

    if (firstBand) {
        focusCell(firstBand.letter, 1, 1);

        return;
    }

    position.x = 0;
    position.y = EYE_HEIGHT;
    position.z = 0;
    pitchDegrees = 0;
    yawDegrees = 0;
}

/**
 * Switches between the first-person walk camera and the orbit/overview
 * camera. Entering orbit mode always resets it to the default overview
 * angle/distance rather than remembering where a previous orbit session
 * left off, matching how `resetView` already re-anchors rather than
 * persisting an arbitrary prior state. Any click-selected cell is cleared
 * on every switch, since it's only ever shown while orbiting.
 */
function setCameraMode(mode: 'walk' | 'orbit'): void {
    if (mode === cameraMode.value) {
        return;
    }

    cameraMode.value = mode;
    clearSelection();
    clearHover();

    if (mode === 'orbit') {
        orbitState = defaultOrbitState(orbitRange);
    }

    emit('camera-mode-change', mode);
}

defineExpose({ focusCell, resetView, setCameraMode });

/**
 * The cell the camera is currently facing — a point one cell-spacing ahead
 * along the current look direction, rounded to the nearest row/cell/flat
 * (`facedGridCoordinate`) — driving the "faced cell" detail panel, the 3D
 * equivalent of the 2D grid always showing every cell's detail at once.
 * Only updates (and only re-renders the panel) when the faced cell actually
 * changes, not on every animation frame.
 */
const facedItem = ref<FacedItem | null>(null);
let lastFacedKey: string | null = null;

/** The row/cell/flat the camera is currently looking at (`facedGridCoordinate`), keyed the same way `cellLookup` is — shared by `updateFacedItem` (per-frame, walk mode) and the Enter-to-select handler (`onKeyDown`, walk mode). */
function currentFacedCoordinate() {
    return facedGridCoordinate(
        position,
        pitchDegrees,
        VIEW_DISTANCE,
        yawDegrees,
    );
}

function updateFacedItem(): void {
    const faced = currentFacedCoordinate();
    const key = facedKey(faced.rowIndex, faced.cellNumber, faced.flatNumber);

    if (key === lastFacedKey) {
        return;
    }

    lastFacedKey = key;
    facedItem.value = cellLookup.get(key) ?? null;
}

/**
 * The row/cell/flat the camera is currently *standing* at (not "facing" —
 * unlike `facedItem`, this always has a value, even over an aisle gap with
 * no cell), so walk mode always shows an orientation anchor even far from
 * the nearest box. Rounded straight from `position`, not the look-ahead
 * probe `facedGridCoordinate` uses. Only refreshed (and only re-rendered)
 * when the rounded grid coordinate actually changes.
 */
const currentLocationLabel = ref('');
let lastLocationKey: string | null = null;

function updateCurrentLocation(): void {
    const rowIndex = nearestRowIndex(position.x);
    const cellNumber = nearestCellNumber(position.z);
    const flatNumber = nearestFlatNumber(position.y);
    const key = facedKey(rowIndex, cellNumber, flatNumber);

    if (key === lastLocationKey) {
        return;
    }

    lastLocationKey = key;
    const band = props.bands[rowIndex];
    currentLocationLabel.value = band
        ? formatSlot(band.letter, cellNumber, flatNumber)
        : '';
}

/** Refreshes the mini-map's reactive position/heading mirror, skipping small deltas (see MINI_MAP_UPDATE_EPSILON). */
function updateMiniMap(): void {
    if (
        Math.abs(position.x - miniMap.x) < MINI_MAP_UPDATE_EPSILON &&
        Math.abs(position.z - miniMap.z) < MINI_MAP_UPDATE_EPSILON &&
        Math.abs(yawDegrees - miniMap.yawDegrees) <
            MINI_MAP_UPDATE_EPSILON_DEGREES
    ) {
        return;
    }

    miniMap.x = position.x;
    miniMap.z = position.z;
    miniMap.yawDegrees = yawDegrees;
}

/**
 * The click-selected cell. In orbit mode it's the only source of detail
 * (orbiting far above the warehouse has no meaningful "camera is facing this
 * cell" probe); in walk mode it takes priority over the continuously-tracked
 * `facedItem` once set, letting you click a box to pin its detail while you
 * keep walking/looking around, without needing to stand still facing it.
 * Only ever set by clicking a cell box (`selectAtScreenPoint`); nothing
 * updates it every frame.
 */
const selectedItem = ref<FacedItem | null>(null);
let selectedKey: string | null = null;
let selectionOutline: THREE.LineSegments | null = null;

function ensureSelectionOutline(): THREE.LineSegments {
    if (selectionOutline) {
        return selectionOutline;
    }

    const geometry = new THREE.EdgesGeometry(
        new THREE.BoxGeometry(
            BOX_SIZE * SELECT_OUTLINE_SCALE,
            BOX_SIZE * SELECT_OUTLINE_SCALE,
            BOX_SIZE * SELECT_OUTLINE_SCALE,
        ),
    );
    const material = new THREE.LineBasicMaterial({ color: SELECT_COLOR });
    sceneDisposables.push(geometry, material);

    selectionOutline = new THREE.LineSegments(geometry, material);
    selectionOutline.name = 'selection-outline';
    selectionOutline.visible = false;
    scene?.add(selectionOutline);

    return selectionOutline;
}

function selectCell(
    rowIndex: number,
    cellNumber: number,
    flatNumber: number,
): void {
    const key = facedKey(rowIndex, cellNumber, flatNumber);
    selectedKey = key;
    selectedItem.value = cellLookup.get(key) ?? null;

    const outline = ensureSelectionOutline();
    outline.position.set(
        rowWorldX(rowIndex),
        flatWorldY(flatNumber),
        cellWorldZ(cellNumber),
    );
    outline.visible = true;
}

function clearSelection(): void {
    selectedKey = null;
    selectedItem.value = null;

    if (selectionOutline) {
        selectionOutline.visible = false;
    }
}

/**
 * Raycasts from a screen point through the cell boxes, returning the
 * row/cell/flat of whichever instance is hit (or null on a miss) — shared by
 * click-to-select (`selectAtScreenPoint`) and orbit-mode hover
 * (`updateHoverAtScreenPoint`). Raycasts against `cellInstanceMeshes`, the
 * per-state `THREE.InstancedMesh`es (one per state, not one per cell) cached
 * alongside `instancesByState`, then resolves the hit's `instanceId` back to
 * a cell key via that state's `keys` array — this runs on every orbit-mode
 * pointermove, so avoiding both a fresh scene-graph scan AND a fresh mesh
 * array per call matters at warehouse scale.
 */
function cellBoxAtScreenPoint(
    clientX: number,
    clientY: number,
): FacedGridCoordinate | null {
    const container = containerRef.value;

    if (!camera || !cellGroup || !container) {
        return null;
    }

    const rect = container.getBoundingClientRect();

    if (rect.width === 0 || rect.height === 0) {
        return null;
    }

    pointerNdc.x = ((clientX - rect.left) / rect.width) * 2 - 1;
    pointerNdc.y = -(((clientY - rect.top) / rect.height) * 2 - 1);

    raycaster.setFromCamera(pointerNdc, camera);
    const intersections = raycaster.intersectObjects(cellInstanceMeshes);

    if (intersections.length === 0) {
        return null;
    }

    const hit = intersections[0];

    for (const entry of instancesByState.values()) {
        if (entry.mesh !== hit.object || hit.instanceId == null) {
            continue;
        }

        const key = entry.keys[hit.instanceId];

        if (!key) {
            return null;
        }

        const [rowIndex, cellNumber, flatNumber] = key.split(':').map(Number);

        return { rowIndex, cellNumber, flatNumber };
    }

    return null;
}

/**
 * A click/tap that didn't drag selects whichever cell box is under it, or
 * clears the selection on a miss.
 */
function selectAtScreenPoint(clientX: number, clientY: number): void {
    const hit = cellBoxAtScreenPoint(clientX, clientY);

    if (!hit) {
        clearSelection();

        return;
    }

    selectCell(hit.rowIndex, hit.cellNumber, hit.flatNumber);
}

/**
 * The keyboard equivalent of click-to-select for orbit mode, where there's
 * no "facing" concept (see `onKeyDown`) and hover-outline feedback only ever
 * reaches keyboard-only users via a pointer. Raycasts through whatever's
 * centered in the viewport instead — the screen-center point a user would
 * naturally orbit a cell into before selecting it.
 */
function selectCenteredCell(): void {
    const container = containerRef.value;

    if (!container) {
        return;
    }

    const rect = container.getBoundingClientRect();
    selectAtScreenPoint(rect.left + rect.width / 2, rect.top + rect.height / 2);
}

function ensureHoverOutline(): THREE.LineSegments {
    if (hoverOutline) {
        return hoverOutline;
    }

    const material = new THREE.LineBasicMaterial({ color: HOVER_COLOR });
    sceneDisposables.push(material);

    hoverOutline = new THREE.LineSegments(getEdgesGeometry(), material);
    hoverOutline.name = 'hover-outline';
    hoverOutline.visible = false;
    scene?.add(hoverOutline);

    return hoverOutline;
}

function clearHover(): void {
    hoveredKey = null;
    isHoveringSelectable.value = false;

    if (hoverOutline) {
        hoverOutline.visible = false;
    }
}

/**
 * Refreshes/invalidates the faced-cell panel, location indicator,
 * click-selection, and orbit hover after `cellLookup` changes — shared by
 * `rebuildCellLookup` (full rebuild) and `updateCellStatesAndLookup`
 * (in-place update) so the two paths can't drift out of sync on the next
 * edit.
 */
function invalidateAfterCellDataChange(): void {
    // Force the next frame to refresh the faced-cell panel even if the
    // camera hasn't moved — the underlying item (highlight/pulse/pallet)
    // may have changed even when its grid coordinate didn't.
    lastFacedKey = null;

    // The row/rowIndex mapping (band order) can shift even when the camera
    // hasn't moved, so force the location indicator to relabel too.
    lastLocationKey = null;

    // The click-selected cell (orbit mode) can't rely on a per-frame probe
    // to refresh it, so it's refreshed/invalidated here explicitly whenever
    // the underlying data changes.
    if (selectedKey !== null) {
        selectedItem.value = cellLookup.get(selectedKey) ?? null;

        if (!selectedItem.value) {
            clearSelection();
        }
    }

    // Same for orbit-mode hover: it's only ever refreshed by a pointermove,
    // so a hovered cell removed by a data change would otherwise leave a
    // stale outline pointing at a now-empty spot until the pointer moves
    // again.
    if (hoveredKey !== null && !cellLookup.has(hoveredKey)) {
        clearHover();
    }
}

/**
 * Orbit-mode-only hover feedback: raycasts under the pointer on every move
 * (not just on click) so a box is visibly outlined before you commit to
 * selecting it — mirrors `selectAtScreenPoint`'s hit test but shows/clears an
 * outline instead of selecting.
 */
function updateHoverAtScreenPoint(clientX: number, clientY: number): void {
    const hit = cellBoxAtScreenPoint(clientX, clientY);

    if (!hit) {
        clearHover();

        return;
    }

    const { rowIndex, cellNumber, flatNumber } = hit;
    const key = facedKey(rowIndex, cellNumber, flatNumber);

    if (key === hoveredKey) {
        return;
    }

    hoveredKey = key;
    isHoveringSelectable.value = true;
    const outline = ensureHoverOutline();
    outline.position.set(
        rowWorldX(rowIndex),
        flatWorldY(flatNumber),
        cellWorldZ(cellNumber),
    );
    outline.visible = true;
}

const displayedItem = computed(() => {
    if (cameraMode.value === 'orbit') {
        return selectedItem.value;
    }

    // A click-selected cell pins the panel even as facedItem keeps tracking
    // whatever the camera is currently looking at in the background.
    return selectedItem.value ?? facedItem.value;
});

const walkHint = computed(() => {
    if (cameraMode.value === 'orbit') {
        return t('cells.map.orbitHint');
    }

    return isTouchDevice.value
        ? t('cells.map.walkHintTouch')
        : t('cells.map.walkHint');
});

const displayedLabel = computed(() =>
    displayedItem.value
        ? formatSlot(
              displayedItem.value.rowLetter,
              displayedItem.value.item.cellNumber,
              displayedItem.value.item.flatNumber,
          )
        : '',
);

/** Mini-map position/heading (walk mode only — see the `miniMap` reactive mirror). */
const miniMapPlayerPosition = computed(() => miniMapPosition(miniMap, bounds));
const miniMapHeading = computed(() =>
    miniMapHeadingDegrees(miniMap.yawDegrees),
);
const miniMapRowPositions = computed(() =>
    miniMapRowLeftPercents(props.bands.length, bounds),
);
/** Which row's mini-map line to highlight as "the row you're in". */
const miniMapActiveRowIndex = computed(() => nearestRowIndex(miniMap.x));
/**
 * Which rows currently contain at least one active highlight/filter match
 * (`item.highlighted`, the same flag driving the 3D box outlines — see
 * `outlineKindFor`) — lets the mini-map flag "a match is somewhere in this
 * row" at a glance, not just which row you're currently standing in.
 */
const miniMapRowHasMatch = computed(() =>
    props.bands.map((band) => band.items.some((item) => item.highlighted)),
);

/** A short "{label}: {state} — {product}" summary shared by the visual panel's label and the aria-live announcement below. */
function describeItem(display: FacedItem): string {
    const label = formatSlot(
        display.rowLetter,
        display.item.cellNumber,
        display.item.flatNumber,
    );
    const state = cellStateLabel(display.item.state);
    const pallet = display.item.pallet;
    const product = pallet
        ? ` — ${productName(pallet.product_name, pallet.product_ar_name)}`
        : '';

    return `${label}: ${state}${product}`;
}

/**
 * A visually-hidden `aria-live` announcement of what the visual faced-cell
 * panel/location-indicator already show sighted users, since those are
 * purely visual (`pointer-events-none`, conditionally rendered) and give
 * screen-reader users no equivalent feedback while walking/orbiting.
 */
const mapAnnouncement = computed(() => {
    if (cameraMode.value === 'orbit') {
        return selectedItem.value
            ? t('cells.map.announcements.selected', {
                  detail: describeItem(selectedItem.value),
              })
            : '';
    }

    const standing = t('cells.map.standingNear', {
        location: currentLocationLabel.value,
    });

    if (!displayedItem.value) {
        return standing;
    }

    return `${standing} ${t('cells.map.announcements.facing', {
        detail: describeItem(displayedItem.value),
    })}`;
});

/**
 * Adapts the displayed item's pallet-sample shape into the full `Cell` shape
 * CellSlot.vue expects — using the item's real `cellId`/`pallet.id`/
 * `pallet.remaining_boxes` (not placeholders) so the faced-cell panel's
 * manage-pallet/toggle-active buttons act on the real cell, not a synthetic
 * one.
 */
const displayedCellForSlot = computed<Cell | null>(() => {
    const displayed = displayedItem.value;

    if (!displayed) {
        return null;
    }

    const { item } = displayed;

    return {
        id: item.cellId,
        cell_number: item.cellNumber,
        flat_number: item.flatNumber,
        state: item.state,
        is_active: item.isActive,
        pallet: item.pallet
            ? {
                  id: item.pallet.id,
                  product_id: item.pallet.product_id,
                  product_name: item.pallet.product_name,
                  product_ar_name: item.pallet.product_ar_name,
                  product_image_url: item.pallet.product_image_url,
                  expiration_date: item.pallet.expiration_date,
                  added_at: item.pallet.added_at,
                  is_stale: null,
                  remaining_boxes: item.pallet.remaining_boxes,
              }
            : null,
    };
});

function updateCamera(): void {
    if (!camera) {
        return;
    }

    if (cameraMode.value === 'orbit') {
        const orbitPosition = orbitCameraPosition(orbitRange, orbitState);
        camera.position.set(orbitPosition.x, orbitPosition.y, orbitPosition.z);
        camera.lookAt(
            orbitRange.center.x,
            orbitRange.center.y,
            orbitRange.center.z,
        );

        return;
    }

    camera.position.set(position.x, position.y, position.z);

    const direction = lookDirection(pitchDegrees, yawDegrees);
    camera.lookAt(
        position.x + direction.x,
        position.y + direction.y,
        position.z + direction.z,
    );
}

function animate(timeMs: number): void {
    if (!renderer || !scene || !camera) {
        return;
    }

    const deltaSeconds =
        lastFrameTime === null ? 0 : (timeMs - lastFrameTime) / 1000;
    lastFrameTime = timeMs;

    if (cameraMode.value === 'walk' && pressedDirections.size > 0) {
        const speed =
            isSprinting || touchSprintEnabled.value
                ? WALK_SPEED * SPRINT_MULTIPLIER
                : WALK_SPEED;
        const next = stepPosition(
            position,
            pressedDirections,
            deltaSeconds,
            bounds,
            speed,
            instanceSlotByKey,
        );
        position.x = next.x;
        position.y = next.y;
        position.z = next.z;
    } else if (cameraMode.value === 'orbit' && pressedDirections.size > 0) {
        orbitState = stepOrbitStateByKeys(
            orbitState,
            pressedDirections,
            deltaSeconds,
            orbitRange,
        );
    }

    updateCamera();

    if (cameraMode.value === 'walk') {
        updateFacedItem();
        updateCurrentLocation();
        updateMiniMap();
        updateRowLabels();
    }

    renderer.render(scene, camera);
    animationFrameId = requestAnimationFrame(animate);
}

/**
 * WASD/arrows and Space/Shift drive both camera modes now — `stepPosition`
 * (walk) and `stepOrbitStateByKeys` (orbit) both read the same
 * `pressedDirections` set from `animate()`, so key handling itself no longer
 * branches on `cameraMode`. Enter selects the currently-faced cell in walk
 * mode (the keyboard equivalent of click-to-select, which otherwise
 * requires a pointer) — orbit has no "facing" concept, so there Enter
 * instead selects whatever's centered in the viewport (`selectCenteredCell`),
 * giving keyboard-only users a way to select in orbit mode too, since
 * hover-outline feedback there otherwise only ever reaches a pointer.
 */
function onKeyDown(event: KeyboardEvent): void {
    if (event.key === 'Control') {
        isSprinting = true;
    }

    if (event.key.toLowerCase() === 'o') {
        event.preventDefault();
        setCameraMode(cameraMode.value === 'walk' ? 'orbit' : 'walk');

        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();

        if (cameraMode.value === 'orbit') {
            selectCenteredCell();

            return;
        }

        const faced = currentFacedCoordinate();
        const key = facedKey(
            faced.rowIndex,
            faced.cellNumber,
            faced.flatNumber,
        );

        if (cellLookup.has(key)) {
            selectCell(faced.rowIndex, faced.cellNumber, faced.flatNumber);
        } else {
            clearSelection();
        }

        return;
    }

    const direction = moveDirectionForKey(event.key);

    if (direction) {
        event.preventDefault();
        pressedDirections.add(direction);
    }
}

function onKeyUp(event: KeyboardEvent): void {
    if (event.key === 'Control') {
        isSprinting = false;
    }

    const direction = moveDirectionForKey(event.key);

    if (direction) {
        pressedDirections.delete(direction);
    }
}

function onFocusLost(): void {
    pressedDirections.clear();
    isSprinting = false;
    isDragging = false;
    activePointers.clear();
    pinchStartGap = null;
}

function onPointerDown(event: PointerEvent): void {
    clearHover();
    activePointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
    containerRef.value?.setPointerCapture?.(event.pointerId);

    if (activePointers.size === 2 && cameraMode.value === 'orbit') {
        isDragging = false;
        const [a, b] = [...activePointers.values()];
        pinchStartGap = distanceBetween(a, b);
        orbitDistanceAtPinchStart = orbitState.distance;

        return;
    }

    isDragging = true;
    dragMoved = false;
    dragStartX = event.clientX;
    dragStartY = event.clientY;
    lastPointerX = event.clientX;
    lastPointerY = event.clientY;
}

function onPointerMove(event: PointerEvent): void {
    if (activePointers.has(event.pointerId)) {
        activePointers.set(event.pointerId, {
            x: event.clientX,
            y: event.clientY,
        });
    }

    if (
        activePointers.size === 2 &&
        pinchStartGap !== null &&
        cameraMode.value === 'orbit'
    ) {
        const [a, b] = [...activePointers.values()];
        orbitState.distance = orbitDistanceFromPinch(
            orbitDistanceAtPinchStart,
            pinchStartGap,
            distanceBetween(a, b),
            orbitRange,
        );

        return;
    }

    if (!isDragging) {
        if (cameraMode.value === 'orbit') {
            updateHoverAtScreenPoint(event.clientX, event.clientY);
        }

        return;
    }

    const deltaX = event.clientX - lastPointerX;
    const deltaY = event.clientY - lastPointerY;
    lastPointerX = event.clientX;
    lastPointerY = event.clientY;

    if (
        Math.abs(event.clientX - dragStartX) > CLICK_MOVE_THRESHOLD_PIXELS ||
        Math.abs(event.clientY - dragStartY) > CLICK_MOVE_THRESHOLD_PIXELS
    ) {
        dragMoved = true;
    }

    if (cameraMode.value === 'orbit') {
        orbitState.yawDegrees = stepYaw(orbitState.yawDegrees, deltaX);
        orbitState.pitchDegrees = stepOrbitPitch(
            orbitState.pitchDegrees,
            deltaY,
        );

        return;
    }

    pitchDegrees = stepPitch(pitchDegrees, deltaY);
    yawDegrees = stepYaw(yawDegrees, deltaX);
}

function onPointerUpOrCancel(event: PointerEvent): void {
    // Click-to-select works in both camera modes — pinchStartGap is only
    // ever set in orbit mode (walk mode has no pinch-zoom), so this can't
    // misfire mid-pinch there.
    const wasSinglePointerClick =
        activePointers.size === 1 && pinchStartGap === null && !dragMoved;

    activePointers.delete(event.pointerId);

    if (activePointers.size < 2) {
        pinchStartGap = null;
    }

    if (containerRef.value?.hasPointerCapture?.(event.pointerId)) {
        containerRef.value.releasePointerCapture(event.pointerId);
    }

    if (wasSinglePointerClick) {
        selectAtScreenPoint(event.clientX, event.clientY);
    }

    clearHover();
    isDragging = false;
}

/** Orbit-only zoom for desktop mouse wheel — pinch (above) covers touch. */
function onWheel(event: WheelEvent): void {
    if (cameraMode.value !== 'orbit') {
        return;
    }

    event.preventDefault();
    orbitState.distance = stepOrbitDistance(
        orbitState.distance,
        event.deltaY,
        orbitRange,
    );
}

/**
 * Press-and-hold handlers for the touch move/fly buttons — same
 * `pressedDirections` set that `onKeyDown`/`onKeyUp` drive, so holding a
 * button moves the camera exactly like holding the matching key. Pointer
 * capture keeps the "up" event firing on this same button even if the
 * finger drifts off it while held.
 */
function onControlPointerDown(
    direction: MoveDirection,
    event: PointerEvent,
): void {
    pressedDirections.add(direction);
    (event.currentTarget as HTMLElement | null)?.setPointerCapture?.(
        event.pointerId,
    );
}

function onControlPointerUp(
    direction: MoveDirection,
    event: PointerEvent,
): void {
    pressedDirections.delete(direction);

    const target = event.currentTarget as HTMLElement | null;

    if (target?.hasPointerCapture?.(event.pointerId)) {
        target.releasePointerCapture(event.pointerId);
    }
}

/** Touch has no "hold Ctrl" gesture, so sprint is a persistent tap-to-toggle there instead of a held modifier. */
function toggleTouchSprint(): void {
    touchSprintEnabled.value = !touchSprintEnabled.value;
}

function onResize(): void {
    const container = containerRef.value;

    if (!container || !camera || !renderer) {
        return;
    }

    const { clientWidth, clientHeight } = container;

    if (clientWidth === 0 || clientHeight === 0) {
        return;
    }

    camera.aspect = clientWidth / clientHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(clientWidth, clientHeight);
}

function setupScene(container: HTMLElement): void {
    scene = new THREE.Scene();
    scene.background = new THREE.Color(SKY_COLOR);

    const { clientWidth, clientHeight } = container;
    camera = new THREE.PerspectiveCamera(
        CAMERA_FOV_DEGREES,
        clientWidth / (clientHeight || 1),
        0.1,
        500,
    );

    renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setSize(clientWidth, clientHeight);
    renderer.domElement.style.display = 'block';
    renderer.domElement.style.width = '100%';
    renderer.domElement.style.height = '100%';
    container.appendChild(renderer.domElement);

    scene.add(new THREE.AmbientLight(0xffffff, 0.6));

    const directionalLight = new THREE.DirectionalLight(0xffffff, 0.8);
    directionalLight.position.set(10, 20, 10);
    scene.add(directionalLight);

    const floorGeometry = new THREE.PlaneGeometry(300, 300);
    const floorMaterial = new THREE.MeshStandardMaterial({ color: 0xf3f4f6 });
    const floor = new THREE.Mesh(floorGeometry, floorMaterial);
    floor.rotation.x = -Math.PI / 2;
    floor.position.y = -0.01;
    scene.add(floor);
    sceneDisposables.push(floorGeometry, floorMaterial);

    rebuildCells();
    resetView();
    updateCurrentLocation();
}

watch(() => props.bands, rebuildCells);

onMounted(() => {
    isTouchDevice.value =
        typeof window.matchMedia === 'function' &&
        window.matchMedia('(pointer: coarse)').matches;

    const container = containerRef.value;

    if (!container) {
        return;
    }

    setupScene(container);

    resizeObserver = new ResizeObserver(onResize);
    resizeObserver.observe(container);

    animationFrameId = requestAnimationFrame(animate);
    container.focus();
});

onBeforeUnmount(() => {
    if (animationFrameId !== null) {
        cancelAnimationFrame(animationFrameId);
    }

    resizeObserver?.disconnect();
    disposeCellGroup();
    sceneDisposables.forEach((disposable) => disposable.dispose());
    renderer?.dispose();

    if (renderer && containerRef.value?.contains(renderer.domElement)) {
        containerRef.value.removeChild(renderer.domElement);
    }
});
</script>

<template>
    <div
        ref="containerRef"
        tabindex="0"
        role="application"
        :aria-label="walkHint"
        data-testid="map-3d-viewport"
        class="relative h-full w-full touch-none outline-none select-none"
        :class="{
            'cursor-pointer': cameraMode === 'orbit' && isHoveringSelectable,
        }"
        @keydown="onKeyDown"
        @keyup="onKeyUp"
        @blur="onFocusLost"
        @pointerdown="onPointerDown"
        @pointermove="onPointerMove"
        @pointerup="onPointerUpOrCancel"
        @pointercancel="onPointerUpOrCancel"
        @pointerleave="onPointerUpOrCancel"
        @wheel="onWheel"
    >
        <div
            class="pointer-events-none absolute start-2 top-2 z-10 flex flex-col gap-2"
        >
            <div
                data-testid="map-3d-state-legend"
                class="flex flex-col gap-1 rounded-md border border-gray-200 bg-white px-2 py-1.5 text-xs text-gray-700 shadow-sm dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-200"
            >
                <div
                    v-for="state in CELL_STATES"
                    :key="state"
                    class="flex items-center gap-1.5"
                >
                    <span
                        class="h-2.5 w-2.5 shrink-0 rounded-sm"
                        :style="{ backgroundColor: stateHexColor(state) }"
                    ></span>
                    <component
                        :is="CELL_STATE_COLOR[state].icon"
                        class="h-3 w-3 shrink-0"
                    />
                    <span>{{ cellStateLabel(state) }}</span>
                </div>
            </div>

            <div
                v-if="cameraMode === 'walk'"
                data-testid="map-3d-mini-map"
                aria-hidden="true"
                class="relative h-24 w-24 shrink-0 overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900"
            >
                <svg
                    class="absolute inset-0 h-full w-full"
                    viewBox="0 0 100 100"
                    preserveAspectRatio="none"
                >
                    <line
                        v-for="(leftPercent, rowIndex) in miniMapRowPositions"
                        :key="rowIndex"
                        :x1="leftPercent"
                        y1="0"
                        :x2="leftPercent"
                        y2="100"
                        vector-effect="non-scaling-stroke"
                        stroke-width="2"
                        :class="
                            rowIndex === miniMapActiveRowIndex
                                ? 'stroke-blue-500 dark:stroke-blue-400'
                                : miniMapRowHasMatch[rowIndex]
                                  ? 'stroke-emerald-500 dark:stroke-emerald-400'
                                  : 'stroke-gray-500 dark:stroke-neutral-400'
                        "
                    />
                </svg>
                <Navigation
                    data-testid="map-3d-mini-map-marker"
                    class="absolute h-4 w-4 text-blue-600 dark:text-blue-400"
                    :style="{
                        left: `${miniMapPlayerPosition.leftPercent}%`,
                        top: `${miniMapPlayerPosition.topPercent}%`,
                        transform: `translate(-50%, -50%) rotate(${miniMapHeading}deg)`,
                    }"
                />
            </div>
        </div>

        <div
            v-if="cameraMode === 'walk'"
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 z-10 overflow-hidden"
        >
            <div
                v-for="label in visibleRowLabels"
                :key="label.letter"
                data-testid="map-3d-row-label"
                class="absolute -translate-x-1/2 -translate-y-1/2 rounded-md border border-gray-200 bg-white px-2 py-1 text-xs font-semibold text-gray-700 shadow-sm dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-200"
                :style="{
                    left: `${label.leftPercent}%`,
                    top: `${label.topPercent}%`,
                }"
            >
                {{ label.letter }}
            </div>
        </div>

        <div
            class="sr-only"
            role="status"
            aria-live="polite"
            data-testid="map-3d-announcement"
        >
            {{ mapAnnouncement }}
        </div>

        <div
            v-if="cameraMode === 'walk'"
            data-testid="map-3d-location-indicator"
            class="pointer-events-none absolute end-2 top-2 z-10 rounded-md border border-gray-200 bg-white px-2 py-1.5 text-xs font-medium text-gray-700 shadow-sm dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-200"
        >
            {{
                t('cells.map.standingNear', { location: currentLocationLabel })
            }}
        </div>

        <div
            v-if="displayedItem"
            data-testid="map-3d-faced-cell"
            class="pointer-events-none absolute inset-x-0 top-2 z-10 flex justify-center"
        >
            <CellSlot
                :cell="displayedCellForSlot"
                :label="displayedLabel"
                :highlighted="displayedItem.item.highlighted"
                :dimmed="displayedItem.item.dimmed"
                :pulsing="displayedItem.item.pulsing"
                toggleable
                manageable
                class="pointer-events-auto shadow-lg"
                @pointerdown.stop
                @toggle-active="emit('toggle-active', $event, displayedLabel)"
                @manage-pallet="emit('manage-pallet', $event, displayedLabel)"
            />
        </div>

        <div
            v-if="cameraMode === 'orbit'"
            class="pointer-events-none absolute inset-x-0 bottom-2 z-10 flex justify-center px-2"
        >
            <div
                data-testid="map-3d-orbit-legend"
                class="flex flex-col items-center gap-1 rounded-md border border-gray-200 bg-white px-3 py-2 text-center text-xs text-gray-700 shadow-sm dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-200"
            >
                <p>{{ t('cells.map.controls.orbit.rotateLabel') }}</p>
                <p>{{ t('cells.map.controls.orbit.zoomLabel') }}</p>
                <p>{{ t('cells.map.controls.orbit.keyboardLabel') }}</p>
                <p>{{ t('cells.map.controls.orbit.selectLabel') }}</p>
                <p class="flex items-center gap-1.5">
                    <span>{{
                        t('cells.map.controls.cameraModeToggleLabel')
                    }}</span>
                    <kbd :class="kbdClass">O</kbd>
                </p>
            </div>
        </div>

        <div
            v-else-if="isTouchDevice"
            class="pointer-events-none absolute inset-x-0 bottom-2 z-10 flex items-end justify-between px-3"
        >
            <div
                data-testid="map-3d-move-pad"
                class="pointer-events-auto grid grid-cols-3 grid-rows-2 gap-1 rounded-md border border-gray-200 bg-white p-2 shadow-sm dark:border-neutral-800 dark:bg-neutral-900"
            >
                <span></span>
                <button
                    type="button"
                    tabindex="-1"
                    :class="mapToolbarButtonClass"
                    :aria-label="t('cells.map.controls.touch.forward')"
                    @pointerdown.stop.prevent="
                        onControlPointerDown('forward', $event)
                    "
                    @pointerup.stop="onControlPointerUp('forward', $event)"
                    @pointercancel.stop="onControlPointerUp('forward', $event)"
                >
                    <ChevronUp class="h-4 w-4" />
                </button>
                <span></span>
                <button
                    type="button"
                    tabindex="-1"
                    :class="mapToolbarButtonClass"
                    :aria-label="t('cells.map.controls.touch.left')"
                    @pointerdown.stop.prevent="
                        onControlPointerDown('left', $event)
                    "
                    @pointerup.stop="onControlPointerUp('left', $event)"
                    @pointercancel.stop="onControlPointerUp('left', $event)"
                >
                    <ChevronLeft class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    tabindex="-1"
                    :class="mapToolbarButtonClass"
                    :aria-label="t('cells.map.controls.touch.backward')"
                    @pointerdown.stop.prevent="
                        onControlPointerDown('backward', $event)
                    "
                    @pointerup.stop="onControlPointerUp('backward', $event)"
                    @pointercancel.stop="onControlPointerUp('backward', $event)"
                >
                    <ChevronDown class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    tabindex="-1"
                    :class="mapToolbarButtonClass"
                    :aria-label="t('cells.map.controls.touch.right')"
                    @pointerdown.stop.prevent="
                        onControlPointerDown('right', $event)
                    "
                    @pointerup.stop="onControlPointerUp('right', $event)"
                    @pointercancel.stop="onControlPointerUp('right', $event)"
                >
                    <ChevronRight class="h-4 w-4" />
                </button>
            </div>

            <div
                data-testid="map-3d-fly-pad"
                class="pointer-events-auto flex flex-col gap-1 rounded-md border border-gray-200 bg-white p-2 shadow-sm dark:border-neutral-800 dark:bg-neutral-900"
            >
                <button
                    type="button"
                    tabindex="-1"
                    :class="mapToolbarButtonClass"
                    :aria-label="t('cells.map.controls.touch.up')"
                    @pointerdown.stop.prevent="
                        onControlPointerDown('up', $event)
                    "
                    @pointerup.stop="onControlPointerUp('up', $event)"
                    @pointercancel.stop="onControlPointerUp('up', $event)"
                >
                    <ArrowUp class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    tabindex="-1"
                    :class="mapToolbarButtonClass"
                    :aria-label="t('cells.map.controls.touch.down')"
                    @pointerdown.stop.prevent="
                        onControlPointerDown('down', $event)
                    "
                    @pointerup.stop="onControlPointerUp('down', $event)"
                    @pointercancel.stop="onControlPointerUp('down', $event)"
                >
                    <ArrowDown class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    tabindex="-1"
                    data-testid="touch-sprint-toggle"
                    :aria-pressed="touchSprintEnabled"
                    :aria-label="t('cells.map.controls.touch.sprint')"
                    :class="[
                        mapToolbarButtonClass,
                        touchSprintEnabled ? selectedToggleClass : '',
                    ]"
                    @click="toggleTouchSprint"
                >
                    <Zap class="h-4 w-4" />
                </button>
            </div>
        </div>
        <div
            v-else
            class="pointer-events-none absolute inset-x-0 bottom-2 z-10 flex justify-center px-2"
        >
            <div
                data-testid="map-3d-controls-legend"
                class="flex flex-col items-center gap-1 rounded-md border border-gray-200 bg-white px-3 py-2 text-center text-xs text-gray-700 shadow-sm dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-200"
            >
                <p>{{ t('cells.map.controls.moveLabel') }}</p>
                <p class="flex items-center gap-1.5">
                    <span>{{ t('cells.map.controls.flyLabel') }}</span>
                    <kbd :class="kbdClass">Space</kbd>
                    <span>{{ t('cells.map.controls.up') }}</span>
                    <kbd :class="kbdClass">Shift</kbd>
                    <span>{{ t('cells.map.controls.down') }}</span>
                </p>
                <p class="flex items-center gap-1.5">
                    <span>{{ t('cells.map.controls.sprintLabel') }}</span>
                    <kbd :class="kbdClass">Ctrl</kbd>
                </p>
                <p>{{ t('cells.map.controls.lookLabel') }}</p>
                <p>{{ t('cells.map.controls.selectLabel') }}</p>
                <p class="flex items-center gap-1.5">
                    <span>{{
                        t('cells.map.controls.cameraModeToggleLabel')
                    }}</span>
                    <kbd :class="kbdClass">O</kbd>
                </p>
            </div>
        </div>
    </div>
</template>

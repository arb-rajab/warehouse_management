<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronUp,
} from '@lucide/vue';
import * as THREE from 'three';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import CellSlot from '@/components/CellSlot.vue';
import { CELL_STATE_COLOR } from '@/lib/cellStateColor';
import { mapToolbarButtonClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { formatSlot } from '@/lib/location';
import {
    boundsForWarehouse,
    cellWorldZ,
    clamp,
    defaultOrbitState,
    EYE_HEIGHT,
    facedGridCoordinate,
    flatWorldY,
    lookDirection,
    maxOf,
    minOf,
    moveDirectionForKey,
    orbitCameraPosition,
    orbitDistanceFromPinch,
    orbitRangeForBounds,
    pitchToLookAt,
    rowWorldX,
    stepOrbitDistance,
    stepOrbitPitch,
    stepOrbitStateByKeys,
    stepPitch,
    stepPosition,
    stepYaw,
} from '@/lib/mapWalker';
import type {
    MoveDirection,
    OrbitRange,
    OrbitState,
    WalkerBounds,
} from '@/lib/mapWalker';
import type { Cell, CellMap3DBand, CellMap3DItem } from '@/types/admin';

const props = defineProps<{
    bands: CellMap3DBand[];
}>();

const emit = defineEmits<{
    'camera-mode-change': [mode: 'walk' | 'orbit'];
}>();

const containerRef = ref<HTMLElement | null>(null);

/** Touch devices get on-screen move/fly buttons instead of WASD/Space/Shift. */
const isTouchDevice = ref(false);

type Disposable = { dispose: () => void };

interface FacedItem {
    rowLetter: string;
    item: CellMap3DItem;
}

/** Screen-space distance between two pointers, for orbit mode's two-finger pinch-to-zoom. */
function distanceBetween(
    a: { x: number; y: number },
    b: { x: number; y: number },
): number {
    return Math.hypot(a.x - b.x, a.y - b.y);
}

const BOX_SIZE = 1.4;
const VIEW_DISTANCE = cellWorldZ(1);
const HIGHLIGHT_COLOR = 0x3b82f6;
const PULSE_COLOR = 0x10b981;
const SKY_COLOR = 0xe5e7eb;
const SHELF_COLOR = 0x64748b;
const SHELF_THICKNESS = 0.15;
/** How far a shelf platform overhangs the outermost box it carries, on every side. */
const SHELF_MARGIN = BOX_SIZE * 0.3;
const POST_SIZE = 0.15;
/** How far a row's corner posts stand out from its boxes, in X (beside the aisle). */
const POST_OFFSET = BOX_SIZE / 2 + SHELF_MARGIN;

/** Key-cap badge for the controls legend (e.g. the `Space`/`Shift` keys). */
const kbdClass =
    'rounded border border-gray-400 bg-white px-1.5 py-0.5 font-mono text-[10px] font-medium text-gray-700 shadow-sm dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-200';

let renderer: THREE.WebGLRenderer | null = null;
let scene: THREE.Scene | null = null;
let camera: THREE.PerspectiveCamera | null = null;
let animationFrameId: number | null = null;
let resizeObserver: ResizeObserver | null = null;
let cellGroup: THREE.Group | null = null;
let cellDisposables: Disposable[] = [];
const sceneDisposables: Disposable[] = [];
let cellLookup = new Map<string, FacedItem>();

const pressedDirections = new Set<MoveDirection>();
const position = { x: 0, y: EYE_HEIGHT, z: 0 };
let pitchDegrees = 0;
let yawDegrees = 0;
let bounds: WalkerBounds = {
    minX: 0,
    maxX: 0,
    minY: 0,
    maxY: 0,
    minZ: 0,
    maxZ: 0,
};
let lastFrameTime: number | null = null;
let isDragging = false;
let lastPointerX = 0;
let lastPointerY = 0;

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

function disposeCellGroup(): void {
    if (cellGroup && scene) {
        scene.remove(cellGroup);
    }

    cellDisposables.forEach((disposable) => disposable.dispose());
    cellDisposables = [];
    cellGroup = null;
}

function buildCellGroup(bands: CellMap3DBand[]): THREE.Group {
    const group = new THREE.Group();
    const boxGeometry = new THREE.BoxGeometry(BOX_SIZE, BOX_SIZE, BOX_SIZE);
    const edgesGeometry = new THREE.EdgesGeometry(
        new THREE.BoxGeometry(
            BOX_SIZE * 1.05,
            BOX_SIZE * 1.05,
            BOX_SIZE * 1.05,
        ),
    );
    cellDisposables.push(boxGeometry, edgesGeometry);

    bands.forEach((band, rowIndex) => {
        for (const item of band.items) {
            const material = new THREE.MeshStandardMaterial({
                color: CELL_STATE_COLOR[item.state].hex,
            });
            cellDisposables.push(material);

            const mesh = new THREE.Mesh(boxGeometry, material);
            mesh.name = 'cell-box';
            mesh.position.set(
                rowWorldX(rowIndex),
                flatWorldY(item.flatNumber),
                cellWorldZ(item.cellNumber),
            );
            group.add(mesh);

            if (item.highlighted || item.pulsing) {
                const outlineMaterial = new THREE.LineBasicMaterial({
                    color: item.pulsing ? PULSE_COLOR : HIGHLIGHT_COLOR,
                });
                cellDisposables.push(outlineMaterial);

                const outline = new THREE.LineSegments(
                    edgesGeometry,
                    outlineMaterial,
                );
                outline.position.copy(mesh.position);
                group.add(outline);
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

    bounds = boundsForWarehouse(rowCount, maxCellsCount, maxFlatNumber);
    orbitRange = orbitRangeForBounds(bounds);
}

function facedKey(
    rowIndex: number,
    cellNumber: number,
    flatNumber: number,
): string {
    return `${rowIndex}:${cellNumber}:${flatNumber}`;
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

    // Force the next frame to refresh the faced-cell panel even if the
    // camera hasn't moved — the underlying item (highlight/pulse/pallet)
    // may have changed even when its grid coordinate didn't.
    lastFacedKey = null;
}

function rebuildCells(): void {
    if (!scene) {
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
 * persisting an arbitrary prior state.
 */
function setCameraMode(mode: 'walk' | 'orbit'): void {
    if (mode === cameraMode.value) {
        return;
    }

    cameraMode.value = mode;

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

function updateFacedItem(): void {
    const faced = facedGridCoordinate(
        position,
        pitchDegrees,
        VIEW_DISTANCE,
        yawDegrees,
    );
    const key = facedKey(faced.rowIndex, faced.cellNumber, faced.flatNumber);

    if (key === lastFacedKey) {
        return;
    }

    lastFacedKey = key;
    facedItem.value = cellLookup.get(key) ?? null;
}

const walkHint = computed(() => {
    if (cameraMode.value === 'orbit') {
        return t('cells.map.orbitHint');
    }

    return isTouchDevice.value
        ? t('cells.map.walkHintTouch')
        : t('cells.map.walkHint');
});

const facedLabel = computed(() =>
    facedItem.value
        ? formatSlot(
              facedItem.value.rowLetter,
              facedItem.value.item.cellNumber,
              facedItem.value.item.flatNumber,
          )
        : '',
);

/** Adapts the faced item's pallet-sample shape into the full `Cell` shape CellSlot.vue expects. */
const facedCellForSlot = computed<Cell | null>(() => {
    const faced = facedItem.value;

    if (!faced) {
        return null;
    }

    const { item } = faced;

    return {
        id: 0,
        cell_number: item.cellNumber,
        flat_number: item.flatNumber,
        state: item.state,
        pallet: item.pallet
            ? {
                  id: 0,
                  product_id: 0,
                  product_name: item.pallet.product_name,
                  product_image_url: item.pallet.product_image_url,
                  expiration_date: item.pallet.expiration_date,
                  added_at: item.pallet.added_at,
                  is_stale: null,
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
        const next = stepPosition(
            position,
            pressedDirections,
            deltaSeconds,
            bounds,
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
    }

    renderer.render(scene, camera);
    animationFrameId = requestAnimationFrame(animate);
}

/**
 * WASD/arrows and Space/Shift drive both camera modes — `stepPosition`
 * (walk) and `stepOrbitStateByKeys` (orbit) both read the same
 * `pressedDirections` set from `animate()`, so key handling itself doesn't
 * branch on `cameraMode`.
 */
function onKeyDown(event: KeyboardEvent): void {
    if (event.key.toLowerCase() === 'o') {
        event.preventDefault();
        setCameraMode(cameraMode.value === 'walk' ? 'orbit' : 'walk');

        return;
    }

    const direction = moveDirectionForKey(event.key);

    if (direction) {
        event.preventDefault();
        pressedDirections.add(direction);
    }
}

function onKeyUp(event: KeyboardEvent): void {
    const direction = moveDirectionForKey(event.key);

    if (direction) {
        pressedDirections.delete(direction);
    }
}

function onFocusLost(): void {
    pressedDirections.clear();
    isDragging = false;
    activePointers.clear();
    pinchStartGap = null;
}

function onPointerDown(event: PointerEvent): void {
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
        return;
    }

    const deltaX = event.clientX - lastPointerX;
    const deltaY = event.clientY - lastPointerY;
    lastPointerX = event.clientX;
    lastPointerY = event.clientY;

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
    activePointers.delete(event.pointerId);

    if (activePointers.size < 2) {
        pinchStartGap = null;
    }

    if (containerRef.value?.hasPointerCapture?.(event.pointerId)) {
        containerRef.value.releasePointerCapture(event.pointerId);
    }

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
        70,
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
            v-if="facedItem"
            data-testid="map-3d-faced-cell"
            class="pointer-events-none absolute inset-x-0 top-2 z-10 flex justify-center"
        >
            <CellSlot
                :cell="facedCellForSlot"
                :label="facedLabel"
                :highlighted="facedItem.item.highlighted"
                :pulsing="facedItem.item.pulsing"
                class="shadow-lg"
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
                <p>{{ t('cells.map.controls.lookLabel') }}</p>
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

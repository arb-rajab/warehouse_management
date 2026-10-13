import { View } from '../core/View';
import { columnSlots } from '../domain/normalizeRows';
import type { Locale, MatchIndex, Slot, Warehouse } from '../domain/types';
import type { Slice } from '../state/slices';
import type { PlanOrientation, RackRef } from '../state/state';
import { el, replaceChildrenOf } from '../utils/dom';
import { PlanGestures } from './PlanGestures';
import {
    centerOnPoint,
    fitPlan,
    highlightScale,
    isCoarse,
    zoomAbout,
    zoomLimits,
} from './PlanLayout';
import type { Orientation, View2D } from './PlanLayout';
import { Tile, tileModel } from './TileRenderer';

export interface PlanState {
    warehouse: Warehouse | null;
    match: MatchIndex;
    floor: number | null;
    targetId: number | null;
    orientation: PlanOrientation;
    /** The 2D view is the one showing. */
    active: boolean;
    locale: Locale;
}

export interface PlanIntents {
    openRack(ref: RackRef): void;
    setChromeAway(away: boolean): void;
    cycleOrientation(): void;
}

/** What the shortcuts and the zoom buttons can ask of the plan. */
export interface PlanControls {
    fit(animate?: boolean): void;
    zoomBy(factor: number, animate?: boolean): void;
    /** Cycles auto / vertical / horizontal. */
    rotate(): void;
    reveal(slot: Slot): void;
}

export type Translate = (
    key: string,
    params?: Record<string, string | number>,
) => string;

interface Pair {
    signs: Array<{ element: HTMLElement; aisle: number }>;
}

const ANIMATION_MS = 480;
/** How long after the last zoom step the tiles keep their light look before the full detail is painted again. */
const ZOOM_SETTLE_MS = 160;

/**
 * The 2D floor plan: a top-down view of the same warehouse as the 3D scene,
 * entrance at the bottom, aisle pairs side by side with A on the left, one
 * strip of tiles per rack row (column 1 at the entrance end). It decides its
 * own orientation to fit the screen.
 */
export class PlanView extends View implements PlanControls {
    private readonly stage: HTMLElement;
    private readonly world: HTMLElement;
    private view: View2D = { x: 0, y: 0, k: 1 };
    private fitZoom = 1;
    private fitted = false;
    private animationTimer: ReturnType<typeof setTimeout> | undefined;
    private zoomTimer: ReturnType<typeof setTimeout> | undefined;
    private highlight = 1.2;

    private gestures!: PlanGestures;
    private tiles = new Map<string, Tile>();
    private heads = new Map<
        string,
        {
            head: HTMLElement;
            rack: HTMLElement;
            plate: HTMLElement;
            count: HTMLElement;
        }
    >();
    private pair: Pair = { signs: [] };
    private levelPill = el('div', { class: 'wms-plan__level is-hidden' });
    private entrance = el('div', { class: 'wms-plan__entrance' });
    private builtFor: Warehouse | null = null;
    private state: PlanState;

    constructor(
        readonly element: HTMLElement,
        private readonly slice: Slice<PlanState>,
        private readonly intents: PlanIntents,
        private readonly t: Translate,
    ) {
        super();
        this.world = el('div', { class: 'wms-plan__world' });
        this.stage = element;
        this.state = slice.get();
    }

    mount(): void {
        this.stage.classList.add('wms-plan');
        this.stage.append(this.world);

        this.gestures = new PlanGestures(this.stage, {
            onPan: (dx, dy) => this.pan(dx, dy),
            onZoom: (factor, cx, cy) => {
                const origin = this.stageOrigin();

                this.zoomAbout(factor, cx - origin.left, cy - origin.top);
            },
            onTapTile: (tile) => {
                this.intents.openRack({
                    row: tile.dataset.row ?? '',
                    col: Number(tile.dataset.col),
                    level: null,
                });
            },
            onFit: () => this.fit(true),
            onGrab: (grabbed) => {
                this.stage.classList.toggle('is-panning', grabbed);
                this.intents.setChromeAway(grabbed);
            },
        });
        this.gestures.mount();
        this.own(() => this.gestures.dispose());

        this.state = this.slice.get();
        this.render(this.state, null);
        this.own(
            this.slice.subscribe((next, previous) =>
                this.render(next, previous),
            ),
        );
    }

    protected onDispose(): void {
        clearTimeout(this.animationTimer);
        clearTimeout(this.zoomTimer);
        clearTimeout(this.refreshTimer);
        this.world.remove();
    }

    /* ---------- PlanControls ---------- */

    fit(animate = false): void {
        const viewport = this.viewport();
        const sizes = this.measure();

        if (!sizes) {
            return;
        }

        const fit = fitPlan(viewport, sizes.sizes, this.state.orientation);

        if (!fit) {
            return;
        }

        this.world.classList.toggle('is-horizontal', fit.orientation === 'h');
        this.fitZoom = fit.zoom;
        this.view = fit.view;
        this.fitted = true;
        this.apply(animate);
        this.refreshTiles();
    }

    zoomBy(factor: number, animate = false): void {
        const v = this.viewport();

        this.zoomAbout(factor, v.w / 2, v.h / 2, animate);
    }

    rotate(): void {
        // the orientation lives in the store; the next render refits and the hint says which one it is
        this.intents.cycleOrientation();
    }

    /** Centres the plan on a slot's column and keeps it highlighted. */
    reveal(slot: Slot): void {
        const tile = this.tiles.get(`${slot.row}|${slot.col}`)?.element;

        if (!tile) {
            return;
        }

        if (!this.fitted) {
            this.fit();
        }

        const r = tile.getBoundingClientRect();
        const w = this.world.getBoundingClientRect();
        const point = {
            x: (r.left - w.left + r.width / 2) / this.view.k,
            y: (r.top - w.top + r.height / 2) / this.view.k,
        };

        this.view = centerOnPoint(this.viewport(), point, 0.8, this.fitZoom);
        this.apply(true);
        this.scheduleRefresh(ANIMATION_MS + 20);
    }

    /** The stage changed size (window, panel layout): refit if the 2D view is showing. */
    resize(): void {
        if (this.state.active) {
            this.fit();
        } else {
            this.fitted = false;
        }
    }

    /* ---------- rendering ---------- */

    private render(next: PlanState, previous: PlanState | null): void {
        this.state = next;

        if (next.warehouse !== this.builtFor) {
            this.build(next.warehouse);
            this.fitted = false;
        }

        if (
            !previous ||
            next.locale !== previous.locale ||
            next.warehouse !== previous.warehouse
        ) {
            this.renderLabels();
        }

        if (
            next.active &&
            (!this.fitted ||
                previous?.orientation !== next.orientation ||
                previous?.active === false)
        ) {
            requestAnimationFrame(() => {
                if (!this.isDisposed && this.state.active) {
                    this.fit(
                        previous !== null &&
                            previous.orientation !== next.orientation,
                    );
                }
            });
        }

        this.refreshTiles();
    }

    private build(warehouse: Warehouse | null): void {
        this.builtFor = warehouse;
        this.tiles.clear();
        this.heads.clear();
        this.pair = { signs: [] };

        if (!warehouse) {
            replaceChildrenOf(this.world);

            return;
        }

        const pairs = el('div', { class: 'wms-plan__pairs' });

        for (let a = 0; a * 2 < warehouse.rows.length; a++) {
            const pair = el('div', {
                class: `wms-pair${a % 2 ? ' wms-pair--alt' : ''}`,
            });
            const sign = el('div', { class: 'wms-pair__sign' });

            pair.append(el('div', { class: 'wms-pair__lane' }), sign);
            this.pair.signs.push({ element: sign, aisle: a });

            [0, 1].forEach((side) => {
                const row = warehouse.rows[a * 2 + side];

                if (!row) {
                    return;
                }

                // A on the left, B on the right: as you walk in from the entrance
                const place = side ? 'b' : 'a';
                const plate = el('span', {
                    class: 'wms-plate',
                    text: row.letter,
                });
                const count = el('span', { class: 'wms-count is-hidden' });
                const head = el(
                    'div',
                    { class: `wms-aisle-head wms-aisle-head--${place}` },
                    plate,
                    count,
                );
                const rack = el('div', {
                    class: `wms-rack-strip wms-rack-strip--${place}`,
                });

                // column 1 sits at the entrance end (the strip is laid out bottom-up)
                for (let c = 1; c <= warehouse.cols; c++) {
                    const tile = new Tile(row.letter, c);

                    this.tiles.set(`${row.letter}|${c}`, tile);
                    rack.append(tile.element);
                }

                pair.append(head, rack);
                this.heads.set(row.letter, { head, rack, plate, count });
            });
            pairs.append(pair);
        }

        replaceChildrenOf(this.world, this.levelPill, pairs, this.entrance);
    }

    private renderLabels(): void {
        const warehouse = this.state.warehouse;
        const t = this.t;

        this.entrance.textContent = `▲  ${t('entrance')}  ▲`;

        if (!warehouse) {
            return;
        }

        for (const { element, aisle } of this.pair.signs) {
            const names = [
                warehouse.rows[aisle * 2]?.letter,
                warehouse.rows[aisle * 2 + 1]?.letter,
            ]
                .filter(Boolean)
                .join(' - ');

            element.textContent = `${t('aisle')} ${names}`;
        }

        for (const [letter, parts] of this.heads) {
            parts.plate.title = `${t('aisle')} ${letter}`;
        }
    }

    private refreshTiles(): void {
        const { warehouse, match, floor, targetId } = this.state;

        this.highlight = highlightScale(this.view.k);
        this.world.style.setProperty('--wms-hl', String(this.highlight));
        this.levelPill.classList.toggle('is-hidden', floor === null);
        this.levelPill.textContent =
            floor === null ? '' : `${this.t('level')} ${floor}`;

        if (!warehouse) {
            return;
        }

        const target =
            targetId === null ? null : (warehouse.byId.get(targetId) ?? null);

        for (const row of warehouse.rows) {
            const hits = match.byRow.get(row.letter) ?? 0;
            const parts = this.heads.get(row.letter);

            if (!parts) {
                continue;
            }

            const faded = match.filtering && hits === 0;

            parts.head.classList.toggle('is-faded', faded);
            parts.rack.classList.toggle('is-faded', faded);
            parts.rack.classList.toggle('is-hit', hits > 0);
            parts.count.classList.toggle('is-hidden', hits === 0);
            parts.count.textContent = String(hits);

            for (let c = 1; c <= warehouse.cols; c++) {
                const tile = this.tiles.get(`${row.letter}|${c}`);

                if (!tile) {
                    continue;
                }

                const isTarget =
                    target !== null &&
                    target.row === row.letter &&
                    target.col === c;

                tile.apply(
                    tileModel({
                        column: columnSlots(warehouse, row.letter, c),
                        match,
                        floor,
                        isTarget,
                    }),
                    isTarget ? target.code : null,
                );
            }
        }
    }

    /* ---------- viewport ---------- */

    private viewport(): { w: number; h: number } {
        return { w: this.stage.clientWidth, h: this.stage.clientHeight };
    }

    private stageOrigin(): { left: number; top: number } {
        const r = this.stage.getBoundingClientRect();

        return { left: r.left, top: r.top };
    }

    /** The plan's size in each orientation (laid out in turn, then left in the one chosen). */
    private measure(): {
        sizes: Record<Orientation, { w: number; h: number }>;
    } | null {
        const sizes = {} as Record<Orientation, { w: number; h: number }>;

        for (const orientation of ['v', 'h'] as const) {
            this.world.classList.toggle('is-horizontal', orientation === 'h');
            sizes[orientation] = {
                w: this.world.offsetWidth,
                h: this.world.offsetHeight,
            };
        }

        return sizes.v.w && sizes.v.h ? { sizes } : null;
    }

    private pan(dx: number, dy: number): void {
        this.view = { ...this.view, x: this.view.x + dx, y: this.view.y + dy };
        this.apply(false);
    }

    private zoomAbout(
        factor: number,
        cx: number,
        cy: number,
        animate = false,
    ): void {
        this.view = zoomAbout(
            this.view,
            factor,
            cx,
            cy,
            zoomLimits(this.fitZoom),
        );
        this.apply(animate);
        this.lightenWhileZooming();
        this.scheduleRefresh(80);
    }

    /**
     * Every zoom step re-rasters the whole plan, and the shadows, gradients and photos of hundreds of tiles are
     * most of that cost: paint the light look while zooming and the full detail once it settles.
     */
    private lightenWhileZooming(): void {
        this.world.classList.add('is-zooming');
        clearTimeout(this.zoomTimer);
        this.zoomTimer = setTimeout(
            () => this.world.classList.remove('is-zooming'),
            ZOOM_SETTLE_MS,
        );
    }

    private apply(animate: boolean): void {
        const w = this.world;

        w.classList.toggle('is-animating', animate);
        w.classList.toggle('is-coarse', isCoarse(this.view.k));
        w.style.transform = `translate(${this.view.x}px, ${this.view.y}px) scale(${this.view.k})`;
        w.style.setProperty('--wms-hl', String(highlightScale(this.view.k)));

        if (animate) {
            clearTimeout(this.animationTimer);
            this.animationTimer = setTimeout(
                () => w.classList.remove('is-animating'),
                ANIMATION_MS,
            );
        }
    }

    /** The highlight size depends on the zoom: redraw the tiles once the zoom settles. */
    private refreshTimer: ReturnType<typeof setTimeout> | undefined;

    private scheduleRefresh(ms: number): void {
        clearTimeout(this.refreshTimer);
        this.refreshTimer = setTimeout(() => {
            if (
                !this.isDisposed &&
                this.highlight !== highlightScale(this.view.k)
            ) {
                this.refreshTiles();
            }
        }, ms);
    }
}

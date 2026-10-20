import { View } from '../core/View';
import type { WarehouseGeometry } from '../domain/geometry';
import type { WarehouseRow } from '../domain/types';
import { isolateLtr } from '../i18n/bidi';
import { el } from '../utils/dom';

const W = 400;
const H = 360;

export interface MinimapFrame {
    mode: 'overview' | 'walk' | 'fly';
    /** Where the viewer stands (walk / fly) or the orbit pivot (overview). */
    x: number;
    z: number;
    yaw: number;
    /** Centre of the target slot, drawn as a yellow dot. */
    target: { x: number; z: number } | null;
}

export interface MinimapLabels {
    entrance: string;
    aisle: (names: string) => string;
    column: string;
}

/**
 * The mini-map: entrance at the bottom, aisles running up, row letters
 * (stacked per aisle pair when there are many rows), an arrow for where you
 * face and a caption with your aisle and bay. The plan under the arrow is
 * drawn once and reused until you change aisle or language.
 */
export class MinimapView extends View {
    readonly element: HTMLElement;
    private readonly canvas: HTMLCanvasElement;
    private readonly caption: HTMLElement;
    private readonly ctx: CanvasRenderingContext2D;
    private cache: HTMLCanvasElement | null = null;
    private cacheKey = '';
    private captionText = '';

    constructor(
        private geometry: WarehouseGeometry,
        private rows: readonly WarehouseRow[],
        private labels: MinimapLabels,
    ) {
        super();
        this.canvas = el('canvas', {
            class: 'wms-minimap__canvas',
            attrs: { width: W, height: H },
        });
        this.caption = el('div', { class: 'wms-minimap__caption' });
        this.element = el(
            'div',
            { class: 'wms-minimap wms-pill wms-hud' },
            this.canvas,
            this.caption,
        );
        this.ctx = this.canvas.getContext('2d') as CanvasRenderingContext2D;
    }

    mount(): void {}

    /** The warehouse changed shape (a refetch brought new rows): redraw the plan. */
    setWorld(geometry: WarehouseGeometry, rows: readonly WarehouseRow[]): void {
        this.geometry = geometry;
        this.rows = rows;
        this.cacheKey = '';
    }

    setLabels(labels: MinimapLabels): void {
        this.labels = labels;
        this.cacheKey = '';
    }

    setHidden(hidden: boolean): void {
        this.element.classList.toggle('is-hidden', hidden);
    }

    /** x -> canvas x: a rigid half turn of the floor (entrance at the bottom, A on the right), never a mirror. */
    private project() {
        const geo = this.geometry;
        const b = geo.bounds;
        const padLeft = 24;
        const padTop = 38;
        const padBottom = 40;
        const spanZ = b.maxZ - b.minZ;
        const scale = Math.min(
            (W - padLeft * 2) / b.w,
            (H - padTop - padBottom) / spanZ,
        );
        const ox = (W - b.w * scale) / 2;
        const oy = padTop + (H - padTop - padBottom - spanZ * scale) / 2;

        return {
            scale,
            mx: (x: number) => ox + (b.maxX - x) * scale,
            mz: (z: number) => oy + (b.maxZ - z) * scale,
        };
    }

    draw(frame: MinimapFrame): void {
        const geo = this.geometry;
        const { scale, mx, mz } = this.project();
        const here = frame.mode === 'overview' ? -1 : geo.aisleOf(frame.x);
        const key = `${here}|${this.labels.entrance}`;

        if (this.cacheKey !== key || !this.cache) {
            this.cache = this.paintPlan(here, scale, mx, mz);
            this.cacheKey = key;
        }

        const g = this.ctx;

        g.clearRect(0, 0, W, H);
        g.drawImage(this.cache, 0, 0);

        if (frame.target) {
            g.fillStyle = '#FACC15';
            g.beginPath();
            g.arc(mx(frame.target.x), mz(frame.target.z), 5, 0, 7);
            g.fill();
        }

        if (frame.mode === 'overview') {
            g.strokeStyle = '#FACC15';
            g.lineWidth = 2;
            g.strokeRect(mx(frame.x) - 9, mz(frame.z) - 9, 18, 18);
            this.setCaption('');

            return;
        }

        this.paintArrow(mx(frame.x), mz(frame.z), frame.yaw);
        this.setCaption(this.captionFor(frame, here));
    }

    /** Forward is (sin yaw, cos yaw) on the floor; the half turn flips both axes of the map. */
    private paintArrow(px: number, py: number, yaw: number): void {
        const g = this.ctx;

        g.save();
        g.translate(px, py);
        g.rotate(Math.atan2(-Math.cos(yaw), -Math.sin(yaw)));
        g.fillStyle = 'rgba(250,204,21,.27)';
        g.beginPath();
        g.moveTo(0, 0);
        g.arc(0, 0, 50, -0.7, 0.7);
        g.closePath();
        g.fill();
        g.fillStyle = '#2563EB';
        g.strokeStyle = '#fff';
        g.lineWidth = 2.5;
        g.beginPath();
        g.moveTo(13, 0);
        g.lineTo(-8, -8.5);
        g.lineTo(-3, 0);
        g.lineTo(-8, 8.5);
        g.closePath();
        g.fill();
        g.stroke();
        g.restore();
    }

    private captionFor(frame: MinimapFrame, aisle: number): string {
        const geo = this.geometry;
        const names = geo
            .aisleRows(aisle)
            .map((i) => this.rows[i]?.letter)
            .filter(Boolean)
            .join(' - ');
        const bay = Math.max(
            1,
            Math.min(geo.cols, Math.round(frame.z / geo.layout.colSpacing) + 1),
        );

        return frame.z < geo.zMin - 0.5
            ? `${this.labels.entrance} · ${this.labels.aisle(isolateLtr(names))}`
            : `${this.labels.aisle(isolateLtr(names))} · ${this.labels.column} ${bay}`;
    }

    private setCaption(text: string): void {
        if (text !== this.captionText) {
            this.captionText = text;
            this.caption.textContent = text;
        }
    }

    private paintPlan(
        here: number,
        scale: number,
        mx: (x: number) => number,
        mz: (z: number) => number,
    ): HTMLCanvasElement {
        const geo = this.geometry;
        const canvas = this.cache ?? document.createElement('canvas');

        canvas.width = W;
        canvas.height = H;

        const o = canvas.getContext('2d') as CanvasRenderingContext2D;
        const { aisleWidth, aisleGap } = geo.layout;

        for (let a = 0; a < geo.aisles; a++) {
            o.fillStyle =
                a === here ? 'rgba(250,204,21,.13)' : 'rgba(148,163,184,.07)';
            o.fillRect(
                mx(geo.aisleCx(a) + aisleWidth / 2),
                mz(geo.zMax + 2),
                aisleWidth * scale,
                (geo.zMax + 2 - (geo.zMin - 7)) * scale,
            );
        }

        o.fillStyle = 'rgba(37,99,235,.7)';
        o.strokeStyle = 'rgba(147,197,253,.65)';
        o.lineWidth = 1.2;

        for (const wall of geo.walls) {
            const x = mx(wall.x1);
            const y = mz(wall.z1);
            const w = (wall.x1 - wall.x0) * scale;
            const h = (wall.z1 - wall.z0) * scale;

            o.fillRect(x, y, w, h);
            o.strokeRect(x, y, w, h);
        }

        o.textAlign = 'center';
        o.textBaseline = 'alphabetic';

        if ((aisleGap - aisleWidth) * scale >= 14) {
            // enough room even between two racks that back onto each other (B and C)
            o.font = `800 ${Math.min(22, aisleWidth * scale * 0.8)}px Inter, Tajawal, sans-serif`;
            geo.walls.forEach((wall, i) => {
                o.fillStyle =
                    Math.floor(i / 2) === here
                        ? '#FACC15'
                        : 'rgba(255,255,255,.88)';
                o.fillText(
                    this.rows[i]?.letter ?? '',
                    mx((wall.x0 + wall.x1) / 2),
                    mz(geo.zMax) - 8,
                );
            });
        } else {
            // a big warehouse: one label per aisle pair, stacked "A" over "B", so nothing overlaps
            o.font = `800 ${Math.max(11, Math.min(15, aisleGap * scale * 0.6))}px Inter, Tajawal, sans-serif`;

            for (let a = 0; a < geo.aisles; a++) {
                const [first, second] = geo.aisleRows(a);
                const x = mx(geo.aisleCx(a));
                const y = mz(geo.zMax) - 8;

                o.fillStyle = a === here ? '#FACC15' : 'rgba(255,255,255,.88)';

                if (this.rows[second]) {
                    o.fillText(this.rows[first].letter, x, y - 14);
                    o.fillText(this.rows[second].letter, x, y);
                } else {
                    o.fillText(this.rows[first]?.letter ?? '', x, y);
                }
            }
        }

        o.fillStyle = '#EAB308';
        o.font = '800 17px Inter, Tajawal, sans-serif';
        o.fillText(`▲  ${this.labels.entrance}`, W / 2, H - 13);
        o.strokeStyle = 'rgba(234,179,8,.6)';
        o.setLineDash([6, 5]);
        o.lineWidth = 2;
        o.beginPath();
        o.moveTo(10, mz(geo.zMin - 2.5));
        o.lineTo(W - 10, mz(geo.zMin - 2.5));
        o.stroke();
        o.setLineDash([]);

        // bay numbers run up the side: 1 at the entrance
        o.fillStyle = 'rgba(255,255,255,.55)';
        o.font = '700 14px Inter, sans-serif';
        o.textAlign = 'left';
        o.fillText('1', 3, mz(geo.zOf(1)) + 5);
        o.fillText(String(geo.cols), 3, mz(geo.zOf(geo.cols)) + 5);

        return canvas;
    }
}

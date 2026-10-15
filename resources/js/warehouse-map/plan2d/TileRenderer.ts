import { isMatch } from '../domain/MatchIndex';
import type { MatchIndex, Slot } from '../domain/types';
import { append, el } from '../utils/dom';

export type LevelMark = 'x' | 'o' | 'f' | '';

export interface TileModel {
    kind: 'full' | 'opened' | 'empty';
    matched: boolean;
    dimmed: boolean;
    inactive: boolean;
    target: boolean;
    image: string | null;
    /** One bar per shelf level: out of service, opened, sealed or empty. */
    levels: LevelMark[];
}

export interface TileInputs {
    /** Every slot of the column, lowest level first. */
    column: readonly Slot[];
    match: MatchIndex;
    floor: number | null;
    isTarget: boolean;
}

/** Only these may reach a CSS `url()`: a server string must never break out of it. */
export function safeImageUrl(raw: string | null | undefined): string | null {
    if (!raw) {
        return null;
    }

    if (!/^(https?:)?\/\//i.test(raw) && !raw.startsWith('/')) {
        return null;
    }

    return raw.replace(
        /["'\\()\s]/g,
        (c) =>
            `%${c.charCodeAt(0).toString(16).toUpperCase().padStart(2, '0')}`,
    );
}

/** What a column of shelves looks like as one tile of the plan. */
export function tileModel({
    column,
    match,
    floor,
    isTarget,
}: TileInputs): TileModel {
    const floorSlots =
        floor === null ? column : column.filter((s) => s.level === floor);
    const occupied = floorSlots.filter((s) => s.state !== 'empty');
    const opened = floorSlots.some((s) => s.state === 'opened');
    const matched = floorSlots.some((s) => isMatch(match, s));
    const showImage = matched || !match.filtering;
    const image = showImage
        ? safeImageUrl(
              occupied.find((s) => s.pallet?.productImageUrl)?.pallet
                  ?.productImageUrl,
          )
        : null;
    let kind: TileModel['kind'] = 'empty';

    if (occupied.length > 0) {
        kind = opened ? 'opened' : 'full';
    }

    return {
        kind,
        matched: match.filtering && matched,
        dimmed: match.filtering && !matched,
        inactive: floorSlots.some((s) => !s.active),
        target: isTarget,
        image,
        levels: column.map((s) => {
            if (!s.active) {
                return 'x';
            }

            if (s.state === 'opened') {
                return 'o';
            }

            return s.state === 'full' ? 'f' : '';
        }),
    };
}

/** Builds a plan tile; `apply()` then keeps it in step with its model. */
export class Tile {
    readonly element: HTMLButtonElement;
    private readonly levelsEl: HTMLElement;
    private codeEl: HTMLElement | null = null;
    private levelsKey = '';

    constructor(row: string, col: number) {
        this.levelsEl = el('span', { class: 'wms-tile__levels' });
        this.element = el(
            'button',
            {
                class: 'wms-tile wms-tile--empty',
                attrs: { type: 'button', 'aria-label': `${row}${col}` },
                data: { row, col },
            },
            el('span', { class: 'wms-tile__num', text: String(col) }),
            this.levelsEl,
        );
    }

    apply(model: TileModel, targetCode: string | null): void {
        const classes = ['wms-tile', `wms-tile--${model.kind}`];

        if (model.matched) {
            classes.push('is-match');
        }

        if (model.dimmed) {
            classes.push('is-dim');
        }

        if (model.inactive) {
            classes.push('is-inactive');
        }

        if (model.target) {
            classes.push('is-target');
        }

        this.element.className = classes.join(' ');

        if (model.image) {
            this.element.style.setProperty(
                '--wms-tile-image',
                `url("${model.image}")`,
            );
            this.element.classList.add('has-image');
        } else {
            this.element.style.removeProperty('--wms-tile-image');
        }

        const key = model.levels.join('');

        if (key !== this.levelsKey) {
            this.levelsKey = key;
            this.levelsEl.replaceChildren();
            append(
                this.levelsEl,
                ...model.levels.map((mark) =>
                    el('i', {
                        class: `wms-tile__level${mark ? ` wms-tile__level--${mark}` : ''}`,
                    }),
                ),
            );
        }

        if (model.target && targetCode) {
            if (!this.codeEl) {
                this.codeEl = el('span', { class: 'wms-tile__code' });
                this.element.append(this.codeEl);
            }

            this.codeEl.textContent = targetCode;
        } else if (this.codeEl) {
            this.codeEl.remove();
            this.codeEl = null;
        }
    }
}

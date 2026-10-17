import type { SlotState } from './types';

export interface SlotRef {
    row: string;
    col: number;
    level: number;
}

/** The label printed on a shelf: row letter + column, then `- ` and the level (`A5- 2`). */
export function formatSlotCode(
    row: string,
    col: number,
    level: number,
): string {
    return `${row}${col}- ${level}`;
}

const CODE_PATTERN = /^([A-Za-z]+)(\d+)-\s*(\d+)$/;

/** Reads `A5- 2` (and the compact `A5-2`); null when it is not a slot code. */
export function parseSlotCode(code: string): SlotRef | null {
    const match = CODE_PATTERN.exec(code.trim());

    if (!match) {
        return null;
    }

    return {
        row: match[1].toUpperCase(),
        col: Number(match[2]),
        level: Number(match[3]),
    };
}

/** The deep link fragment that opens the 3D view in front of a slot: `#slot=B5-2`. */
export function slotHash(ref: SlotRef): string {
    return `#slot=${ref.row}${ref.col}-${ref.level}`;
}

/** A filter a link can open the map with: products (any of them) and a slot state. */
export interface FilterRef {
    productIds: number[];
    state: SlotState | null;
}

const SLOT_STATES: readonly SlotState[] = ['empty', 'full', 'opened'];

/**
 * The deep link fragment that opens the map filtered to some products (and a
 * state): `#products=12,34&state=full`. Empty when there is nothing to filter.
 */
export function filterHash(ref: FilterRef): string {
    const parts: string[] = [];

    if (ref.productIds.length > 0) {
        parts.push(`products=${ref.productIds.join(',')}`);
    }

    if (ref.state) {
        parts.push(`state=${ref.state}`);
    }

    return parts.length > 0 ? `#${parts.join('&')}` : '';
}

function parseFilter(fragment: string): FilterRef | null {
    const params = new URLSearchParams(fragment);
    const productIds = [
        ...new Set(
            (params.get('products') ?? '')
                .split(',')
                .map((id) => Number(id.trim()))
                .filter((id) => Number.isInteger(id) && id > 0),
        ),
    ];
    const state = params.get('state') as SlotState | null;
    const validState = state && SLOT_STATES.includes(state) ? state : null;

    return productIds.length > 0 || validState
        ? { productIds, state: validState }
        : null;
}

export type HashTarget =
    | { kind: 'view'; view: '2d' | '3d' }
    | { kind: 'slot'; slot: SlotRef }
    | { kind: 'filter'; filter: FilterRef };

/** Reads the page fragment: `#3d`, `#2d`, `#slot=B5-2` or `#products=12,34&state=full`. */
export function parseHash(hash: string): HashTarget | null {
    const fragment = hash.replace(/^#/, '');

    if (fragment === '2d' || fragment === '3d') {
        return { kind: 'view', view: fragment };
    }

    if (/^(products|state)=/.test(fragment)) {
        const filter = parseFilter(fragment);

        return filter ? { kind: 'filter', filter } : null;
    }

    if (fragment.startsWith('slot=')) {
        const slot = parseSlotCode(decodeURIComponent(fragment.slice(5)));

        return slot ? { kind: 'slot', slot } : null;
    }

    return null;
}

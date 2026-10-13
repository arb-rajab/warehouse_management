import { formatSlotCode } from './SlotCode';
import type {
    ApiCell,
    ApiPallet,
    ApiRow,
    Pallet,
    Slot,
    SlotState,
    Warehouse,
} from './types';

const DAY_MS = 86_400_000;

export function columnKey(letter: string, col: number): string {
    return `${letter}|${col}`;
}

/** `sealed` is the mobile app's name for `full`; anything unknown reads as empty. */
export function parseSlotState(raw: unknown): SlotState {
    const value = String(raw ?? '')
        .trim()
        .toLowerCase();

    if (value === 'opened') {
        return 'opened';
    }

    return value === 'full' || value === 'sealed' ? 'full' : 'empty';
}

function normalizePallet(raw: ApiPallet): Pallet {
    return {
        id: raw.id,
        productId: raw.product_id,
        expirationDate: raw.expiration_date ?? null,
        productName: raw.product_name ?? '',
        productArName: raw.product_ar_name ?? '',
        productImageUrl: raw.product_image_url ?? null,
        remainingBoxes: raw.remaining_boxes ?? null,
        productBoxesCount: raw.product_boxes_count ?? null,
    };
}

/** Whole days until the end of the expiry day; negative once it has passed. */
export function daysUntil(isoDate: string | null, now: number): number | null {
    if (!isoDate) {
        return null;
    }

    const end = new Date(`${isoDate}T23:59:59`).getTime();

    return Number.isNaN(end) ? null : Math.ceil((end - now) / DAY_MS);
}

function normalizeCell(
    cell: ApiCell,
    letter: string,
    rowIdx: number,
    now: number,
): Slot {
    const pallet = cell.pallet ? normalizePallet(cell.pallet) : null;

    return {
        id: cell.id,
        row: letter,
        rowIdx,
        col: cell.cell_number,
        level: cell.flat_number,
        state: parseSlotState(cell.state),
        active: cell.is_active !== false,
        pallet,
        code: formatSlotCode(letter, cell.cell_number, cell.flat_number),
        haystack: pallet
            ? `${pallet.productName} ${pallet.productArName}`.toLowerCase()
            : '',
        expiresInDays: daysUntil(pallet?.expirationDate ?? null, now),
    };
}

/**
 * Turns the nested rows of `GET /rows/full` into the flat, indexed warehouse
 * every view reads. Accepts the `{ data: [...] }` wrapper too.
 */
export function normalizeRows(
    payload: ApiRow[] | { data: ApiRow[] },
    now: number = Date.now(),
): Warehouse {
    const apiRows = Array.isArray(payload) ? payload : (payload.data ?? []);
    const warehouse: Warehouse = {
        rows: [],
        slots: [],
        cols: 0,
        levels: 0,
        byId: new Map(),
        byColumn: new Map(),
    };

    apiRows.forEach((row, rowIdx) => {
        const letter = String(row.letter ?? row.row_letter ?? '').trim();

        warehouse.rows.push({ id: row.id, letter, idx: rowIdx });

        for (const cell of row.cells ?? []) {
            const slot = normalizeCell(cell, letter, rowIdx, now);
            const key = columnKey(letter, slot.col);
            const column = warehouse.byColumn.get(key) ?? [];

            column.push(slot);
            warehouse.byColumn.set(key, column);
            warehouse.slots.push(slot);
            warehouse.byId.set(slot.id, slot);
            warehouse.cols = Math.max(warehouse.cols, slot.col);
            warehouse.levels = Math.max(warehouse.levels, slot.level);
        }
    });

    warehouse.byColumn.forEach((column) =>
        column.sort((a, b) => a.level - b.level),
    );

    return warehouse;
}

export function columnSlots(
    warehouse: Warehouse,
    letter: string,
    col: number,
): Slot[] {
    return warehouse.byColumn.get(columnKey(letter, col)) ?? [];
}

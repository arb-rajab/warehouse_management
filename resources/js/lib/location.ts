import { t } from '@/lib/i18n';

/**
 * The warehouse-wide label for a single slot, e.g. `A3·2` for cell 3, flat 2 of row A.
 */
export function formatSlot(
    rowLetter: string,
    cellNumber: number,
    flatNumber: number,
): string {
    return `${rowLetter}${cellNumber}·${flatNumber}`;
}

/**
 * A row's label in a dropdown or list: its letter, followed by its warehouse
 * (`A - Main`) when the payload carries one. A letter names one row per
 * warehouse, so `Row::filterOptions()` sends the warehouse for the letters
 * that more than one warehouse uses, and the round listings send it always.
 */
export function rowOptionLabel(row: {
    letter: string;
    warehouse?: { name: string };
}): string {
    return row.warehouse
        ? t('warehouses.rowOption', {
              letter: row.letter,
              warehouse: row.warehouse.name,
          })
        : row.letter;
}

/**
 * The rows of a set as one label, e.g. `A, C, D` for the rows a verification
 * round covers (`A - Main, C - Main` where the payload names the warehouses).
 * Already ordered by the server; an absent or empty list renders as an em dash
 * rather than an empty cell.
 */
export function formatRowLetters(
    rows: { letter: string; warehouse?: { name: string } }[] | undefined,
): string {
    if (rows === undefined || rows.length === 0) {
        return '—';
    }

    return rows.map(rowOptionLabel).join(', ');
}

/**
 * The Wayfinder options of a link to a row's page: `warehouse_id` whenever the
 * payload names the row's warehouse (a letter is unique per warehouse only,
 * and the backend refuses one that several warehouses use without it), plus
 * any other query parameters (`cell_number`, `flat_number`).
 */
export function rowLinkOptions(
    warehouse: { id: number } | undefined,
    query: Record<string, number | string> = {},
): { query: Record<string, number | string> } {
    return {
        query: warehouse ? { ...query, warehouse_id: warehouse.id } : query,
    };
}

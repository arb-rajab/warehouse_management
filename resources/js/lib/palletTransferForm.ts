import type { ValidationSchema } from '@/lib/formValidation';
import palletTransferRules from '@/lib/palletTransferRules.json';
import type { CellMapRow } from '@/types/admin';

/**
 * Admin\TransferPalletRequest, in the browser: the destination fields and the
 * note. `lib/palletTransferRules.json` is kept equal to the server's rules by
 * TransferPalletClientValidationParityTest; the `exists` checks (warehouse,
 * row letter) and whether the slot exists read the database, so only the
 * server runs them. `return_to` and `warehouse_ids` have no control.
 */
export const palletTransferValidation: ValidationSchema = {
    rules: palletTransferRules.rules,
};

/** The fields of a transfer's destination, as the dialogs post them. */
export const palletTransferFieldKeys = [
    'warehouse_id',
    'row_letter',
    'cell_number',
    'flat_number',
    'note',
] as const;

/** The rows a transfer can go to in one warehouse, by letter. */
export function rowsInWarehouse(
    rows: CellMapRow[],
    warehouseId: number | null,
): CellMapRow[] {
    return rows
        .filter(
            (row) => warehouseId === null || row.warehouse_id === warehouseId,
        )
        .sort((a, b) => a.letter.localeCompare(b.letter));
}

/**
 * The errors no transfer field shows inline (a refused action such as a
 * destination that is no longer empty), for the dialog's banner.
 */
export function nonFieldErrors(
    errors: Record<string, string>,
): Record<string, string> {
    return Object.fromEntries(
        Object.entries(errors).filter(
            ([key]) =>
                !(palletTransferFieldKeys as readonly string[]).includes(key),
        ),
    );
}

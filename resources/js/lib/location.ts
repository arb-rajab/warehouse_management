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

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
 * The letters of a set of rows as one label, e.g. `A, C, D` for the rows a
 * verification round covers. Already ordered by the server; an absent or empty
 * list renders as an em dash rather than an empty cell.
 */
export function formatRowLetters(
    rows: { letter: string }[] | undefined,
): string {
    if (rows === undefined || rows.length === 0) {
        return '—';
    }

    return rows.map((row) => row.letter).join(', ');
}

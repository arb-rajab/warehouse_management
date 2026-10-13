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

export type HashTarget =
    { kind: 'view'; view: '2d' | '3d' } | { kind: 'slot'; slot: SlotRef };

/** Reads the page fragment: `#3d`, `#2d` or `#slot=B5-2`. */
export function parseHash(hash: string): HashTarget | null {
    const fragment = hash.replace(/^#/, '');

    if (fragment === '2d' || fragment === '3d') {
        return { kind: 'view', view: fragment };
    }

    if (fragment.startsWith('slot=')) {
        const slot = parseSlotCode(decodeURIComponent(fragment.slice(5)));

        return slot ? { kind: 'slot', slot } : null;
    }

    return null;
}

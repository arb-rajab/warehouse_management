import { describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import type { CellStatusLog } from '@/types/admin';
import {
    cellLogActionLabel,
    flagReasonLabel,
    mergeTransferPairs,
    transferPair,
} from './cellStatusLogDisplay';

function cellLog(overrides: Partial<CellStatusLog> = {}): CellStatusLog {
    return {
        id: 1,
        action: 'stored',
        from_state: 'empty',
        to_state: 'full',
        note: null,
        boxes_count: null,
        cell: { row_letter: 'A', cell_number: 3, flat_number: 2 },
        related_cell: null,
        product: null,
        pallet: null,
        user: { id: 7, name: 'Jane Doe' },
        created_at: '2026-08-01T10:00:00Z',
        next_log_at: null,
        duration_seconds: 0,
        flagged: false,
        flags: [],
        ...overrides,
    };
}

describe('cellLogActionLabel', () => {
    it('translates the action key', () => {
        expect(cellLogActionLabel('opened')).toBe(t('cellLog.actions.opened'));
    });
});

describe('flagReasonLabel', () => {
    it('translates the reason key', () => {
        expect(flagReasonLabel('off_hours')).toBe(
            t('cellLog.flags.reasons.off_hours'),
        );
    });
});

describe('transferPair', () => {
    it('treats the log cell as the origin for a transferred_out entry', () => {
        const log = cellLog({
            action: 'transferred_out',
            cell: { row_letter: 'A', cell_number: 1, flat_number: 1 },
            related_cell: { row_letter: 'B', cell_number: 2, flat_number: 1 },
        });

        expect(transferPair(log)).toEqual({
            from: { row_letter: 'A', cell_number: 1, flat_number: 1 },
            to: { row_letter: 'B', cell_number: 2, flat_number: 1 },
        });
    });

    it('treats the related cell as the origin for a transferred_in entry', () => {
        const log = cellLog({
            action: 'transferred_in',
            cell: { row_letter: 'B', cell_number: 2, flat_number: 1 },
            related_cell: { row_letter: 'A', cell_number: 1, flat_number: 1 },
        });

        expect(transferPair(log)).toEqual({
            from: { row_letter: 'A', cell_number: 1, flat_number: 1 },
            to: { row_letter: 'B', cell_number: 2, flat_number: 1 },
        });
    });

    it('has no destination for a non-transfer entry', () => {
        const log = cellLog({ action: 'stored', related_cell: null });

        expect(transferPair(log)).toEqual({
            from: { row_letter: 'A', cell_number: 3, flat_number: 2 },
            to: null,
        });
    });
});

describe('mergeTransferPairs', () => {
    it('merges a transferred_out/transferred_in pair sharing the same pallet and timestamp', () => {
        const out = cellLog({
            id: 1,
            action: 'transferred_out',
            pallet: { id: 55, expiration_date: null },
            cell: { row_letter: 'A', cell_number: 1, flat_number: 1 },
            related_cell: { row_letter: 'B', cell_number: 2, flat_number: 1 },
            created_at: '2026-08-01T10:00:00Z',
        });
        const inLog = cellLog({
            id: 2,
            action: 'transferred_in',
            pallet: { id: 55, expiration_date: null },
            cell: { row_letter: 'B', cell_number: 2, flat_number: 1 },
            related_cell: { row_letter: 'A', cell_number: 1, flat_number: 1 },
            created_at: '2026-08-01T10:00:00Z',
        });

        const merged = mergeTransferPairs([out, inLog]);

        expect(merged).toHaveLength(1);
        expect(merged[0].id).toBe(out.id);
        expect(merged[0].pairedIn?.id).toBe(inLog.id);
    });

    it('does not merge a pair for different pallets', () => {
        const out = cellLog({
            id: 1,
            action: 'transferred_out',
            pallet: { id: 55, expiration_date: null },
            cell: { row_letter: 'A', cell_number: 1, flat_number: 1 },
            related_cell: { row_letter: 'B', cell_number: 2, flat_number: 1 },
            created_at: '2026-08-01T10:00:00Z',
        });
        const inLog = cellLog({
            id: 2,
            action: 'transferred_in',
            pallet: { id: 56, expiration_date: null },
            cell: { row_letter: 'B', cell_number: 2, flat_number: 1 },
            related_cell: { row_letter: 'A', cell_number: 1, flat_number: 1 },
            created_at: '2026-08-01T10:00:00Z',
        });

        expect(mergeTransferPairs([out, inLog])).toHaveLength(2);
    });

    it('does not merge a lone transferred_out entry with no matching transferred_in', () => {
        const out = cellLog({
            id: 1,
            action: 'transferred_out',
            pallet: { id: 55, expiration_date: null },
        });

        const merged = mergeTransferPairs([out]);

        expect(merged).toHaveLength(1);
        expect(merged[0].pairedIn).toBeUndefined();
    });

    it('leaves non-transfer entries untouched', () => {
        const stored = cellLog({ id: 1, action: 'stored' });
        const opened = cellLog({ id: 2, action: 'opened' });

        expect(mergeTransferPairs([stored, opened])).toHaveLength(2);
    });
});

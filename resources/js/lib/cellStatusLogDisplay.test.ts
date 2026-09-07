import { beforeEach, describe, expect, it, vi } from 'vitest';
import { t } from '@/lib/i18n';
import { cellLog as cellLogFixture } from '@/testing/factories';
import type { CellStatusLog, CellStatusLogFlag } from '@/types/admin';
import {
    acknowledgeFlags,
    cellLogActionLabel,
    flagReasonLabel,
    hasUnacknowledgedFlags,
    mergeTransferPairs,
    transferPair,
} from './cellStatusLogDisplay';

const { routerPostMock } = vi.hoisted(() => ({
    routerPostMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { post: routerPostMock },
}));

function cellLog(overrides: Partial<CellStatusLog> = {}): CellStatusLog {
    return cellLogFixture({
        note: null,
        product: null,
        pallet: null,
        duration_seconds: 0,
        ...overrides,
    });
}

function flag(overrides: Partial<CellStatusLogFlag> = {}): CellStatusLogFlag {
    return { id: 1, reason: 'off_hours', acknowledged: false, ...overrides };
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

describe('hasUnacknowledgedFlags', () => {
    it('is false when there are no flags', () => {
        expect(hasUnacknowledgedFlags(cellLog({ flags: [] }))).toBe(false);
    });

    it('is false when every flag is already acknowledged', () => {
        const log = cellLog({
            flags: [flag({ acknowledged: true }), flag({ acknowledged: true })],
        });

        expect(hasUnacknowledgedFlags(log)).toBe(false);
    });

    it('is true when at least one flag is unacknowledged', () => {
        const log = cellLog({
            flags: [
                flag({ acknowledged: true }),
                flag({ acknowledged: false }),
            ],
        });

        expect(hasUnacknowledgedFlags(log)).toBe(true);
    });
});

describe('acknowledgeFlags', () => {
    beforeEach(() => {
        routerPostMock.mockReset();
    });

    it('posts to the log-specific acknowledge endpoint', () => {
        acknowledgeFlags(cellLog({ id: 42 }));

        expect(routerPostMock).toHaveBeenCalledTimes(1);
        expect(routerPostMock.mock.calls[0][0]).toContain(
            'cell-logs/42/acknowledge-flags',
        );
    });

    it('sends return_to null by default', () => {
        acknowledgeFlags(cellLog({ id: 42 }));

        expect(routerPostMock.mock.calls[0][1]).toEqual({ return_to: null });
    });

    it('sends the given return_to so the backend redirects back to that page', () => {
        acknowledgeFlags(cellLog({ id: 42 }), 'user');

        expect(routerPostMock.mock.calls[0][1]).toEqual({ return_to: 'user' });
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

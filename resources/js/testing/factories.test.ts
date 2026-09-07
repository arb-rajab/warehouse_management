import { describe, expect, it } from 'vitest';
import {
    cell,
    cellLog,
    cellVerificationReport,
    cellVerificationRound,
    paginated,
    row,
    user,
} from './factories';

describe('row', () => {
    it('applies default fields', () => {
        expect(row()).toMatchObject({
            id: 1,
            letter: 'A',
            cells_count: 5,
            flats_count: 7,
            has_pallets: false,
        });
    });

    it('merges overrides on top of the defaults, without dropping untouched fields', () => {
        expect(row({ letter: 'B', has_pallets: true })).toMatchObject({
            id: 1,
            letter: 'B',
            cells_count: 5,
            flats_count: 7,
            has_pallets: true,
        });
    });
});

describe('cell', () => {
    it('applies default fields', () => {
        expect(cell()).toMatchObject({
            id: 1,
            cell_number: 1,
            flat_number: 1,
            state: 'empty',
            is_active: true,
            pallet: null,
        });
    });

    it('merges overrides on top of the defaults, without dropping untouched fields', () => {
        expect(cell({ state: 'full', is_active: false })).toMatchObject({
            id: 1,
            cell_number: 1,
            state: 'full',
            is_active: false,
        });
    });
});

describe('user', () => {
    it('applies default fields', () => {
        expect(user()).toMatchObject({
            id: 7,
            name: 'Jane Doe',
            email: 'jane@example.com',
            is_admin: false,
        });
    });

    it('merges overrides on top of the defaults, without dropping untouched fields', () => {
        expect(user({ is_admin: true })).toMatchObject({
            id: 7,
            name: 'Jane Doe',
            is_admin: true,
        });
    });
});

describe('cellVerificationReport', () => {
    it('applies default fields', () => {
        const result = cellVerificationReport();

        expect(result).toMatchObject({
            id: 1,
            cell_verification_round_id: 1,
            is_correct: true,
            cell: { row_letter: 'A', cell_number: 3, flat_number: 2 },
        });
        expect(result.expected).toEqual(result.reported);
    });

    it('merges overrides on top of the defaults, without dropping untouched fields', () => {
        const result = cellVerificationReport({
            is_correct: false,
            note: 'Mismatch',
        });

        expect(result).toMatchObject({
            id: 1,
            is_correct: false,
            note: 'Mismatch',
        });
        expect(result.expected).toBeDefined();
    });
});

describe('cellVerificationRound', () => {
    it('applies default fields', () => {
        expect(cellVerificationRound()).toMatchObject({
            id: 1,
            reports_count: 3,
            user: { id: 7, name: 'Jane Doe' },
        });
    });

    it('merges overrides on top of the defaults, without dropping untouched fields', () => {
        expect(cellVerificationRound({ reports_count: 9 })).toMatchObject({
            id: 1,
            reports_count: 9,
            user: { id: 7, name: 'Jane Doe' },
        });
    });
});

describe('cellLog', () => {
    it('applies default fields', () => {
        expect(cellLog()).toMatchObject({
            id: 1,
            action: 'stored',
            from_state: 'empty',
            to_state: 'full',
            flagged: false,
            flags: [],
        });
    });

    it('merges overrides on top of the defaults, without dropping untouched fields', () => {
        expect(
            cellLog({ flagged: true, action: 'boxes_removed' }),
        ).toMatchObject({
            id: 1,
            action: 'boxes_removed',
            flagged: true,
            to_state: 'full',
        });
    });
});

describe('paginated', () => {
    it('sets from/to/total for non-empty data', () => {
        const result = paginated(['a', 'b', 'c'], 20);

        expect(result.meta).toMatchObject({
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 3,
            from: 1,
            to: 3,
        });
    });

    it('sets from to null and to/total to 0 for empty data', () => {
        const result = paginated([]);

        expect(result.meta).toMatchObject({
            total: 0,
            from: null,
            to: 0,
        });
    });
});

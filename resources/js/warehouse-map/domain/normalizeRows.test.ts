import { describe, expect, it } from 'vitest';
import {
    columnSlots,
    daysUntil,
    normalizeRows,
    parseSlotState,
} from './normalizeRows';
import type { ApiRow } from './types';

const NOW = new Date('2026-10-13T12:00:00').getTime();

function apiRows(): ApiRow[] {
    return [
        {
            id: 1,
            letter: 'A',
            cells: [
                {
                    id: 12,
                    cell_number: 1,
                    flat_number: 2,
                    state: 'opened',
                    is_active: false,
                    pallet: {
                        id: 90,
                        product_id: 14,
                        expiration_date: '2026-10-20',
                        product_name: 'Basmati Rice',
                        product_ar_name: 'أرز',
                        product_image_url: 'https://cdn.example/rice.png',
                        remaining_boxes: 31,
                        product_boxes_count: 48,
                    },
                },
                {
                    id: 11,
                    cell_number: 1,
                    flat_number: 1,
                    state: 'empty',
                    pallet: null,
                },
            ],
        },
        {
            id: 2,
            row_letter: 'B',
            cells: [
                { id: 21, cell_number: 3, flat_number: 1, state: 'sealed' },
            ],
        },
        { id: 3, letter: 'C', cells: null },
    ];
}

describe('parseSlotState', () => {
    it('reads sealed as full and anything unknown as empty', () => {
        expect(parseSlotState('sealed')).toBe('full');
        expect(parseSlotState(' FULL ')).toBe('full');
        expect(parseSlotState('opened')).toBe('opened');
        expect(parseSlotState('empty')).toBe('empty');
        expect(parseSlotState('nonsense')).toBe('empty');
        expect(parseSlotState(null)).toBe('empty');
    });
});

describe('daysUntil', () => {
    it('counts whole days to the end of the expiry day', () => {
        expect(daysUntil('2026-10-13', NOW)).toBe(1);
        expect(daysUntil('2026-10-20', NOW)).toBe(8);
        expect(daysUntil('2026-10-10', NOW)).toBe(-2);
    });

    it('is null without a usable date', () => {
        expect(daysUntil(null, NOW)).toBeNull();
        expect(daysUntil('not a date', NOW)).toBeNull();
    });
});

describe('normalizeRows', () => {
    it('flattens rows and cells into indexed slots', () => {
        const warehouse = normalizeRows(apiRows(), NOW);

        expect(warehouse.rows).toEqual([
            { id: 1, letter: 'A', idx: 0 },
            { id: 2, letter: 'B', idx: 1 },
            { id: 3, letter: 'C', idx: 2 },
        ]);
        expect(warehouse.slots).toHaveLength(3);
        expect(warehouse.cols).toBe(3);
        expect(warehouse.levels).toBe(2);
        expect(warehouse.byId.get(21)?.row).toBe('B');
    });

    it('builds the slot with its code, search text and expiry', () => {
        const slot = normalizeRows(apiRows(), NOW).byId.get(12)!;

        expect(slot).toMatchObject({
            id: 12,
            row: 'A',
            rowIdx: 0,
            col: 1,
            level: 2,
            state: 'opened',
            active: false,
            code: 'A1- 2',
            haystack: 'basmati rice أرز',
            expiresInDays: 8,
            pallet: {
                id: 90,
                productId: 14,
                productImageUrl: 'https://cdn.example/rice.png',
                remainingBoxes: 31,
                productBoxesCount: 48,
            },
        });
    });

    it('tolerates the optional pallet fields being absent', () => {
        const rows: ApiRow[] = [
            {
                id: 1,
                letter: 'A',
                cells: [
                    {
                        id: 1,
                        cell_number: 1,
                        flat_number: 1,
                        state: 'full',
                        pallet: { id: 5, product_id: 3, expiration_date: null },
                    },
                ],
            },
        ];
        const slot = normalizeRows(rows, NOW).slots[0];

        expect(slot.active).toBe(true);
        expect(slot.pallet).toMatchObject({
            productName: '',
            productImageUrl: null,
            remainingBoxes: null,
            productBoxesCount: null,
        });
        expect(slot.expiresInDays).toBeNull();
    });

    it('lists a column lowest level first and unwraps a data wrapper', () => {
        const warehouse = normalizeRows({ data: apiRows() }, NOW);

        expect(columnSlots(warehouse, 'A', 1).map((s) => s.level)).toEqual([
            1, 2,
        ]);
        expect(columnSlots(warehouse, 'A', 99)).toEqual([]);
    });
});

import { describe, expect, it } from 'vitest';
import { buildMatchIndex, isMatch } from './MatchIndex';
import { isFiltering, parseTerms, slotMatches } from './SlotFilter';
import type { Slot, SlotFilter } from './types';

function slot(partial: Partial<Slot> & { id: number }): Slot {
    return {
        row: 'A',
        rowIdx: 0,
        col: 1,
        level: 1,
        state: 'full',
        active: true,
        pallet: {
            id: partial.id,
            productId: 1,
            expirationDate: null,
            productName: 'x',
            productArName: '',
            productImageUrl: null,
            remainingBoxes: null,
            productBoxesCount: null,
        },
        code: 'A1- 1',
        haystack: 'basmati rice',
        expiresInDays: 100,
        ...partial,
    };
}

const none: SlotFilter = { terms: [], state: 'all', expiring: false };

describe('parseTerms', () => {
    it('splits on whitespace and lower-cases', () => {
        expect(parseTerms('  Basmati   RICE ')).toEqual(['basmati', 'rice']);
        expect(parseTerms('   ')).toEqual([]);
    });
});

describe('slotMatches', () => {
    it('requires every search word to match, in either language', () => {
        const rice = slot({ id: 1, haystack: 'basmati rice أرز بسمتي' });

        expect(slotMatches(rice, { ...none, terms: ['rice'] })).toBe(true);
        expect(slotMatches(rice, { ...none, terms: ['rice', 'basmati'] })).toBe(
            true,
        );
        expect(slotMatches(rice, { ...none, terms: ['rice', 'sugar'] })).toBe(
            false,
        );
        expect(slotMatches(rice, { ...none, terms: ['أرز'] })).toBe(true);
    });

    it('filters by state', () => {
        const opened = slot({ id: 1, state: 'opened' });

        expect(slotMatches(opened, { ...none, state: 'opened' })).toBe(true);
        expect(slotMatches(opened, { ...none, state: 'full' })).toBe(false);
    });

    it('lets an empty slot match only when no pallet filter is on', () => {
        const empty = slot({
            id: 1,
            state: 'empty',
            pallet: null,
            haystack: '',
        });

        expect(slotMatches(empty, none)).toBe(true);
        expect(slotMatches(empty, { ...none, state: 'empty' })).toBe(true);
        expect(slotMatches(empty, { ...none, terms: ['rice'] })).toBe(false);
        expect(slotMatches(empty, { ...none, expiring: true })).toBe(false);
    });

    it('matches expiring within 30 days, and already expired', () => {
        const expiring = { ...none, expiring: true };

        expect(slotMatches(slot({ id: 1, expiresInDays: 30 }), expiring)).toBe(
            true,
        );
        expect(slotMatches(slot({ id: 2, expiresInDays: 31 }), expiring)).toBe(
            false,
        );
        expect(slotMatches(slot({ id: 3, expiresInDays: -4 }), expiring)).toBe(
            true,
        );
        expect(
            slotMatches(slot({ id: 4, expiresInDays: null }), expiring),
        ).toBe(false);
    });

    it('knows whether anything is being filtered', () => {
        expect(isFiltering(none)).toBe(false);
        expect(isFiltering({ ...none, terms: ['a'] })).toBe(true);
        expect(isFiltering({ ...none, state: 'full' })).toBe(true);
        expect(isFiltering({ ...none, expiring: true })).toBe(true);
    });
});

describe('buildMatchIndex', () => {
    const slots = [
        slot({ id: 1, row: 'A', level: 1, haystack: 'rice' }),
        slot({ id: 2, row: 'A', level: 2, haystack: 'rice' }),
        slot({ id: 3, row: 'B', level: 2, haystack: 'sugar' }),
        slot({
            id: 4,
            row: 'B',
            level: 1,
            state: 'empty',
            pallet: null,
            haystack: '',
        }),
    ];

    it('counts matches in total, per row and per level', () => {
        const index = buildMatchIndex(slots, { ...none, terms: ['rice'] });

        expect(index.filtering).toBe(true);
        expect(index.count).toBe(2);
        expect(index.byRow.get('A')).toBe(2);
        expect(index.byRow.has('B')).toBe(false);
        expect(index.byLevel.get(1)).toBe(1);
        expect(index.byLevel.get(2)).toBe(1);
    });

    it('treats every slot as a match while nothing is filtered', () => {
        const index = buildMatchIndex(slots, none);

        expect(index.filtering).toBe(false);
        expect(index.count).toBe(0);
        expect(isMatch(index, slots[0])).toBe(true);
    });

    it('reports non-matching slots when filtering', () => {
        const index = buildMatchIndex(slots, { ...none, terms: ['rice'] });

        expect(isMatch(index, slots[0])).toBe(true);
        expect(isMatch(index, slots[2])).toBe(false);
    });
});

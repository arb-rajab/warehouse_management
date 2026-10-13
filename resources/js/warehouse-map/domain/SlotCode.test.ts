import { describe, expect, it } from 'vitest';
import { formatSlotCode, parseHash, parseSlotCode, slotHash } from './SlotCode';

describe('SlotCode', () => {
    it('formats the printed shelf label', () => {
        expect(formatSlotCode('A', 5, 2)).toBe('A5- 2');
        expect(formatSlotCode('AB', 12, 1)).toBe('AB12- 1');
    });

    it('parses both the printed and the compact form', () => {
        expect(parseSlotCode('A5- 2')).toEqual({ row: 'A', col: 5, level: 2 });
        expect(parseSlotCode('b5-2')).toEqual({ row: 'B', col: 5, level: 2 });
        expect(parseSlotCode(' AB12-  1 ')).toEqual({
            row: 'AB',
            col: 12,
            level: 1,
        });
    });

    it('rejects anything that is not a slot code', () => {
        expect(parseSlotCode('')).toBeNull();
        expect(parseSlotCode('5-2')).toBeNull();
        expect(parseSlotCode('A5')).toBeNull();
        expect(parseSlotCode('A5-x')).toBeNull();
    });

    it('builds and reads the deep link', () => {
        expect(slotHash({ row: 'B', col: 5, level: 2 })).toBe('#slot=B5-2');
        expect(parseHash('#slot=B5-2')).toEqual({
            kind: 'slot',
            slot: { row: 'B', col: 5, level: 2 },
        });
        expect(parseHash('#slot=B5-%202')).toEqual({
            kind: 'slot',
            slot: { row: 'B', col: 5, level: 2 },
        });
        expect(parseHash('#3d')).toEqual({ kind: 'view', view: '3d' });
        expect(parseHash('#2d')).toEqual({ kind: 'view', view: '2d' });
        expect(parseHash('#slot=nope')).toBeNull();
        expect(parseHash('')).toBeNull();
    });
});

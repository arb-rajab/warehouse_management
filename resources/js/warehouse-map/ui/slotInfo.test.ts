import { describe, expect, it } from 'vitest';
import { makeWarehouse } from '../testing/fixtures';
import { boxesText, expiryText, meter, stateLabel } from './slotInfo';

const t = (key: string, params?: Record<string, string | number>) =>
    params ? `${key}:${params.count}` : key;
const slot = makeWarehouse(1, 3, 2).slots.find((s) => s.state === 'opened')!;

describe('slotInfo', () => {
    it('labels the states', () => {
        expect(stateLabel('opened', t)).toBe('stOpened');
        expect(stateLabel('full', t)).toBe('stFull');
        expect(stateLabel('empty', t)).toBe('stEmpty');
    });

    it('words the expiry with days left, days ago, and a tone', () => {
        const base = {
            ...slot,
            pallet: { ...slot.pallet!, expirationDate: '2026-12-31' },
        };

        expect(expiryText({ ...base, expiresInDays: 60 }, 'en', t)).toEqual({
            text: 'expires 31 Dec 2026 · daysLeft:60',
            tone: 'ok',
        });
        expect(expiryText({ ...base, expiresInDays: 10 }, 'en', t)?.tone).toBe(
            'soon',
        );
        expect(
            expiryText({ ...base, expiresInDays: -3 }, 'en', t),
        ).toMatchObject({
            text: 'expires 31 Dec 2026 · daysAgo:3',
            tone: 'bad',
        });
        expect(
            expiryText(
                { ...base, pallet: { ...base.pallet, expirationDate: null } },
                'en',
                t,
            ),
        ).toBeNull();
    });

    it('shows boxes left only when the server says, and a gauge that follows them', () => {
        const withBoxes = {
            ...slot,
            pallet: {
                ...slot.pallet!,
                remainingBoxes: 5,
                productBoxesCount: 20,
            },
        };

        expect(boxesText(withBoxes, t)).toBe('5 / 20 boxes');
        expect(
            meter(withBoxes, 'm')
                ?.querySelector('i')
                ?.style.getPropertyValue('--wms-fill'),
        ).toBe('25%');
        expect(meter(withBoxes, 'm')?.className).toBe('m is-opened');

        const without = {
            ...slot,
            pallet: {
                ...slot.pallet!,
                remainingBoxes: null,
                productBoxesCount: null,
            },
        };

        expect(boxesText(without, t)).toBeNull();
        expect(meter(without, 'm')).toBeNull();
    });
});

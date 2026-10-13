import { describe, expect, it } from 'vitest';
import { expiryInfo, gaugeBand, productHue, remainingPercent } from './expiry';

describe('expiryInfo', () => {
    it('is null without an expiry', () => {
        expect(expiryInfo(null)).toBeNull();
    });

    it('marks expired, soon and fine pallets', () => {
        expect(expiryInfo(-3)).toEqual({ tone: 'bad', days: 3, expired: true });
        expect(expiryInfo(0)).toEqual({
            tone: 'soon',
            days: 0,
            expired: false,
        });
        expect(expiryInfo(30)).toEqual({
            tone: 'soon',
            days: 30,
            expired: false,
        });
        expect(expiryInfo(31)).toEqual({
            tone: 'ok',
            days: 31,
            expired: false,
        });
    });
});

describe('remainingPercent', () => {
    it('rounds the share of boxes left', () => {
        expect(remainingPercent(31, 48)).toBe(65);
        expect(remainingPercent(0, 48)).toBe(0);
    });

    it('is null when the server did not say', () => {
        expect(remainingPercent(null, 48)).toBeNull();
        expect(remainingPercent(5, null)).toBeNull();
        expect(remainingPercent(5, 0)).toBeNull();
    });
});

describe('gaugeBand', () => {
    it('is green above half, amber from a quarter, red below', () => {
        expect(gaugeBand(0.51)).toBe('high');
        expect(gaugeBand(0.5)).toBe('mid');
        expect(gaugeBand(0.26)).toBe('mid');
        expect(gaugeBand(0.25)).toBe('low');
        expect(gaugeBand(0.05)).toBe('low');
    });
});

describe('productHue', () => {
    it('is stable and within the colour wheel', () => {
        expect(productHue(14)).toBe((14 * 47) % 360);
        expect(productHue(14)).toBe(productHue(14));
        expect(productHue(1000)).toBeLessThan(360);
    });
});

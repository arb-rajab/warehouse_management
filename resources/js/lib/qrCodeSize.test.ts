import { describe, expect, it } from 'vitest';
import { cmToPx, pxToCm } from './qrCodeSize';

describe('qrCodeSize', () => {
    it('converts pixels to centimeters, rounded to 1 decimal place', () => {
        expect(pxToCm(280)).toBe(7.4);
        expect(pxToCm(380)).toBe(10.1);
    });

    it('converts centimeters to whole pixels', () => {
        expect(cmToPx(7.4)).toBe(280);
        expect(cmToPx(10.1)).toBe(382);
    });

    it('rounds a fractional pixel result to the nearest pixel', () => {
        expect(cmToPx(10)).toBe(378);
    });

    it('round-trips the backend min/max bounds within a pixel', () => {
        expect(cmToPx(pxToCm(100))).toBeGreaterThanOrEqual(98);
        expect(cmToPx(pxToCm(100))).toBeLessThanOrEqual(101);
        expect(cmToPx(pxToCm(1000))).toBeGreaterThanOrEqual(999);
        expect(cmToPx(pxToCm(1000))).toBeLessThanOrEqual(1002);
    });
});

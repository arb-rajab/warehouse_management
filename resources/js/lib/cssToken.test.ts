import { afterEach, describe, expect, it } from 'vitest';
import { readCssToken } from './cssToken';

afterEach(() => {
    document.documentElement.removeAttribute('style');
});

describe('readCssToken', () => {
    it('reads a custom property from the document root, trimmed', () => {
        document.documentElement.style.setProperty('--brand', ' #c4170c ');

        expect(readCssToken('--brand')).toBe('#c4170c');
    });

    it('falls back when the token is not set', () => {
        expect(readCssToken('--missing')).toBe('');
        expect(readCssToken('--missing', 'currentColor')).toBe('currentColor');
    });
});

import { describe, expect, it } from 'vitest';
import ar from './ar.json';
import en from './en.json';

function keysOf(value: unknown, prefix = ''): string[] {
    if (typeof value !== 'object' || value === null) {
        return [prefix];
    }

    return Object.entries(value).flatMap(([key, child]) =>
        keysOf(child, prefix === '' ? key : `${prefix}.${key}`),
    );
}

describe('locale files', () => {
    it('define the same keys in English and Arabic', () => {
        const english = new Set(keysOf(en));
        const arabic = new Set(keysOf(ar));

        expect([...english].filter((key) => !arabic.has(key))).toEqual([]);
        expect([...arabic].filter((key) => !english.has(key))).toEqual([]);
    });

    it('translate the receipts keys in both languages', () => {
        expect(keysOf(en).filter((key) => key.startsWith('receipts.'))).toEqual(
            keysOf(ar).filter((key) => key.startsWith('receipts.')),
        );
        expect(ar.receipts.show.title).not.toBe(en.receipts.show.title);
    });
});

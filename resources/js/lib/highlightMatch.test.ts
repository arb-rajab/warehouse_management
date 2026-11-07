import { describe, expect, it } from 'vitest';
import { initialsOf, splitMatches } from './highlightMatch';

describe('splitMatches', () => {
    it('returns the whole text unmatched for an empty query', () => {
        expect(splitMatches('Basmati Rice', '')).toEqual([
            { text: 'Basmati Rice', match: false },
        ]);
        expect(splitMatches('Basmati Rice', '   ')).toEqual([
            { text: 'Basmati Rice', match: false },
        ]);
    });

    it('returns nothing for an empty text', () => {
        expect(splitMatches('', 'rice')).toEqual([]);
    });

    it('marks every case-insensitive match of every word', () => {
        expect(splitMatches('Basmati Rice 5kg', 'rice bas')).toEqual([
            { text: 'Bas', match: true },
            { text: 'mati ', match: false },
            { text: 'Rice', match: true },
            { text: ' 5kg', match: false },
        ]);
    });

    it('keeps the text as it was typed, never as markup', () => {
        expect(splitMatches('<b>Rice</b>', 'rice')).toEqual([
            { text: '<b>', match: false },
            { text: 'Rice', match: true },
            { text: '</b>', match: false },
        ]);
    });

    it('treats regular expression characters in the query literally', () => {
        expect(splitMatches('a.b (c) a+b', 'a.b (c')).toEqual([
            { text: 'a.b', match: true },
            { text: ' ', match: false },
            { text: '(c', match: true },
            { text: ') a+b', match: false },
        ]);
    });

    it('prefers the longer word when words overlap', () => {
        expect(splitMatches('Alina', 'ali alina')).toEqual([
            { text: 'Alina', match: true },
        ]);
    });
});

describe('initialsOf', () => {
    it.each([
        ['Ali Hassan Trading', 'AH'],
        ['alina', 'AL'],
        ['  Salim   Alkhatib ', 'SA'],
        ['علي حسن', 'ع' + 'ح'],
        ['', ''],
    ])('%s gives %s', (label, initials) => {
        expect(initialsOf(label)).toBe(initials);
    });
});

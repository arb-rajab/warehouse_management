import { describe, expect, it } from 'vitest';
import { googleMapsUrl, joinAddressParts } from '@/lib/address';

describe('joinAddressParts', () => {
    it('joins non-empty parts with a comma and drops blanks', () => {
        expect(
            joinAddressParts(['Main St 1', null, ' ', undefined, ' Berlin ']),
        ).toBe('Main St 1, Berlin');
    });

    it('returns an empty string when there are no parts', () => {
        expect(joinAddressParts([null, ''])).toBe('');
    });
});

describe('googleMapsUrl', () => {
    it('encodes the address into the query', () => {
        expect(googleMapsUrl('A&B 5, Köln')).toBe(
            'https://www.google.com/maps/search/?api=1&query=A%26B%205%2C%20K%C3%B6ln',
        );
    });
});

import { describe, expect, it } from 'vitest';
import { buildMatchIndex } from '../domain/MatchIndex';
import type { Slot } from '../domain/types';
import { Tile, safeImageUrl, tileModel } from './TileRenderer';

function slot(level: number, partial: Partial<Slot> = {}): Slot {
    return {
        id: level,
        row: 'A',
        rowIdx: 0,
        col: 3,
        level,
        state: 'full',
        active: true,
        pallet: {
            id: level,
            productId: 1,
            expirationDate: null,
            productName: 'Rice',
            productArName: '',
            productImageUrl: null,
            remainingBoxes: null,
            productBoxesCount: null,
        },
        code: `A3- ${level}`,
        haystack: 'rice',
        expiresInDays: 10,
        ...partial,
    };
}

const empty = (level: number) =>
    slot(level, { state: 'empty', pallet: null, haystack: '' });
const noMatch = buildMatchIndex([], {
    terms: [],
    state: 'all',
    expiring: false,
});

describe('tileModel', () => {
    it('is green when sealed, amber when any shelf is opened, grey when empty', () => {
        expect(
            tileModel({
                column: [slot(1), slot(2)],
                match: noMatch,
                floor: null,
                isTarget: false,
            }).kind,
        ).toBe('full');
        expect(
            tileModel({
                column: [slot(1), slot(2, { state: 'opened' })],
                match: noMatch,
                floor: null,
                isTarget: false,
            }).kind,
        ).toBe('opened');
        expect(
            tileModel({
                column: [empty(1), empty(2)],
                match: noMatch,
                floor: null,
                isTarget: false,
            }).kind,
        ).toBe('empty');
    });

    it('draws one bar per shelf: sealed, opened, empty and out of service', () => {
        const model = tileModel({
            column: [
                slot(1),
                slot(2, { state: 'opened' }),
                empty(3),
                slot(4, { active: false }),
            ],
            match: noMatch,
            floor: null,
            isTarget: false,
        });

        expect(model.levels).toEqual(['f', 'o', '', 'x']);
        expect(model.inactive).toBe(true);
    });

    it('looks only at the chosen floor, but still draws every shelf bar', () => {
        const model = tileModel({
            column: [slot(1), slot(2, { state: 'opened' }), empty(3)],
            match: noMatch,
            floor: 1,
            isTarget: false,
        });

        expect(model.kind).toBe('full');
        expect(model.levels).toHaveLength(3);
        expect(
            tileModel({
                column: [slot(1), empty(2)],
                match: noMatch,
                floor: 2,
                isTarget: false,
            }).kind,
        ).toBe('empty');
    });

    it('highlights matching tiles and dims the rest while filtering', () => {
        const column = [slot(1), slot(2)];
        const rice = buildMatchIndex(column, {
            terms: ['rice'],
            state: 'all',
            expiring: false,
        });
        const sugar = buildMatchIndex(column, {
            terms: ['sugar'],
            state: 'all',
            expiring: false,
        });

        expect(
            tileModel({ column, match: rice, floor: null, isTarget: false }),
        ).toMatchObject({ matched: true, dimmed: false });
        expect(
            tileModel({ column, match: sugar, floor: null, isTarget: false }),
        ).toMatchObject({ matched: false, dimmed: true });
        expect(
            tileModel({ column, match: noMatch, floor: null, isTarget: false }),
        ).toMatchObject({ matched: false, dimmed: false });
    });

    it('shows a product picture only for tiles that are not filtered away', () => {
        const withImage = slot(1);

        withImage.pallet!.productImageUrl = 'https://cdn.example/rice.png';

        const hit = buildMatchIndex([withImage], {
            terms: ['rice'],
            state: 'all',
            expiring: false,
        });
        const miss = buildMatchIndex([withImage], {
            terms: ['sugar'],
            state: 'all',
            expiring: false,
        });

        expect(
            tileModel({
                column: [withImage],
                match: noMatch,
                floor: null,
                isTarget: false,
            }).image,
        ).toBe('https://cdn.example/rice.png');
        expect(
            tileModel({
                column: [withImage],
                match: hit,
                floor: null,
                isTarget: false,
            }).image,
        ).not.toBeNull();
        expect(
            tileModel({
                column: [withImage],
                match: miss,
                floor: null,
                isTarget: false,
            }).image,
        ).toBeNull();
    });
});

describe('safeImageUrl', () => {
    it('accepts web and site-relative urls and escapes what could break out of url("")', () => {
        expect(safeImageUrl('https://cdn.example/a b.png')).toBe(
            'https://cdn.example/a%20b.png',
        );
        expect(safeImageUrl('//cdn.example/a.png')).toBe('//cdn.example/a.png');
        expect(safeImageUrl('/storage/a.png')).toBe('/storage/a.png');
        expect(
            safeImageUrl('https://x/a.png"); background:red; ("'),
        ).not.toMatch(/["()]/);
    });

    it('refuses anything else', () => {
        expect(safeImageUrl('javascript:alert(1)')).toBeNull();
        expect(safeImageUrl('data:image/png;base64,AAAA')).toBeNull();
        expect(safeImageUrl('')).toBeNull();
        expect(safeImageUrl(null)).toBeNull();
    });
});

describe('Tile', () => {
    it('is a button labelled with its place', () => {
        const tile = new Tile('B', 7);

        expect(tile.element.tagName).toBe('BUTTON');
        expect(tile.element.getAttribute('aria-label')).toBe('B7');
        expect(tile.element.dataset.row).toBe('B');
        expect(tile.element.dataset.col).toBe('7');
        expect(tile.element.textContent).toBe('7');
    });

    it('applies state as classes and rebuilds the shelf bars only when they change', () => {
        const tile = new Tile('A', 1);
        const model = tileModel({
            column: [slot(1, { active: false }), empty(2)],
            match: noMatch,
            floor: null,
            isTarget: false,
        });

        tile.apply(model, null);
        expect(tile.element.className).toContain('wms-tile--full');
        expect(tile.element.className).toContain('is-inactive');
        expect(tile.element.querySelectorAll('.wms-tile__level')).toHaveLength(
            2,
        );
        expect(
            tile.element.querySelector('.wms-tile__level--x'),
        ).not.toBeNull();

        const bar = tile.element.querySelector('.wms-tile__level');

        tile.apply({ ...model, dimmed: true }, null);
        expect(tile.element.querySelector('.wms-tile__level')).toBe(bar);
        expect(tile.element.className).toContain('is-dim');
    });

    it('shows the target code only while it is the target', () => {
        const tile = new Tile('A', 1);
        const model = tileModel({
            column: [slot(1)],
            match: noMatch,
            floor: null,
            isTarget: true,
        });

        tile.apply(model, 'A1- 1');
        expect(tile.element.querySelector('.wms-tile__code')?.textContent).toBe(
            'A1- 1',
        );
        expect(tile.element.className).toContain('is-target');

        tile.apply({ ...model, target: false }, null);
        expect(tile.element.querySelector('.wms-tile__code')).toBeNull();
    });

    it('sets the picture through a CSS variable, never from raw text', () => {
        const tile = new Tile('A', 1);
        const model = tileModel({
            column: [slot(1)],
            match: noMatch,
            floor: null,
            isTarget: false,
        });

        tile.apply({ ...model, image: 'https://cdn.example/a.png' }, null);
        expect(tile.element.style.getPropertyValue('--wms-tile-image')).toBe(
            'url("https://cdn.example/a.png")',
        );

        tile.apply({ ...model, image: null }, null);
        expect(tile.element.style.getPropertyValue('--wms-tile-image')).toBe(
            '',
        );
    });
});

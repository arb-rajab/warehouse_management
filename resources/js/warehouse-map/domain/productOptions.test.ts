import { describe, expect, it } from 'vitest';
import { normalizeRows } from './normalizeRows';
import { filterProductOptions, listProductOptions } from './productOptions';
import type { ApiPallet } from './types';

function pallet(id: number, productId: number, extra: Partial<ApiPallet> = {}) {
    return {
        id,
        product_id: productId,
        expiration_date: null,
        product_name: `Product ${productId}`,
        product_ar_name: `منتج ${productId}`,
        ...extra,
    };
}

const warehouse = normalizeRows([
    {
        id: 1,
        letter: 'A',
        cells: [
            {
                id: 1,
                cell_number: 1,
                flat_number: 1,
                state: 'full',
                pallet: pallet(10, 7),
            },
            {
                id: 2,
                cell_number: 1,
                flat_number: 2,
                state: 'opened',
                pallet: pallet(11, 3),
            },
            {
                id: 3,
                cell_number: 2,
                flat_number: 1,
                state: 'full',
                pallet: pallet(12, 7),
            },
            {
                id: 4,
                cell_number: 2,
                flat_number: 2,
                state: 'full',
                pallet: pallet(13, 5, {
                    product_name: 'Old Oil',
                    product_ar_name: '',
                    product_active: false,
                }),
            },
            {
                id: 5,
                cell_number: 3,
                flat_number: 1,
                state: 'empty',
                pallet: null,
            },
        ],
    },
]);

describe('listProductOptions', () => {
    it('lists every stored product once, with how many slots hold it and whether it is still sold', () => {
        expect(listProductOptions(warehouse.slots)).toEqual([
            {
                id: 3,
                name: 'Product 3',
                arName: 'منتج 3',
                active: true,
                pallets: 1,
            },
            { id: 5, name: 'Old Oil', arName: '', active: false, pallets: 1 },
            {
                id: 7,
                name: 'Product 7',
                arName: 'منتج 7',
                active: true,
                pallets: 2,
            },
        ]);
    });

    it('lists nothing for a warehouse without pallets', () => {
        expect(listProductOptions([])).toEqual([]);
    });
});

describe('filterProductOptions', () => {
    const options = listProductOptions(warehouse.slots);
    const ids = (query: string) =>
        filterProductOptions(options, query).map((o) => o.id);

    it('keeps every option for an empty query', () => {
        expect(ids('  ')).toEqual([3, 5, 7]);
    });

    it('matches every typed word against either name or the id', () => {
        expect(ids('old')).toEqual([5]);
        expect(ids('منتج 7')).toEqual([7]);
        expect(ids('#3')).toEqual([3]);
        expect(ids('product oil')).toEqual([]);
    });
});

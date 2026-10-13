import { normalizeRows } from '../domain/normalizeRows';
import type { ApiRow, Warehouse } from '../domain/types';

/** Deterministic pseudo-random numbers, so a fixture is the same on every run. */
function random(seed: number): () => number {
    let state = seed;

    return () => {
        state = (state * 1664525 + 1013904223) % 4294967296;

        return state / 4294967296;
    };
}

const PRODUCTS = [
    { id: 1, name: 'Basmati Rice 5 kg', ar: 'أرز بسمتي 5 كجم', boxes: 20 },
    { id: 2, name: 'Sunflower Oil 1.8 L', ar: 'زيت عباد الشمس', boxes: 12 },
    { id: 3, name: 'White Sugar 2 kg', ar: 'سكر أبيض', boxes: 24 },
    { id: 4, name: 'Tomato Paste 400 g', ar: 'معجون طماطم', boxes: 48 },
];

export const rowLetter = (i: number): string =>
    i < 26
        ? String.fromCharCode(65 + i)
        : String.fromCharCode(64 + Math.floor(i / 26)) +
          String.fromCharCode(65 + (i % 26));

/** Rows in the exact JSON shape of `GET /rows/full`. */
export function apiRows(
    rows: number,
    cols: number,
    levels: number,
    seed = 7,
): ApiRow[] {
    const next = random(seed);
    let cellId = 1;
    let palletId = 1000;
    const day = 86_400_000;
    const now = new Date('2026-10-13T12:00:00').getTime();

    return Array.from({ length: rows }, (_, r) => {
        const letter = rowLetter(r);
        const cells: ApiRow['cells'] = [];

        for (let c = 1; c <= cols; c++) {
            for (let l = 1; l <= levels; l++) {
                const roll = next();
                const state =
                    roll < 0.12 ? 'empty' : roll < 0.75 ? 'sealed' : 'opened';
                const product = PRODUCTS[Math.floor(next() * PRODUCTS.length)];
                const expiry = new Date(
                    now + Math.round(-5 + next() * 300) * day,
                )
                    .toISOString()
                    .slice(0, 10);

                cells!.push({
                    id: cellId++,
                    cell_number: c,
                    flat_number: l,
                    state,
                    is_active: next() > 0.03,
                    pallet:
                        state === 'empty'
                            ? null
                            : {
                                  id: palletId++,
                                  product_id: product.id,
                                  expiration_date: expiry,
                                  product_name: product.name,
                                  product_ar_name: product.ar,
                                  product_image_url: null,
                                  remaining_boxes:
                                      state === 'opened'
                                          ? Math.max(
                                                1,
                                                Math.round(
                                                    product.boxes * next(),
                                                ),
                                            )
                                          : product.boxes,
                                  product_boxes_count: product.boxes,
                              },
                });
            }
        }

        return { id: r + 1, letter, cells };
    });
}

export function makeWarehouse(
    rows = 4,
    cols = 6,
    levels = 3,
    seed = 7,
): Warehouse {
    return normalizeRows(
        apiRows(rows, cols, levels, seed),
        new Date('2026-10-13T12:00:00').getTime(),
    );
}

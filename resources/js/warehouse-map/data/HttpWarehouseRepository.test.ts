import { describe, expect, it, vi } from 'vitest';
import { HttpWarehouseRepository } from './HttpWarehouseRepository';
import type { FetchLike } from './HttpWarehouseRepository';

const NOW = new Date('2026-10-13T12:00:00').getTime();
const rows = [
    {
        id: 1,
        letter: 'A',
        cells: [
            {
                id: 1,
                cell_number: 1,
                flat_number: 1,
                state: 'empty',
                pallet: null,
            },
        ],
    },
];

function response(
    status: number,
    body: unknown = rows,
    etag: string | null = '"v1"',
) {
    return {
        ok: status >= 200 && status < 300,
        status,
        headers: new Headers(etag ? { ETag: etag } : {}),
        json: async () => body,
    };
}

describe('HttpWarehouseRepository', () => {
    it('loads and normalises the rows, asking for JSON with the session cookie', async () => {
        const fetcher = vi.fn<FetchLike>().mockResolvedValue(response(200));
        const repo = new HttpWarehouseRepository('/rows', fetcher, () => NOW);

        const snapshot = await repo.load();

        expect(snapshot.changed).toBe(true);
        expect(snapshot.warehouse.slots).toHaveLength(1);
        expect(fetcher).toHaveBeenCalledWith('/rows', {
            headers: expect.objectContaining({ Accept: 'application/json' }),
            credentials: 'same-origin',
        });
        expect(fetcher.mock.calls[0][1]!.headers).not.toHaveProperty(
            'If-None-Match',
        );
    });

    it('revalidates with the ETag and keeps the data on a 304', async () => {
        const fetcher = vi
            .fn<FetchLike>()
            .mockResolvedValueOnce(response(200))
            .mockResolvedValueOnce(response(304, null, null));
        const repo = new HttpWarehouseRepository('/rows', fetcher, () => NOW);

        const first = await repo.load();
        const second = await repo.load();

        expect(fetcher.mock.calls[1][1]!.headers).toHaveProperty(
            'If-None-Match',
            '"v1"',
        );
        expect(second.changed).toBe(false);
        expect(second.warehouse).toBe(first.warehouse);
    });

    it('takes the new data when the server sends a fresh body', async () => {
        const fetcher = vi
            .fn<FetchLike>()
            .mockResolvedValueOnce(response(200))
            .mockResolvedValueOnce(response(200, [], '"v2"'));
        const repo = new HttpWarehouseRepository('/rows', fetcher, () => NOW);

        await repo.load();
        const second = await repo.load();

        expect(second.changed).toBe(true);
        expect(second.warehouse.slots).toHaveLength(0);
    });

    it('fails on an error response', async () => {
        const fetcher = vi
            .fn<FetchLike>()
            .mockResolvedValue(response(500, null, null));

        await expect(
            new HttpWarehouseRepository('/rows', fetcher).load(),
        ).rejects.toThrow(/500/);
    });
});

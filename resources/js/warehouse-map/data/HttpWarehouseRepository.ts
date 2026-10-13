import { normalizeRows } from '../domain/normalizeRows';
import type { ApiRow, Warehouse } from '../domain/types';
import type {
    WarehouseRepository,
    WarehouseSnapshot,
} from './WarehouseRepository';

export type FetchLike = (
    input: string,
    init?: RequestInit,
) => Promise<Pick<Response, 'ok' | 'status' | 'headers' | 'json'>>;

/**
 * Loads `GET /rows/full`-shaped JSON. After the first load it revalidates with
 * `If-None-Match`, so refetching when the tab regains focus costs a 304 and no
 * re-render while the warehouse is unchanged. (Sending the header ourselves is
 * what lets the page see the 304; the browser cache would otherwise swallow it.)
 */
export class HttpWarehouseRepository implements WarehouseRepository {
    private etag: string | null = null;
    private current: Warehouse | null = null;

    constructor(
        private readonly url: string,
        private readonly fetcher: FetchLike = (input, init) =>
            fetch(input, init),
        private readonly now: () => number = Date.now,
    ) {}

    async load(): Promise<WarehouseSnapshot> {
        const headers: Record<string, string> = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };

        if (this.etag && this.current) {
            headers['If-None-Match'] = this.etag;
        }

        const response = await this.fetcher(this.url, {
            headers,
            credentials: 'same-origin',
        });

        if (response.status === 304 && this.current) {
            return { warehouse: this.current, changed: false };
        }

        if (!response.ok) {
            throw new Error(`Warehouse request failed (${response.status})`);
        }

        const payload = (await response.json()) as
            ApiRow[] | { data: ApiRow[] };

        this.current = normalizeRows(payload, this.now());
        this.etag = response.headers.get('ETag');

        return { warehouse: this.current, changed: true };
    }
}

import { describe, expect, it } from 'vitest';
import { paginated } from './factories';

describe('paginated', () => {
    it('sets from/to/total for non-empty data', () => {
        const result = paginated(['a', 'b', 'c'], 20);

        expect(result.meta).toMatchObject({
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 3,
            from: 1,
            to: 3,
        });
    });

    it('sets from to null and to/total to 0 for empty data', () => {
        const result = paginated([]);

        expect(result.meta).toMatchObject({
            total: 0,
            from: null,
            to: 0,
        });
    });
});

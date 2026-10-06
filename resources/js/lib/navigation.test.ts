import { describe, expect, it } from 'vitest';
import { isActiveNavUrl } from '@/lib/navigation';

const siblings = ['/admin', '/admin/rows', '/admin/users'];

describe('isActiveNavUrl', () => {
    it('matches the exact url', () => {
        expect(isActiveNavUrl('/admin/rows', '/admin/rows', siblings)).toBe(
            true,
        );
    });

    it('matches a nested path under the url', () => {
        expect(
            isActiveNavUrl('/admin/rows/A/edit', '/admin/rows', siblings),
        ).toBe(true);
    });

    it('ignores the query string', () => {
        expect(
            isActiveNavUrl('/admin/rows?per_page=50', '/admin/rows', siblings),
        ).toBe(true);
    });

    it('does not match a mere string prefix', () => {
        expect(isActiveNavUrl('/admin/rowsx', '/admin/rows', siblings)).toBe(
            false,
        );
    });

    it('does not match an unrelated page', () => {
        expect(isActiveNavUrl('/admin/users', '/admin/rows', siblings)).toBe(
            false,
        );
    });

    it('lets a longer matching sibling win over a shorter prefix url', () => {
        expect(isActiveNavUrl('/admin/rows', '/admin', siblings)).toBe(false);
        expect(isActiveNavUrl('/admin', '/admin', siblings)).toBe(true);
    });

    it('compares a link that carries a query string on its path', () => {
        expect(
            isActiveNavUrl(
                '/temp/admin/website/custom-pages/edit/home?page=home',
                '/temp/admin/website/custom-pages/edit/home?page=home',
                ['/temp/admin'],
            ),
        ).toBe(true);
    });
});

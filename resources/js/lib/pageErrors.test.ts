import { beforeEach, describe, expect, it, vi } from 'vitest';
import { firstError, usePageError } from './pageErrors';

const { usePageMock } = vi.hoisted(() => ({ usePageMock: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({ usePage: usePageMock }));

describe('usePageError', () => {
    beforeEach(() => {
        usePageMock.mockReset();
    });

    it('returns the error for the given key', () => {
        usePageMock.mockReturnValue({
            props: { errors: { action: 'Nope', row: 'Other' } },
        });

        expect(usePageError('action').value).toBe('Nope');
    });

    it('returns undefined when that key has no error', () => {
        usePageMock.mockReturnValue({ props: { errors: { row: 'Other' } } });

        expect(usePageError('action').value).toBeUndefined();
    });

    it('returns undefined when the page has no errors bag', () => {
        usePageMock.mockReturnValue({ props: {} });

        expect(usePageError('action').value).toBeUndefined();
    });
});

describe('firstError', () => {
    it('returns the first message', () => {
        expect(firstError({ a: 'First', b: 'Second' })).toBe('First');
    });

    it('returns undefined for an empty bag', () => {
        expect(firstError({})).toBeUndefined();
    });
});

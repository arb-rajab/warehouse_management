import { afterEach, describe, expect, it, vi } from 'vitest';
import { confirmDelete } from './confirm';

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('confirmDelete', () => {
    it('asks with the shared irreversible-delete wording', () => {
        const confirmSpy = vi.fn(() => true);
        vi.stubGlobal('confirm', confirmSpy);

        confirmDelete('row Z');

        expect(confirmSpy).toHaveBeenCalledWith(
            'Delete row Z? This cannot be undone.',
        );
    });

    it('passes the user’s answer straight back to the caller', () => {
        vi.stubGlobal('confirm', () => false);
        expect(confirmDelete('Jane Doe')).toBe(false);

        vi.stubGlobal('confirm', () => true);
        expect(confirmDelete('Jane Doe')).toBe(true);
    });
});

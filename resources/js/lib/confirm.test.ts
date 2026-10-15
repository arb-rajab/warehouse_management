import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    confirmAcceptVerificationReport,
    confirmDelete,
    confirmDeleteUser,
    confirmLogout,
} from './confirm';

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

describe('confirmDeleteUser', () => {
    it('says the user loses access but keeps their history', () => {
        const confirmSpy = vi.fn(() => true);
        vi.stubGlobal('confirm', confirmSpy);

        confirmDeleteUser('Jane Doe');

        expect(confirmSpy).toHaveBeenCalledWith(
            'Remove Jane Doe? They will lose access to the system immediately, but their activity history is kept.',
        );
    });

    it('passes the user’s answer straight back to the caller', () => {
        vi.stubGlobal('confirm', () => false);
        expect(confirmDeleteUser('Jane Doe')).toBe(false);

        vi.stubGlobal('confirm', () => true);
        expect(confirmDeleteUser('Jane Doe')).toBe(true);
    });
});

describe('confirmLogout', () => {
    it('asks with the shared logout wording', () => {
        const confirmSpy = vi.fn(() => true);
        vi.stubGlobal('confirm', confirmSpy);

        confirmLogout();

        expect(confirmSpy).toHaveBeenCalledWith('Log out of your account?');
    });

    it('passes the user’s answer straight back to the caller', () => {
        vi.stubGlobal('confirm', () => false);
        expect(confirmLogout()).toBe(false);

        vi.stubGlobal('confirm', () => true);
        expect(confirmLogout()).toBe(true);
    });
});

describe('confirmAcceptVerificationReport', () => {
    it('asks with the shared accept wording, naming the slot', () => {
        const confirmSpy = vi.fn(() => true);
        vi.stubGlobal('confirm', confirmSpy);

        confirmAcceptVerificationReport('A3·2');

        expect(confirmSpy).toHaveBeenCalledWith(
            'Update A3·2 to match what was reported? The report stays marked incorrect, along with the date it was accepted.',
        );
    });

    it('passes the user’s answer straight back to the caller', () => {
        vi.stubGlobal('confirm', () => false);
        expect(confirmAcceptVerificationReport('A3·2')).toBe(false);

        vi.stubGlobal('confirm', () => true);
        expect(confirmAcceptVerificationReport('A3·2')).toBe(true);
    });
});

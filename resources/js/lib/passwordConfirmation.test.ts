import { afterEach, describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import { usePasswordConfirmationMismatch } from './passwordConfirmation';

function appendInput(id: string): HTMLInputElement {
    const input = document.createElement('input');
    input.id = id;
    document.body.appendChild(input);

    return input;
}

describe('usePasswordConfirmationMismatch', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('has no mismatch error before any input is synced', () => {
        const { confirmationMismatchError } = usePasswordConfirmationMismatch();

        expect(confirmationMismatchError.value).toBeUndefined();
    });

    it('flags a mismatch as a computed error and via native validity', () => {
        const password = appendInput('password');
        const confirmation = appendInput('password_confirmation');
        const { confirmationMismatchError, syncConfirmationValidity } =
            usePasswordConfirmationMismatch();

        password.value = 'Password123!';
        confirmation.value = 'Different123!';
        syncConfirmationValidity();

        expect(confirmationMismatchError.value).toBe(
            t('users.fields.passwordMismatch'),
        );
        expect(confirmation.validationMessage).not.toBe('');
    });

    it('clears the mismatch once the confirmation matches the password', () => {
        const password = appendInput('password');
        const confirmation = appendInput('password_confirmation');
        const { confirmationMismatchError, syncConfirmationValidity } =
            usePasswordConfirmationMismatch();

        password.value = 'Password123!';
        confirmation.value = 'Different123!';
        syncConfirmationValidity();
        expect(confirmationMismatchError.value).not.toBeUndefined();

        confirmation.value = 'Password123!';
        syncConfirmationValidity();

        expect(confirmationMismatchError.value).toBeUndefined();
        expect(confirmation.validationMessage).toBe('');
    });

    it('does nothing when the referenced elements are not in the document', () => {
        const { confirmationMismatchError, syncConfirmationValidity } =
            usePasswordConfirmationMismatch(
                'missing-password',
                'missing-confirmation',
            );

        expect(() => syncConfirmationValidity()).not.toThrow();
        expect(confirmationMismatchError.value).toBeUndefined();
    });

    it('supports custom element ids for a second field pair on the same page', () => {
        const password = appendInput('custom-password');
        const confirmation = appendInput('custom-confirmation');
        const { confirmationMismatchError, syncConfirmationValidity } =
            usePasswordConfirmationMismatch(
                'custom-password',
                'custom-confirmation',
            );

        password.value = 'abc';
        confirmation.value = 'xyz';
        syncConfirmationValidity();

        expect(confirmationMismatchError.value).toBe(
            t('users.fields.passwordMismatch'),
        );
    });
});

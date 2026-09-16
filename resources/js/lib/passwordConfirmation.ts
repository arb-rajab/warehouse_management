import { computed, ref } from 'vue';
import { t } from '@/lib/i18n';

/**
 * Live "passwords match" check shared by every password + confirmation field
 * pair (UserFormFields.vue, ChangePassword.vue): mirrors the backend's
 * `confirmed` rule via `setCustomValidity` on the confirmation input (native
 * browser validation) alongside a computed error string matching every other
 * field's error styling.
 */
export function usePasswordConfirmationMismatch(
    passwordId = 'password',
    confirmationId = 'password_confirmation',
) {
    const passwordValue = ref('');
    const confirmationValue = ref('');

    const confirmationMismatchError = computed(() =>
        confirmationValue.value &&
        confirmationValue.value !== passwordValue.value
            ? t('users.fields.passwordMismatch')
            : undefined,
    );

    function syncConfirmationValidity(): void {
        const password = document.getElementById(
            passwordId,
        ) as HTMLInputElement | null;
        const confirmation = document.getElementById(
            confirmationId,
        ) as HTMLInputElement | null;

        if (!password || !confirmation) {
            return;
        }

        passwordValue.value = password.value;
        confirmationValue.value = confirmation.value;

        confirmation.setCustomValidity(
            confirmation.value && confirmation.value !== password.value
                ? t('users.fields.passwordMismatch')
                : '',
        );
    }

    return { confirmationMismatchError, syncConfirmationValidity };
}

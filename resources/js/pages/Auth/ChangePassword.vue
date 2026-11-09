<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { update } from '@/actions/App/Http/Controllers/PasswordChangeController';
import AuthCard from '@/components/AuthCard.vue';
import FormField from '@/components/FormField.vue';
import LogoutLink from '@/components/LogoutLink.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { t } from '@/lib/i18n';
import { usePasswordConfirmationMismatch } from '@/lib/passwordConfirmation';

const { confirmationMismatchError, syncConfirmationValidity } =
    usePasswordConfirmationMismatch();
</script>

<template>
    <Head :title="t('auth.changePassword.title')" />

    <AuthCard :area="t('auth.changePassword.brand')">
        <h2 class="mb-1 text-base font-medium">
            {{ t('auth.changePassword.title') }}
        </h2>
        <p class="mb-6 text-sm text-muted">
            {{ t('auth.changePassword.description') }}
        </p>

        <Form
            :action="update()"
            #default="{ errors, processing }"
            class="space-y-4"
        >
            <FormField
                id="current_password"
                :label="t('auth.changePassword.currentPassword')"
                type="password"
                :error="errors.current_password"
                maxlength="255"
                autocomplete="current-password"
                required
            />

            <FormField
                id="password"
                :label="t('auth.changePassword.password')"
                type="password"
                :error="errors.password"
                maxlength="255"
                autocomplete="new-password"
                required
                @input="syncConfirmationValidity"
            />

            <FormField
                id="password_confirmation"
                :label="t('auth.changePassword.confirmPassword')"
                type="password"
                :error="confirmationMismatchError"
                maxlength="255"
                autocomplete="new-password"
                required
                @input="syncConfirmationValidity"
            />

            <SubmitButton
                :label="t('auth.changePassword.submit')"
                :processing-label="t('auth.changePassword.submitting')"
                :processing="processing"
            />
        </Form>

        <LogoutLink
            class="mt-4 flex w-full cursor-pointer items-center justify-center gap-1 text-center text-sm text-muted hover:text-ink"
        />
    </AuthCard>
</template>

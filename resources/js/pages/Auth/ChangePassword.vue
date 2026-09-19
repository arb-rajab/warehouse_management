<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Warehouse } from '@lucide/vue';
import { update } from '@/actions/App/Http/Controllers/PasswordChangeController';
import FormField from '@/components/FormField.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import LogoutLink from '@/components/LogoutLink.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { t } from '@/lib/i18n';
import { usePasswordConfirmationMismatch } from '@/lib/passwordConfirmation';

const { confirmationMismatchError, syncConfirmationValidity } =
    usePasswordConfirmationMismatch();
</script>

<template>
    <Head :title="t('auth.changePassword.title')" />

    <div
        class="flex min-h-screen items-center justify-center bg-gray-50 text-gray-900 dark:bg-neutral-950 dark:text-neutral-100"
    >
        <div
            class="w-full max-w-sm rounded-lg border border-gray-200 bg-white p-8 shadow-sm dark:border-neutral-800 dark:bg-neutral-900"
        >
            <div class="mb-6 flex items-center justify-between">
                <h1
                    class="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-neutral-100"
                >
                    <Warehouse class="h-5 w-5 shrink-0" />
                    {{ t('auth.changePassword.brand') }}
                </h1>
                <LanguageSwitcher />
            </div>

            <h2 class="mb-1 text-base font-medium">
                {{ t('auth.changePassword.title') }}
            </h2>
            <p class="mb-6 text-sm text-gray-600 dark:text-neutral-400">
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
                class="mt-4 flex w-full cursor-pointer items-center justify-center gap-1 text-center text-sm text-gray-600 hover:text-gray-900 dark:text-neutral-400 dark:hover:text-white"
            />
        </div>
    </div>
</template>

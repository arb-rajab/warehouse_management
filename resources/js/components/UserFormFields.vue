<script setup lang="ts">
import { CircleAlert, TriangleAlert } from '@lucide/vue';
import { t } from '@/lib/i18n';
import { usePasswordConfirmationMismatch } from '@/lib/passwordConfirmation';
import FormField from './FormField.vue';

withDefaults(
    defineProps<{
        name?: string;
        email?: string;
        isAdmin?: boolean;
        passwordRequired?: boolean;
        disableAdminToggle?: boolean;
        errors: Partial<
            Record<'name' | 'email' | 'password' | 'is_admin', string>
        >;
    }>(),
    {
        name: '',
        email: '',
        isAdmin: false,
        passwordRequired: false,
        disableAdminToggle: false,
    },
);

const { confirmationMismatchError, syncConfirmationValidity } =
    usePasswordConfirmationMismatch();
</script>

<template>
    <div class="space-y-4">
        <FormField
            id="name"
            :label="t('users.fields.name')"
            :value="name"
            :error="errors.name"
            maxlength="255"
            required
        />

        <FormField
            id="email"
            :label="t('users.fields.email')"
            type="email"
            :value="email"
            :error="errors.email"
            maxlength="255"
            required
        />

        <FormField
            id="password"
            :label="
                passwordRequired
                    ? t('users.fields.password')
                    : t('users.fields.newPassword')
            "
            type="password"
            :error="errors.password"
            maxlength="255"
            :required="passwordRequired"
            :placeholder="
                passwordRequired ? '' : t('users.fields.passwordPlaceholder')
            "
            @input="syncConfirmationValidity"
        />

        <FormField
            id="password_confirmation"
            :label="t('users.fields.confirmPassword')"
            type="password"
            :error="confirmationMismatchError"
            maxlength="255"
            :required="passwordRequired"
            @input="syncConfirmationValidity"
        />

        <div>
            <label
                class="flex items-center gap-2 text-sm text-gray-700 dark:text-neutral-300"
            >
                <input type="hidden" name="is_admin" value="0" />
                <input
                    type="checkbox"
                    name="is_admin"
                    value="1"
                    :checked.attr="isAdmin"
                    class="rounded border-gray-300 dark:border-neutral-700 dark:bg-neutral-800"
                    :class="{
                        'cursor-not-allowed opacity-50': disableAdminToggle,
                    }"
                    @click="disableAdminToggle && $event.preventDefault()"
                />
                {{ t('users.fields.adminAccess') }}
            </label>
            <p
                v-if="errors.is_admin"
                class="mt-1 flex items-center gap-1 text-sm text-red-600 dark:text-red-400"
            >
                <CircleAlert class="h-3.5 w-3.5 shrink-0" />
                {{ errors.is_admin }}
            </p>
            <p
                v-else-if="disableAdminToggle"
                class="mt-1 flex items-center gap-1 text-sm text-amber-600 dark:text-amber-400"
            >
                <TriangleAlert class="h-3.5 w-3.5 shrink-0" />
                {{ t('users.fields.disabledAdminHint') }}
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { usePasswordConfirmationMismatch } from '@/lib/passwordConfirmation';
import type { StoreRole } from '@/types/store';

/**
 * The store staff form's fields (Store\Admin\StoreStaffRequest and
 * UpdateStaffRequest): the account's contact details, its store role and a
 * password, required on create and optional on edit.
 */
withDefaults(
    defineProps<{
        roles: StoreRole[];
        name?: string;
        email?: string;
        phone?: string | null;
        roleId?: number | null;
        passwordRequired?: boolean;
        errors: Partial<
            Record<'name' | 'email' | 'mobile' | 'role_id' | 'password', string>
        >;
    }>(),
    {
        name: '',
        email: '',
        phone: '',
        roleId: null,
        passwordRequired: false,
    },
);

const { confirmationMismatchError, syncConfirmationValidity } =
    usePasswordConfirmationMismatch();
</script>

<template>
    <div class="space-y-4">
        <FormField
            id="name"
            :label="t('store.staff.fields.name')"
            :value="name"
            :error="errors.name"
            maxlength="255"
            required
        />

        <FormField
            id="email"
            :label="t('store.staff.fields.email')"
            type="email"
            :value="email"
            :error="errors.email"
            maxlength="255"
            required
        />

        <FormField
            id="mobile"
            :label="t('store.staff.fields.phone')"
            type="tel"
            :value="phone ?? ''"
            :error="errors.mobile"
            maxlength="255"
            required
        />

        <div>
            <label for="role_id" :class="fieldLabelClass">
                {{ t('store.staff.fields.role') }}
            </label>
            <select
                id="role_id"
                name="role_id"
                required
                :class="[plainFieldInputClass, 'cursor-pointer']"
            >
                <option value="" disabled :selected="roleId === null">
                    {{ t('store.staff.fields.rolePlaceholder') }}
                </option>
                <option
                    v-for="role in roles"
                    :key="role.id"
                    :value="role.id"
                    :selected="role.id === roleId"
                >
                    {{ role.name }}
                </option>
            </select>
            <FieldError :message="errors.role_id" />
        </div>

        <FormField
            id="password"
            :label="
                passwordRequired
                    ? t('store.staff.fields.password')
                    : t('store.staff.fields.newPassword')
            "
            type="password"
            :error="errors.password"
            maxlength="255"
            autocomplete="new-password"
            :required="passwordRequired"
            :placeholder="
                passwordRequired
                    ? ''
                    : t('store.staff.fields.passwordPlaceholder')
            "
            @input="syncConfirmationValidity"
        />

        <FormField
            id="password_confirmation"
            :label="t('store.staff.fields.confirmPassword')"
            type="password"
            :error="confirmationMismatchError"
            maxlength="255"
            autocomplete="new-password"
            :required="passwordRequired"
            @input="syncConfirmationValidity"
        />
    </div>
</template>

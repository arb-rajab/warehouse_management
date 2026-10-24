<script setup lang="ts">
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { usePasswordConfirmationMismatch } from '@/lib/passwordConfirmation';
import type { StoreCountry } from '@/types/store';

withDefaults(
    defineProps<{
        countries: Pick<StoreCountry, 'id' | 'name'>[];
        name?: string;
        email?: string | null;
        phone?: string | null;
        country?: string | null;
        editing?: boolean;
        errors: Partial<
            Record<'name' | 'email' | 'phone' | 'country' | 'password', string>
        >;
    }>(),
    { name: '', email: '', phone: '', country: '', editing: false },
);

const { confirmationMismatchError, syncConfirmationValidity } =
    usePasswordConfirmationMismatch();
</script>

<template>
    <div class="space-y-4">
        <FormField
            id="name"
            :label="t('store.drivers.fields.name')"
            :value="name"
            :error="errors.name"
            maxlength="191"
            required
        />
        <FormField
            id="email"
            :label="t('store.drivers.fields.email')"
            type="email"
            :value="email ?? ''"
            :error="errors.email"
            maxlength="191"
            :readonly="editing"
            :required="!editing"
        />
        <FormField
            id="phone"
            :label="t('store.drivers.fields.phone')"
            type="tel"
            :value="phone ?? ''"
            :error="errors.phone"
            maxlength="20"
            required
        />
        <div>
            <label for="country" :class="fieldLabelClass"
                >{{ t('store.drivers.fields.country') }}<RequiredMark
            /></label>
            <select
                id="country"
                name="country"
                required
                :class="[plainFieldInputClass, 'cursor-pointer']"
            >
                <option value="" disabled :selected="!country">
                    {{ t('store.drivers.fields.countryPlaceholder') }}
                </option>
                <option
                    v-if="
                        country &&
                        !countries.some((item) => item.name === country)
                    "
                    :value="country"
                    selected
                >
                    {{ country }}
                </option>
                <option
                    v-for="item in countries"
                    :key="item.id"
                    :value="item.name"
                    :selected="item.name === country"
                >
                    {{ item.name }}
                </option>
            </select>
            <FieldError :message="errors.country" />
        </div>
        <FormField
            id="password"
            :label="
                t(
                    editing
                        ? 'store.drivers.fields.newPassword'
                        : 'store.drivers.fields.password',
                )
            "
            type="password"
            :error="errors.password"
            minlength="6"
            maxlength="255"
            autocomplete="new-password"
            :required="!editing"
            :placeholder="
                editing ? t('store.drivers.fields.passwordPlaceholder') : ''
            "
            @input="syncConfirmationValidity"
        />
        <FormField
            id="password_confirmation"
            :label="t('store.drivers.fields.confirmPassword')"
            type="password"
            :error="confirmationMismatchError"
            minlength="6"
            maxlength="255"
            autocomplete="new-password"
            :required="!editing"
            @input="syncConfirmationValidity"
        />
    </div>
</template>

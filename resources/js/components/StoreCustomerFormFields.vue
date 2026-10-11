<script setup lang="ts">
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { usePasswordConfirmationMismatch } from '@/lib/passwordConfirmation';
import type { AddressOption, CustomerDetails } from '@/types/storeCustomers';
defineProps<{
    customer?: CustomerDetails;
    countries: AddressOption[];
    creating?: boolean;
    representative?: boolean;
    errors: Record<string, string>;
}>();
const { confirmationMismatchError, syncConfirmationValidity } =
    usePasswordConfirmationMismatch();
const fields = [
    'company_name',
    'tax_number',
    'bank_account_number',
    'company_address',
    'shipping_address',
    'member_serial',
] as const;
function validateTradeLicense(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.setCustomValidity(
        file && file.size > 20000 * 1024
            ? t('store.customers.fields.trade_license')
            : '',
    );
}
</script>
<template>
    <div class="grid gap-4 md:grid-cols-2">
        <label v-if="customer && !creating" :class="fieldLabelClass">
            <input type="hidden" name="is_rep" value="0" />
            <input
                type="checkbox"
                name="is_rep"
                value="1"
                :checked="customer.is_rep"
            />
            {{ t('store.customers.representativeAccount') }}
            <FieldError :message="errors.is_rep" />
        </label>
        <FormField
            id="name"
            :label="t('store.customers.fields.name')"
            :value="customer?.name"
            :error="errors.name"
            required
            maxlength="255"
        />
        <FormField
            id="email"
            type="email"
            :label="t('store.customers.fields.email')"
            :value="customer?.email"
            :error="errors.email"
            required
            maxlength="255"
        />
        <FormField
            id="phone"
            type="tel"
            :label="t('store.customers.fields.phone')"
            :value="customer?.phone ?? ''"
            :error="errors.phone"
            required
            maxlength="255"
        />
        <template v-if="!representative">
            <FormField
                v-for="field in fields"
                :key="field"
                :id="field"
                :label="t(`store.customers.fields.${field}`)"
                :value="customer?.[field] ?? ''"
                :error="errors[field]"
                :required="
                    creating && !representative && field !== 'member_serial'
                "
                :maxlength="
                    ['tax_number', 'bank_account_number'].includes(field)
                        ? 100
                        : 255
                "
            />
            <div>
                <label for="country" :class="fieldLabelClass">{{
                    t('store.customers.fields.country')
                }}</label>
                <select
                    id="country"
                    name="country"
                    :required="creating && !representative"
                    :class="plainFieldInputClass"
                >
                    <option value="">
                        {{ t('store.customers.fields.selectCountry') }}
                    </option>
                    <option
                        v-for="country in countries"
                        :key="country.id"
                        :value="country.name"
                        :selected="customer?.country === country.name"
                    >
                        {{ country.name }}
                    </option></select
                ><FieldError :message="errors.country" />
            </div>
            <FormField
                id="AccSysID"
                type="number"
                :label="t('store.customers.fields.AccSysID')"
                :value="customer?.AccSysID ?? ''"
                :error="errors.AccSysID"
                min="0"
                step="1"
            />
            <FormField
                id="trade_license"
                type="file"
                :required="creating && !representative"
                :label="t('store.customers.fields.trade_license')"
                :error="errors.trade_license"
                accept=".pdf,.doc,.docx,.png,.jpg,.jpeg"
                @change="validateTradeLicense"
            />
        </template>
        <FormField
            id="password"
            type="password"
            :label="t('store.customers.fields.password')"
            :error="errors.password"
            :required="creating"
            minlength="8"
            maxlength="255"
            autocomplete="new-password"
            @input="syncConfirmationValidity"
        />
        <FormField
            id="password_confirmation"
            type="password"
            :label="t('store.customers.fields.password_confirmation')"
            :error="confirmationMismatchError"
            :required="creating"
            minlength="8"
            maxlength="255"
            autocomplete="new-password"
            @input="syncConfirmationValidity"
        />
    </div>
</template>

<script setup lang="ts">
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreCountry, StoreState } from '@/types/store';

defineProps<{
    countries: StoreCountry[];
    state?: StoreState;
    errors: Partial<Record<string, string>>;
}>();
</script>

<template>
    <div class="space-y-4">
        <FormField
            id="name"
            :label="t('store.states.fields.name')"
            :value="state?.name"
            :error="errors.name"
            maxlength="255"
            required
        />
        <div>
            <label for="country_id" :class="fieldLabelClass">
                {{ t('store.states.fields.country') }}
            </label>
            <select
                id="country_id"
                name="country_id"
                :class="plainFieldInputClass"
                required
            >
                <option value="" disabled :selected="!state">
                    {{ t('store.states.fields.countryPlaceholder') }}
                </option>
                <option
                    v-for="country in countries"
                    :key="country.id"
                    :value="country.id"
                    :selected="state?.country_id === country.id"
                >
                    {{ country.name }}
                </option>
            </select>
            <FieldError :message="errors.country_id" />
        </div>
    </div>
</template>

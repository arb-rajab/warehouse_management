<script setup lang="ts">
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreZone, StoreReferenceOption } from '@/types/store';
defineProps<{
    zone?: StoreZone;
    countries: StoreReferenceOption[];
    countryIds?: number[];
    errors: Record<string, string>;
}>();
</script>

<template>
    <div class="space-y-4">
        <FormField
            id="name"
            :label="t('store.reference.name')"
            :value="zone?.name"
            :error="errors.name"
            required
            maxlength="255"
        />
        <div>
            <label for="country_id" :class="fieldLabelClass">{{
                t('store.zones.countries')
            }}</label>
            <select
                id="country_id"
                name="country_id[]"
                multiple
                required
                :class="[plainFieldInputClass, 'cursor-pointer']"
            >
                <option
                    v-for="country in countries"
                    :key="country.id"
                    :value="country.id"
                    :selected="countryIds?.includes(country.id)"
                >
                    {{ country.name }}
                </option>
            </select>
            <FieldError
                :message="errors.country_id ?? errors['country_id.0']"
            />
        </div>
    </div>
</template>

<script setup lang="ts">
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreCity, StoreState } from '@/types/store';

defineProps<{
    states: StoreState[];
    city?: StoreCity;
    name?: string;
    errors: Partial<Record<string, string>>;
}>();
</script>

<template>
    <div class="space-y-4">
        <FormField
            id="name"
            :label="t('store.cities.fields.name')"
            :value="name ?? city?.name"
            :error="errors.name"
            maxlength="255"
            required
        />
        <div>
            <label for="state_id" :class="fieldLabelClass">
                {{ t('store.cities.fields.state') }}
            </label>
            <select
                id="state_id"
                name="state_id"
                :class="plainFieldInputClass"
                required
            >
                <option value="" disabled :selected="!city">
                    {{ t('store.cities.fields.statePlaceholder') }}
                </option>
                <option
                    v-for="state in states"
                    :key="state.id"
                    :value="state.id"
                    :selected="city?.state_id === state.id"
                >
                    {{ state.name }}
                </option>
            </select>
            <FieldError :message="errors.state_id" />
        </div>
        <FormField
            id="cost"
            :label="t('store.cities.fields.cost')"
            type="number"
            :value="city?.cost ?? '0'"
            :error="errors.cost"
            min="0"
            max="999999999999999999.99"
            step="0.01"
            required
        />
    </div>
</template>

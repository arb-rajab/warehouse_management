<script setup lang="ts">
import { ref } from 'vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import { fieldLabelClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreCountry, StoreZone } from '@/types/store';

const props = withDefaults(
    defineProps<{
        countries: StoreCountry[];
        zone?: StoreZone;
        countryIds?: number[];
        errors: Partial<Record<string, string>>;
    }>(),
    { countryIds: () => [] },
);

const selected = ref([...props.countryIds]);

function toggle(id: number, checked: boolean): void {
    selected.value = checked
        ? [...selected.value, id]
        : selected.value.filter((value) => value !== id);
}
</script>

<template>
    <div class="space-y-4">
        <FormField
            id="name"
            :label="t('store.zones.fields.name')"
            :value="zone?.name"
            :error="errors.name"
            maxlength="255"
            required
        />
        <fieldset>
            <legend :class="fieldLabelClass">
                {{ t('store.zones.fields.countries') }}
            </legend>
            <div class="grid gap-2 sm:grid-cols-2">
                <label
                    v-for="country in countries"
                    :key="country.id"
                    class="flex cursor-pointer items-center gap-2 text-sm"
                >
                    <input
                        type="checkbox"
                        name="country_id[]"
                        :value="country.id"
                        :checked="selected.includes(country.id)"
                        :required="selected.length === 0"
                        class="h-4 w-4 cursor-pointer rounded border-gray-300 dark:border-neutral-700"
                        @change="
                            toggle(
                                country.id,
                                ($event.target as HTMLInputElement).checked,
                            )
                        "
                    />
                    {{ country.name }}
                </label>
            </div>
            <FieldError
                :message="errors.country_id ?? errors['country_id.0']"
            />
        </fieldset>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import {
    states,
    cities,
} from '@/actions/App/Http/Controllers/Store/Admin/AddressController';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import StoreSearchSelect from '@/components/StoreSearchSelect.vue';
import { plainFieldInputClass, fieldLabelClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { SearchOption } from '@/types/searchSelect';
import type { AddressOption, CustomerAddress } from '@/types/storeCustomers';
const props = defineProps<{
    address?: CustomerAddress;
    countries: AddressOption[];
    errors: Record<string, string>;
}>();
const country = ref(props.address?.country_id ?? '');
const state = ref<SearchOption | null>(props.address?.state_option ?? null);
const city = ref<SearchOption | null>(props.address?.city_option ?? null);
/** A new country drops the state and city chosen under the old one. */
function changeCountry(): void {
    state.value = null;
    city.value = null;
}
/** A new state drops the city chosen under the old one. */
function pickState(option: SearchOption | null): void {
    if (String(option?.id ?? '') !== String(state.value?.id ?? '')) {
        city.value = null;
    }

    state.value = option;
}
</script>
<template>
    <div class="grid gap-3 md:grid-cols-2">
        <FormField
            id="address"
            :label="t('store.customers.fields.address')"
            :value="address?.address"
            :error="errors.address"
            required
            maxlength="255"
        />
        <FormField
            id="phone"
            :label="t('store.customers.fields.phone')"
            :value="address?.phone"
            :error="errors.phone"
            required
            maxlength="255"
        />
        <div>
            <label for="country_id" :class="fieldLabelClass"
                >{{ t('store.customers.fields.country')
                }}<RequiredMark /></label
            ><select
                id="country_id"
                v-model="country"
                name="country_id"
                required
                :class="plainFieldInputClass"
                @change="changeCountry"
            >
                <option value="">
                    {{ t('store.customers.fields.selectCountry') }}
                </option>
                <option
                    v-for="option in countries"
                    :key="option.id"
                    :value="option.id"
                >
                    {{ option.name }}
                </option></select
            ><FieldError :message="errors.country_id" />
        </div>
        <StoreSearchSelect
            id="state_id"
            name="state_id"
            :model-value="state"
            :label="t('store.customers.fields.state')"
            :endpoint="states"
            :params="{ country_id: country }"
            :disabled="!country"
            :placeholder="
                t(
                    country
                        ? 'store.customers.fields.selectState'
                        : 'store.customers.fields.selectCountryFirst',
                )
            "
            :search-placeholder="t('store.customers.fields.searchStates')"
            :error="errors.state_id"
            @update:model-value="pickState"
        />
        <StoreSearchSelect
            id="city_id"
            v-model="city"
            name="city_id"
            :label="t('store.customers.fields.city')"
            :endpoint="cities"
            :params="{ state_id: state?.id }"
            :disabled="!state"
            :placeholder="
                t(
                    state
                        ? 'store.customers.fields.selectCity'
                        : 'store.customers.fields.selectStateFirst',
                )
            "
            :search-placeholder="t('store.customers.fields.searchCities')"
            :error="errors.city_id"
        />
        <FormField
            id="postal_code"
            :label="t('store.customers.fields.postal_code')"
            :value="address?.postal_code ?? ''"
            :error="errors.postal_code"
            maxlength="255"
        />
        <FormField
            id="latitude"
            type="number"
            :label="t('store.customers.fields.latitude')"
            :value="address?.latitude ?? ''"
            :error="errors.latitude"
            min="-90"
            max="90"
            step="any"
        />
        <FormField
            id="longitude"
            type="number"
            :label="t('store.customers.fields.longitude')"
            :value="address?.longitude ?? ''"
            :error="errors.longitude"
            min="-180"
            max="180"
            step="any"
        />
    </div>
</template>

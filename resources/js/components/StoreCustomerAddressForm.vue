<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import {
    states as listStates,
    cities as listCities,
} from '@/actions/App/Http/Controllers/Store/Admin/AddressController';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import { plainFieldInputClass, fieldLabelClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { AddressOption, CustomerAddress } from '@/types/storeCustomers';
const props = defineProps<{
    address?: CustomerAddress;
    countries: AddressOption[];
    errors: Record<string, string>;
}>();
const country = ref(props.address?.country_id ?? '');
const state = ref(props.address?.state_id ?? '');
const city = ref(props.address?.city_id ?? '');
const stateOptions = ref<AddressOption[]>([]);
const cityOptions = ref<AddressOption[]>([]);
const stateHttp = useHttp<Record<string, never>, AddressOption[]>({});
const cityHttp = useHttp<Record<string, never>, AddressOption[]>({});
function loadStates(reset = true): void {
    if (reset) {
        state.value = '';
        city.value = '';
        cityOptions.value = [];
    }

    if (!country.value) {
        stateOptions.value = [];

        return;
    }

    stateHttp.get(listStates({ query: { country_id: country.value } }).url, {
        onSuccess: (response) => {
            stateOptions.value = response;
        },
    });
}
function loadCities(reset = true): void {
    if (reset) {
        city.value = '';
    }

    if (!state.value) {
        cityOptions.value = [];

        return;
    }

    cityHttp.get(listCities({ query: { state_id: state.value } }).url, {
        onSuccess: (response) => {
            cityOptions.value = response;
        },
    });
}
onMounted(() => {
    loadStates(false);
    loadCities(false);
});
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
            <label for="country_id" :class="fieldLabelClass">{{
                t('store.customers.fields.country')
            }}</label
            ><select
                id="country_id"
                v-model="country"
                name="country_id"
                required
                :class="plainFieldInputClass"
                @change="loadStates()"
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
        <div>
            <label for="state_id" :class="fieldLabelClass">{{
                t('store.customers.fields.state')
            }}</label
            ><select
                id="state_id"
                v-model="state"
                name="state_id"
                :class="plainFieldInputClass"
                :disabled="stateHttp.processing"
                @change="loadCities()"
            >
                <option value="">—</option>
                <option
                    v-for="option in stateOptions"
                    :key="option.id"
                    :value="option.id"
                >
                    {{ option.name }}
                </option></select
            ><FieldError :message="errors.state_id" />
        </div>
        <div>
            <label for="city_id" :class="fieldLabelClass">{{
                t('store.customers.fields.city')
            }}</label
            ><select
                id="city_id"
                v-model="city"
                name="city_id"
                :class="plainFieldInputClass"
                :disabled="cityHttp.processing"
            >
                <option value="">—</option>
                <option
                    v-for="option in cityOptions"
                    :key="option.id"
                    :value="option.id"
                >
                    {{ option.name }}
                </option></select
            ><FieldError :message="errors.city_id" />
        </div>
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

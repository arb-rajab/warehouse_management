<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import { nextTick, onMounted, ref, watch } from 'vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import StoreBooleanField from '@/components/StoreBooleanField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import {
    fieldLabelClass,
    plainFieldInputClass,
    filterTriggerButtonClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import type {
    StoreCarrier,
    StoreReferenceOption,
    StoreUpload,
} from '@/types/store';
const props = defineProps<{
    carrier?: StoreCarrier;
    zones: StoreReferenceOption[];
    initialFiles?: StoreUpload[];
    errors: Record<string, string>;
}>();
const container = ref<HTMLElement>();
const logo = ref(props.carrier?.logo?.toString() ?? '');
const freeShipping = ref(props.carrier?.free_shipping ?? false);
const billing = ref(props.carrier?.ranges?.[0]?.billing_type ?? 'weight_based');
let nextKey = 0;
const ranges = ref(
    (props.carrier?.ranges?.length
        ? props.carrier.ranges
        : [{ delimiter1: '0.00', delimiter2: '', prices: {} }]
    ).map((range) => ({
        key: nextKey++,
        lower: range.delimiter1,
        upper: range.delimiter2,
        prices: { ...range.prices },
    })),
);
const selectedZones = ref(
    props.zones
        .filter((zone) =>
            props.carrier?.ranges?.some((range) =>
                Object.hasOwn(range.prices, zone.id),
            ),
        )
        .map((zone) => zone.id),
);
function addRange(): void {
    ranges.value.push({
        key: nextKey++,
        lower: ranges.value.at(-1)?.upper ?? '',
        upper: '',
        prices: {},
    });
}
function setValue(index: number, field: 'lower' | 'upper', event: Event): void {
    ranges.value[index][field] = (event.target as HTMLInputElement).value;
}
function decimal(value: string): bigint | null {
    const match = /^(-?)(\d+)(?:\.(\d{1,2}))?$/.exec(value);

    if (!match) {
        return null;
    }

    const amount =
        BigInt(match[2]) * 100n + BigInt((match[3] ?? '').padEnd(2, '0'));

    return match[1] === '-' ? -amount : amount;
}
async function validateRanges(): Promise<void> {
    await nextTick();
    ranges.value.forEach((range, index) => {
        const lower = container.value?.querySelector<HTMLInputElement>(
            `#delimiter1-${range.key}`,
        );
        const upper = container.value?.querySelector<HTMLInputElement>(
            `#delimiter2-${range.key}`,
        );
        lower?.setCustomValidity(
            index > 0 &&
                range.lower !== '' &&
                ranges.value[index - 1].upper !== '' &&
                decimal(range.lower) !== decimal(ranges.value[index - 1].upper)
                ? t('store.carriers.contiguous')
                : '',
        );
        upper?.setCustomValidity(
            range.upper !== '' &&
                range.lower !== '' &&
                decimal(range.upper) !== null &&
                decimal(range.lower) !== null &&
                decimal(range.upper)! <= decimal(range.lower)!
                ? t('store.carriers.increasing')
                : '',
        );
    });
    const zoneInput =
        container.value?.querySelector<HTMLInputElement>('input[data-zone]');
    zoneInput?.setCustomValidity(
        !freeShipping.value && selectedZones.value.length === 0
            ? t('store.carriers.selectZone')
            : '',
    );
}
watch([ranges, selectedZones, freeShipping], validateRanges, { deep: true });
onMounted(validateRanges);
</script>

<template>
    <div ref="container" class="space-y-4">
        <FormField
            id="carrier_name"
            :label="t('store.carriers.name')"
            :value="carrier?.name"
            :error="errors.carrier_name"
            required
            maxlength="255"
        />
        <FormField
            id="transit_time"
            :label="t('store.carriers.transitTime')"
            :value="carrier?.transit_time"
            :error="errors.transit_time"
            required
            maxlength="255"
        />
        <StoreUploadField
            v-model="logo"
            name="logo"
            :label="t('store.carriers.logo')"
            type="image"
            :initial-files="initialFiles"
            :error="errors.logo"
        />
        <StoreBooleanField
            name="shipping_type"
            :label="t('store.carriers.freeShipping')"
            :value="freeShipping"
            :error="errors.shipping_type"
            @change="
                freeShipping =
                    ($event.target as HTMLSelectElement).value === '1'
            "
        />
        <template v-if="!freeShipping">
            <div>
                <label for="billing_type" :class="fieldLabelClass">{{
                    t('store.carriers.billingType')
                }}</label>
                <select
                    id="billing_type"
                    v-model="billing"
                    name="billing_type"
                    required
                    :class="[plainFieldInputClass, 'cursor-pointer']"
                >
                    <option value="weight_based">
                        {{ t('store.carriers.weightBased') }}
                    </option>
                    <option value="price_based">
                        {{ t('store.carriers.priceBased') }}
                    </option>
                </select>
                <FieldError :message="errors.billing_type" />
            </div>
            <fieldset>
                <legend :class="fieldLabelClass">
                    {{ t('store.carriers.zones') }}
                </legend>
                <label
                    v-for="zone in zones"
                    :key="zone.id"
                    class="me-4 inline-flex cursor-pointer items-center gap-2"
                >
                    <input
                        v-model="selectedZones"
                        type="checkbox"
                        name="zones[]"
                        :value="zone.id"
                        data-zone
                    />{{ zone.name }}
                </label>
                <FieldError :message="errors.zones" />
                <FieldError
                    v-for="[key, message] in Object.entries(errors).filter(
                        ([key]) => key.startsWith('zones.'),
                    )"
                    :key="key"
                    :message="message"
                />
            </fieldset>
            <p class="text-sm">{{ t('store.carriers.boundaries') }}</p>
            <FieldError
                v-for="key in ['delimiter1', 'delimiter2', 'carrier_price']"
                :key="key"
                :message="errors[key]"
            />
            <fieldset
                v-for="(range, index) in ranges"
                :key="range.key"
                class="space-y-3 rounded-md border border-gray-300 p-4 dark:border-neutral-700"
            >
                <legend class="px-2 font-medium">
                    {{ t('store.carriers.range') }} {{ index + 1 }}
                </legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        :id="`delimiter1-${range.key}`"
                        :name="`delimiter1[${index}]`"
                        :label="t('store.carriers.lower')"
                        type="number"
                        :value="range.lower"
                        step="0.01"
                        required
                        :error="errors[`delimiter1.${index}`]"
                        @input="setValue(index, 'lower', $event)"
                    />
                    <FormField
                        :id="`delimiter2-${range.key}`"
                        :name="`delimiter2[${index}]`"
                        :label="t('store.carriers.upper')"
                        type="number"
                        :value="range.upper"
                        step="0.01"
                        required
                        :error="errors[`delimiter2.${index}`]"
                        @input="setValue(index, 'upper', $event)"
                    />
                    <template v-for="zone in zones" :key="zone.id">
                        <FormField
                            v-if="selectedZones.includes(zone.id)"
                            :id="`price-${zone.id}-${range.key}`"
                            :name="`carrier_price[${zone.id}][${index}]`"
                            :label="`${zone.name} — ${t('store.carriers.price')}`"
                            type="number"
                            :value="range.prices[zone.id]"
                            step="0.01"
                            min="-999999.99"
                            max="999999.99"
                            required
                            :error="
                                errors[`carrier_price.${zone.id}.${index}`] ??
                                errors[`carrier_price.${zone.id}`]
                            "
                            @input="
                                range.prices[zone.id] = (
                                    $event.target as HTMLInputElement
                                ).value
                            "
                        />
                    </template>
                </div>
                <button
                    type="button"
                    :class="filterTriggerButtonClass"
                    :disabled="ranges.length === 1"
                    @click="ranges.splice(index, 1)"
                >
                    <Trash2 class="h-4 w-4" />{{
                        t('store.carriers.removeRange')
                    }}
                </button>
            </fieldset>
            <button
                type="button"
                :class="filterTriggerButtonClass"
                @click="addRange"
            >
                <Plus class="h-4 w-4" />{{ t('store.carriers.addRange') }}
            </button>
        </template>
    </div>
</template>

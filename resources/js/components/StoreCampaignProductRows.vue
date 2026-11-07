<script setup lang="ts">
import { Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { index as productIndex } from '@/actions/App/Http/Controllers/Store/Admin/CampaignProductController';
import DataTable from '@/components/DataTable.vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import StoreSearchSelect from '@/components/StoreSearchSelect.vue';
import {
    fieldLabelClass,
    filterTriggerButtonClass,
    plainFieldInputClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { SearchOption } from '@/types/searchSelect';
import type {
    StoreCampaignProductExtra,
    StoreCampaignRow,
} from '@/types/store';

/**
 * The reward products of a flash deal or an offer: picked in a multiple
 * StoreSearchSelect, listed below with their own discount (and quantity) inputs
 * posted as `products[i][...]`. Every product ever seen is remembered, so a
 * row keeps its name and price while the search changes.
 */
const props = defineProps<{
    selectedProducts: SearchOption[];
    quantity?: boolean;
    errors: Record<string, string>;
}>();
const rows = defineModel<StoreCampaignRow[]>({ required: true });
const known = new Map<number, SearchOption>(
    props.selectedProducts.map((option) => [Number(option.id), option]),
);
const picked = computed(() =>
    rows.value
        .map((row) => known.get(row.id))
        .filter((option): option is SearchOption => option !== undefined),
);

function extraOf(id: number): Partial<StoreCampaignProductExtra> {
    return (known.get(id)?.extra ?? {}) as Partial<StoreCampaignProductExtra>;
}
function newRow(option: SearchOption): StoreCampaignRow {
    const extra = (option.extra ?? {}) as Partial<StoreCampaignProductExtra>;

    return {
        id: Number(option.id),
        discount: props.quantity ? 0 : (extra.discount ?? 0),
        discount_type: props.quantity
            ? 'amount'
            : (extra.discount_type ?? 'amount'),
        ...(props.quantity ? { quantity: 1 } : {}),
    };
}
function pick(options: SearchOption[]): void {
    for (const option of options) {
        known.set(Number(option.id), option);
    }

    rows.value = options.map(
        (option) =>
            rows.value.find((row) => row.id === Number(option.id)) ??
            newRow(option),
    );
}
function name(id: number): string {
    return (
        known.get(id)?.label ?? t('store.campaigns.fields.unavailable', { id })
    );
}
</script>
<template>
    <div class="space-y-4">
        <slot name="targeting" />
        <StoreSearchSelect
            id="products"
            multiple
            :items="picked"
            :label="t('store.campaigns.fields.products')"
            :endpoint="productIndex"
            :placeholder="t('store.campaigns.fields.chooseProducts')"
            :search-placeholder="t('store.campaigns.fields.searchProducts')"
            required
            :error="errors.products"
            @update:items="pick"
        />
        <DataTable
            :columns="[
                t('store.campaigns.fields.product'),
                t('store.campaigns.fields.price'),
                t('store.campaigns.fields.discount'),
                ...(quantity ? [t('store.campaigns.fields.quantity')] : []),
                t('store.campaigns.fields.discountType'),
                t('store.campaigns.fields.actions'),
            ]"
            :rows="rows"
            :empty-message="t('store.campaigns.fields.empty')"
        >
            <template #row="{ row }"
                ><td class="px-4 py-2">
                    {{ name(row.id)
                    }}<input
                        type="hidden"
                        :name="`products[${rows.indexOf(row)}][id]`"
                        :value="row.id"
                    /><FieldError
                        :message="errors[`products.${rows.indexOf(row)}.id`]"
                    />
                </td>
                <td class="px-4 py-2">
                    {{ extraOf(row.id).price ?? '—' }}
                </td>
                <td class="px-4 py-2">
                    <FormField
                        :id="`discount-${row.id}`"
                        :name="`products[${rows.indexOf(row)}][discount]`"
                        type="number"
                        min="0"
                        :max="row.discount_type === 'percent' ? 100 : undefined"
                        step="0.01"
                        required
                        :label="t('store.campaigns.fields.discount')"
                        :value="row.discount"
                        :error="
                            errors[`products.${rows.indexOf(row)}.discount`]
                        "
                        @input="
                            row.discount = Number(
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                    />
                </td>
                <td v-if="quantity" class="px-4 py-2">
                    <FormField
                        :id="`quantity-${row.id}`"
                        :name="`products[${rows.indexOf(row)}][quantity]`"
                        type="number"
                        min="0"
                        step="1"
                        required
                        :label="t('store.campaigns.fields.quantity')"
                        :value="row.quantity"
                        :error="
                            errors[`products.${rows.indexOf(row)}.quantity`]
                        "
                        @input="
                            row.quantity = Number(
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                    />
                </td>
                <td class="px-4 py-2">
                    <label
                        :for="`discount-type-${row.id}`"
                        :class="fieldLabelClass"
                        >{{ t('store.campaigns.fields.discountType')
                        }}<RequiredMark /></label
                    ><select
                        :id="`discount-type-${row.id}`"
                        v-model="row.discount_type"
                        :name="`products[${rows.indexOf(row)}][discount_type]`"
                        required
                        :class="plainFieldInputClass"
                    >
                        <option value="amount">
                            {{ t('store.campaigns.fields.amount') }}
                        </option>
                        <option value="percent">
                            {{ t('store.campaigns.fields.percent') }}
                        </option></select
                    ><FieldError
                        :message="
                            errors[
                                `products.${rows.indexOf(row)}.discount_type`
                            ]
                        "
                    />
                </td>
                <td class="px-4 py-2">
                    <button
                        type="button"
                        :class="filterTriggerButtonClass"
                        @click="rows = rows.filter((r) => r.id !== row.id)"
                    >
                        <Trash2 aria-hidden="true" class="size-4" />{{
                            t('store.campaigns.fields.remove')
                        }}
                    </button>
                </td></template
            ></DataTable
        >
    </div>
</template>

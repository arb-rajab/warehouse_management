<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { LoaderCircle, Trash2 } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { index as productIndex } from '@/actions/App/Http/Controllers/Store/Admin/CampaignProductController';
import DataTable from '@/components/DataTable.vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import {
    fieldLabelClass,
    plainFieldInputClass,
    filterTriggerButtonClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreCampaignProduct, StoreCampaignRow } from '@/types/store';
const props = defineProps<{
    selectedProducts: StoreCampaignProduct[];
    quantity?: boolean;
    pinnedIds?: number[];
    errors: Record<string, string>;
}>();
const rows = defineModel<StoreCampaignRow[]>({ required: true });
const results = ref<StoreCampaignProduct[]>(props.selectedProducts);
const remembered = new Map(props.selectedProducts.map((p) => [p.id, p]));
const http = useHttp<{ q: string }, StoreCampaignProduct[]>({ q: '' });
let sequence = 0;
const options = computed(() => {
    const map = new Map(props.selectedProducts.map((p) => [p.id, p]));

    for (const r of [
        ...rows.value,
        ...(props.pinnedIds ?? []).map((id) => ({ id })),
    ]) {
        const p = remembered.get(r.id);

        if (p) {
            map.set(p.id, p);
        }
    }

    for (const p of results.value) {
        map.set(p.id, p);
    }

    return [...map.values()];
});
function search() {
    const current = ++sequence;
    http.get(productIndex.url(), {
        onSuccess: (response) => {
            if (current !== sequence) {
                return;
            }

            for (const p of response) {
                remembered.set(p.id, p);
            }

            results.value = response;
        },
    }).catch(() => undefined);
}
function select(event: Event) {
    const ids = Array.from(
        (event.target as HTMLSelectElement).selectedOptions,
        (p) => Number(p.value),
    );
    rows.value = ids.map(
        (id) =>
            rows.value.find((r) => r.id === id) ?? {
                id,
                discount: props.quantity
                    ? 0
                    : (remembered.get(id)?.discount ?? 0),
                discount_type: props.quantity
                    ? 'amount'
                    : (remembered.get(id)?.discount_type ?? 'amount'),
                ...(props.quantity ? { quantity: 1 } : {}),
            },
    );
}
function name(id: number) {
    return (
        remembered.get(id)?.name ??
        t('store.campaigns.fields.unavailable', { id })
    );
}
onMounted(search);
</script>
<template>
    <div class="space-y-4">
        <FormField
            id="product_search"
            type="search"
            maxlength="255"
            :label="t('store.campaigns.fields.searchProducts')"
            :error="http.errors.q"
            @input="
                http.q = ($event.target as HTMLInputElement).value;
                search();
            "
        />
        <p v-if="http.processing" role="status" class="flex items-center gap-2">
            <LoaderCircle aria-hidden="true" class="size-4 animate-spin" />{{
                t('store.campaigns.fields.searching')
            }}
        </p>
        <slot name="targeting" :options="options" />
        <div>
            <label for="products" :class="fieldLabelClass"
                >{{ t('store.campaigns.fields.products')
                }}<RequiredMark /></label
            ><select
                id="products"
                multiple
                required
                size="6"
                :class="plainFieldInputClass"
                @change="select"
            >
                <option
                    v-for="p in options"
                    :key="p.id"
                    :value="p.id"
                    :selected="rows.some((r) => r.id === p.id)"
                >
                    {{ p.name }}
                </option></select
            ><FieldError :message="errors.products" />
        </div>
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
                    {{ remembered.get(row.id)?.price ?? '—' }}
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

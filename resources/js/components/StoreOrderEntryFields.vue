<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import DataTable from '@/components/DataTable.vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import StoreOrderSearch from '@/components/StoreOrderSearch.vue';
import {
    fieldLabelClass,
    plainFieldInputClass,
    filterTriggerButtonClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import { firstError } from '@/lib/pageErrors';
import type { StoreOrder, StoreOrderOption } from '@/types/store';
const props = defineProps<{
    representativeOrder: boolean;
    submissionKey?: string;
    order?: StoreOrder;
    additionalInfo?: string | null;
    couponDiscount?: number;
    errors: Record<string, string>;
}>();
type EntryLine = {
    id: number;
    persistedId?: number;
    option?: StoreOrderOption;
    quantity: number;
    price: number;
    unit: string;
    variant: string;
    info: string;
    tax?: number;
    shipping?: number;
};
let nextId = 1;
const customer = ref<StoreOrderOption | undefined>(
    props.order?.customer ?? undefined,
);
const representative = ref<StoreOrderOption | undefined>(
    props.order?.representative ?? undefined,
);
const lines = ref<EntryLine[]>(
    props.order?.details?.map((line) => ({
        id: nextId++,
        persistedId: line.id,
        option: {
            id: line.product_id,
            name: line.name,
            serial: line.serial,
            mat_id: line.mat_id,
        },
        quantity: line.quantity,
        price: line.unit_price,
        unit: line.unit,
        variant: line.variation ?? '',
        info: line.info ?? '',
        tax: line.tax,
        shipping: line.shipping_cost,
    })) ?? [
        {
            id: nextId++,
            quantity: 1,
            price: 0,
            unit: 'PerBox',
            variant: '',
            info: '',
        },
    ],
);
const subtotal = computed(() =>
    lines.value.reduce(
        (sum, line) => sum + Math.round(line.price * line.quantity * 100) / 100,
        0,
    ),
);
const tax = computed(() =>
    lines.value.reduce(
        (sum, line) =>
            sum +
            (line.tax ??
                (line.option?.tax ?? 0)),
        0,
    ),
);
const total = computed(() => Math.max(0, subtotal.value));

function selectProduct(line: EntryLine, product: StoreOrderOption): void {
    const same = product.id === line.option?.id;
    line.option = product;
    line.variant = product.stocks?.[0]?.variant ?? '';
    line.price = product.unit_price ?? 0;
    line.unit = 'PerBox';
    unitChanged(line);

    if (!same) {
        line.tax = undefined;
        line.shipping = undefined;
    }
}
function selectRepresentative(option: StoreOrderOption): void {
    representative.value = option;
    lines.value.forEach(unitChanged);
}
function unitChanged(line: EntryLine): void {
    if (!line.option?.unit_price) {
        return;
    }

    const base =
        line.unit === 'PerPiece'
            ? line.option.unit_price / Math.max(1, line.option.unit_equal ?? 1)
            : line.option.unit_price;
    line.price =
        Math.round(
            base *
                (1 -
                    (representative.value?.rep_discount_percentage ?? 0) /
                        100) *
                100,
        ) / 100;
}
</script>
<template>
    <div class="space-y-5">
        <ActionErrorBanner :message="firstError(errors)" />
        <input
            v-if="submissionKey"
            type="hidden"
            name="submission_key"
            :value="submissionKey"
        />
        <div class="grid gap-4 md:grid-cols-2">
            <StoreOrderSearch
                id="customer_id"
                name="customer_id"
                :label="t('store.orders.customer')"
                kind="customers"
                :selected="customer"
                required
                :error="errors.customer_id"
                @select="customer = $event"
            />
            <StoreOrderSearch
                v-if="representativeOrder"
                id="rep_id"
                name="rep_id"
                :label="t('store.orders.representative')"
                kind="representatives"
                :selected="representative"
                required
                :error="errors.rep_id"
                @select="selectRepresentative"
            />
            <FormField
                id="delivery_date"
                type="date"
                :label="t('store.orders.deliveryDate')"
                :value="order?.delivery_date ?? ''"
                :error="errors.delivery_date"
                class="dark:[color-scheme:dark]"
            />
            <FormField
                id="additional_info"
                :label="t('store.orders.notes')"
                :value="additionalInfo ?? ''"
                maxlength="2000"
                :error="errors.additional_info"
            />
        </div>
        <DataTable
            :columns="[
                t('store.orders.product'),
                t('store.orders.quantity'),
                t('store.orders.unit'),
                t('store.orders.unitPrice'),
                t('store.orders.notes'),
                t('store.orders.actions'),
            ]"
            :rows="lines"
            :empty-message="t('store.orders.noLines')"
        >
            <template #row="{ row }">
                <td class="min-w-64 px-3 py-3">
                    <input
                        v-if="row.persistedId"
                        type="hidden"
                        :name="`products[${lines.indexOf(row)}][id]`"
                        :value="row.persistedId"
                    />
                    <StoreOrderSearch
                        :id="`product-${row.id}`"
                        :name="`products[${lines.indexOf(row)}][product_id]`"
                        :label="t('store.orders.product')"
                        kind="products"
                        :selected="row.option"
                        required
                        :error="
                            errors[`products.${lines.indexOf(row)}.product_id`]
                        "
                        @select="selectProduct(row, $event)"
                    />
                    <label
                        :for="`variant-${row.id}`"
                        :class="fieldLabelClass"
                        >{{ t('store.orders.variant') }}</label
                    >
                    <select
                        v-if="row.option?.stocks?.length"
                        :id="`variant-${row.id}`"
                        v-model="row.variant"
                        :class="plainFieldInputClass"
                        :name="`products[${lines.indexOf(row)}][stock_variant]`"
                    >
                        <option
                            v-for="stock in row.option.stocks"
                            :key="stock.variant"
                            :value="stock.variant"
                        >
                            {{
                                stock.variant ||
                                t('store.orders.defaultVariant')
                            }}
                            ({{ stock.qty }})
                        </option>
                    </select>
                    <input
                        v-else
                        type="hidden"
                        :name="`products[${lines.indexOf(row)}][stock_variant]`"
                        :value="row.variant"
                    />
                </td>
                <td class="min-w-28 px-3 py-3">
                    <FormField
                        :id="`quantity-${row.id}`"
                        :name="`products[${lines.indexOf(row)}][quantity]`"
                        :label="t('store.orders.quantity')"
                        type="number"
                        :value="row.quantity"
                        required
                        min="1"
                        max="100000"
                        step="1"
                        :error="
                            errors[`products.${lines.indexOf(row)}.quantity`]
                        "
                        @input="
                            row.quantity = Number(
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                    />
                </td>
                <td class="px-3 py-3">
                    <label :for="`unit-${row.id}`" :class="fieldLabelClass">{{
                        t('store.orders.unit')
                    }}</label
                    ><select
                        :id="`unit-${row.id}`"
                        v-model="row.unit"
                        :name="`products[${lines.indexOf(row)}][unit]`"
                        :class="plainFieldInputClass"
                        required
                        @change="unitChanged(row)"
                    >
                        <option value="PerBox">
                            {{ t('store.orders.box') }}
                        </option>
                        <option value="PerPiece">
                            {{ t('store.orders.piece') }}
                        </option>
                    </select>
                </td>
                <td class="min-w-32 px-3 py-3">
                    <FormField
                        :id="`price-${row.id}`"
                        :name="`products[${lines.indexOf(row)}][price]`"
                        :label="t('store.orders.unitPrice')"
                        type="number"
                        :value="row.price"
                        required
                        min="0"
                        max="99999999.99"
                        step="0.01"
                        :error="errors[`products.${lines.indexOf(row)}.price`]"
                        @input="
                            row.price = Number(
                                ($event.target as HTMLInputElement).value,
                            )
                        "
                    />
                </td>
                <td class="px-3 py-3">
                    <FormField
                        :id="`info-${row.id}`"
                        :name="`products[${lines.indexOf(row)}][info]`"
                        :label="t('store.orders.notes')"
                        :value="row.info"
                        maxlength="1000"
                        :error="errors[`products.${lines.indexOf(row)}.info`]"
                    />
                </td>
                <td class="px-3 py-3">
                    <button
                        type="button"
                        :class="filterTriggerButtonClass"
                        :aria-label="t('store.orders.removeLine')"
                        :disabled="lines.length === 1"
                        @click="
                            lines = lines.filter((line) => line.id !== row.id)
                        "
                    >
                        <Trash2 class="h-4 w-4" />
                    </button>
                </td>
            </template>
        </DataTable>
        <FieldError :message="errors.products" />
        <button
            type="button"
            :class="filterTriggerButtonClass"
            :disabled="lines.length >= 100"
            @click="
                lines.push({
                    id: nextId++,
                    quantity: 1,
                    price: 0,
                    unit: 'PerBox',
                    variant: '',
                    info: '',
                })
            "
        >
            <Plus class="h-4 w-4" />{{ t('store.orders.addLine') }}
        </button>
        <dl class="grid grid-cols-2 gap-2 text-sm">
            <dt>{{ t('store.orders.subtotal') }}</dt>
            <dd>{{ subtotal.toFixed(2) }}</dd>
            <dt>{{ t('store.orders.tax') }}</dt>
            <dd>{{ tax.toFixed(2) }}</dd>
            <dt>{{ t('store.orders.total') }}</dt>
            <dd class="font-semibold">{{ total.toFixed(2) }}</dd>
        </dl>
    </div>
</template>

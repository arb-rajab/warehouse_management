<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { CircleAlert } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { variantCombinations } from '@/actions/App/Http/Controllers/Store/Admin/ProductController';
import { attributeValues } from '@/actions/App/Http/Controllers/Store/Admin/ProductSearchController';
import FieldError from '@/components/FieldError.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { productAmountMax } from '@/lib/storeProductForm';
import type {
    StoreProductAttributeValue,
    StoreProductColor,
    StoreProductVariantRow,
    StoreProductVariantState,
    StoreReferenceOption,
} from '@/types/store';

/**
 * A product's colours and attribute values, and the variant rows they
 * generate, each with its own price, SKU, quantity and photo.
 *
 * The rows come from the server (`products.variant_combinations`, or on the
 * edit form `rowsUrl`), which names and orders them as the legacy SKU table
 * did; like that table, they are regenerated whenever a choice or the unit
 * price changes. Colours, attributes and values keep their list order, which
 * is the order the legacy selects posted them in, except that the edit form's
 * stored attributes (`initial`) keep their stored order ahead of the others:
 * that order names the stored variants. A value listed twice is offered once.
 */
const props = defineProps<{
    colors: StoreProductColor[];
    attributes: StoreReferenceOption[];
    unitPrice: string;
    initial?: StoreProductVariantState;
    rowsUrl?: string;
    errors: Record<string, string>;
}>();
const hasVariants = defineModel<boolean>('hasVariants', { default: false });
const storedOrder =
    props.initial?.choice_options.map((choice) => choice.attribute_id) ?? [];
const colorsActive = ref(props.initial?.colors_active ?? false);
const chosenColors = ref<string[]>([...(props.initial?.colors ?? [])]);
const chosenAttributes = ref<number[]>([...storedOrder]);
const attributeOptions = ref<Record<number, StoreProductAttributeValue[]>>({
    ...props.initial?.attribute_values,
});
const chosenValues = ref<Record<number, string[]>>(
    Object.fromEntries(
        (props.initial?.choice_options ?? []).map((choice) => [
            choice.attribute_id,
            [...choice.values],
        ]),
    ),
);
const rows = ref<StoreProductVariantRow[]>(props.initial?.rows ?? []);
const failure = ref<string>();
const valuesRequest = useHttp<
    { attribute_id: number },
    StoreProductAttributeValue[]
>({ attribute_id: 0 });
const rowsRequest = useHttp<
    {
        colors_active: boolean;
        colors: string[];
        choice_options: { attribute_id: number; values: string[] }[];
        unit_price: string;
    },
    StoreProductVariantRow[]
>({ colors_active: false, colors: [], choice_options: [], unit_price: '' });
let revision = 0;
let timer: ReturnType<typeof setTimeout> | undefined;

const orderedColors = computed(() =>
    props.colors
        .filter((color) => chosenColors.value.includes(color.code))
        .map((color) => color.code),
);
function rank(id: number, listIndex: number): number {
    const stored = storedOrder.indexOf(id);

    return stored === -1 ? storedOrder.length + listIndex : stored;
}
function uniqueOptions(
    options: StoreProductAttributeValue[],
): StoreProductAttributeValue[] {
    return options.filter(
        (option, index) =>
            options.findIndex((other) => other.value === option.value) ===
            index,
    );
}
const choices = computed(() =>
    props.attributes
        .map((attribute, listIndex) => ({ attribute, listIndex }))
        .filter(({ attribute }) =>
            chosenAttributes.value.includes(attribute.id),
        )
        .sort(
            (a, b) =>
                rank(a.attribute.id, a.listIndex) -
                rank(b.attribute.id, b.listIndex),
        )
        .map(({ attribute }) => {
            const options = uniqueOptions(
                attributeOptions.value[attribute.id] ?? [],
            );
            const chosen = chosenValues.value[attribute.id] ?? [];
            const values = options.flatMap((option) =>
                chosen.includes(option.value) ? [option.value] : [],
            );

            return { attribute, options, values };
        }),
);

async function loadValues(attributeId: number): Promise<void> {
    if (attributeOptions.value[attributeId]) {
        return;
    }

    valuesRequest.attribute_id = attributeId;

    try {
        const result = await valuesRequest.post(attributeValues.url());
        attributeOptions.value = {
            ...attributeOptions.value,
            [attributeId]: result ?? [],
        };
    } catch {
        failure.value = t('store.products.form.valuesFailed');
    }
}
watch(chosenAttributes, (ids) => {
    chosenValues.value = Object.fromEntries(
        ids.map((id) => [id, chosenValues.value[id] ?? []]),
    );
    ids.forEach((id) => void loadValues(id));
});

async function refresh(): Promise<void> {
    const current = ++revision;
    const colors = colorsActive.value ? orderedColors.value : [];
    const chosen = choices.value.map((choice) => ({
        attribute_id: choice.attribute.id,
        values: choice.values,
    }));

    // An attribute without a chosen value generates no row, as the legacy
    // table showed none; saving it is refused until a value is chosen.
    if (
        (colors.length === 0 && chosen.length === 0) ||
        chosen.some((choice) => choice.values.length === 0)
    ) {
        rows.value = [];

        return;
    }

    rowsRequest.colors_active = colorsActive.value;
    rowsRequest.colors = colors;
    rowsRequest.choice_options = chosen;
    rowsRequest.unit_price = props.unitPrice;
    failure.value = undefined;

    try {
        const result = await rowsRequest.post(
            props.rowsUrl ?? variantCombinations.url(),
        );

        if (revision === current) {
            rows.value = result ?? [];
            // A refused request resolves without rows.
            failure.value = result
                ? undefined
                : t('store.products.form.variantsFailed');
        }
    } catch {
        if (revision === current) {
            rows.value = [];
            failure.value = t('store.products.form.variantsFailed');
        }
    }
}
watch(
    [colorsActive, orderedColors, choices, () => props.unitPrice],
    () => {
        clearTimeout(timer);
        timer = setTimeout(() => void refresh(), 300);
    },
    { deep: true },
);
watch(rows, (current) => (hasVariants.value = current.length > 0), {
    immediate: true,
});
onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div class="space-y-4 lg:col-span-2">
        <div>
            <label class="inline-flex cursor-pointer items-center gap-2">
                <input
                    v-model="colorsActive"
                    type="checkbox"
                    name="colors_active"
                    value="1"
                    class="h-4 w-4 cursor-pointer"
                />
                <span class="text-sm font-medium">{{
                    t('store.products.form.useColors')
                }}</span>
            </label>
            <fieldset
                :disabled="!colorsActive"
                class="mt-2 disabled:opacity-60"
            >
                <legend :class="fieldLabelClass">
                    {{ t('store.products.form.colors') }}
                </legend>
                <div class="flex flex-wrap gap-3">
                    <label
                        v-for="color in colors"
                        :key="color.id"
                        class="inline-flex cursor-pointer items-center gap-2 text-sm"
                    >
                        <input
                            v-model="chosenColors"
                            type="checkbox"
                            name="colors[]"
                            :value="color.code"
                            class="h-4 w-4 cursor-pointer disabled:cursor-not-allowed"
                        />
                        <span
                            class="inline-block h-4 w-4 rounded border border-gray-300 dark:border-neutral-600"
                            :style="{ backgroundColor: color.code }"
                            aria-hidden="true"
                        />
                        {{ color.name }}
                    </label>
                </div>
                <FieldError :message="errors.colors" />
            </fieldset>
        </div>

        <fieldset>
            <legend :class="fieldLabelClass">
                {{ t('store.products.form.attributes') }}
            </legend>
            <p class="mb-2 text-xs text-gray-600 dark:text-neutral-400">
                {{ t('store.products.form.attributesHelp') }}
            </p>
            <div class="flex flex-wrap gap-3">
                <label
                    v-for="attribute in attributes"
                    :key="attribute.id"
                    class="inline-flex cursor-pointer items-center gap-2 text-sm"
                >
                    <input
                        v-model="chosenAttributes"
                        type="checkbox"
                        :value="attribute.id"
                        class="h-4 w-4 cursor-pointer"
                    />
                    {{ attribute.name }}
                </label>
            </div>
        </fieldset>

        <fieldset
            v-for="(choice, index) in choices"
            :key="choice.attribute.id"
            :data-attribute="choice.attribute.id"
        >
            <legend :class="fieldLabelClass">
                {{
                    t('store.products.form.valuesOf', {
                        attribute: choice.attribute.name,
                    })
                }}
            </legend>
            <input
                type="hidden"
                :name="`choice_options[${index}][attribute_id]`"
                :value="choice.attribute.id"
            />
            <p
                v-if="choice.options.length === 0"
                class="text-sm text-gray-600 dark:text-neutral-400"
            >
                {{ t('store.products.form.noValues') }}
            </p>
            <div class="flex flex-wrap gap-3">
                <label
                    v-for="option in choice.options"
                    :key="option.id"
                    class="inline-flex cursor-pointer items-center gap-2 text-sm"
                >
                    <input
                        v-model="chosenValues[choice.attribute.id]"
                        type="checkbox"
                        :name="`choice_options[${index}][values][]`"
                        :value="option.value"
                        class="h-4 w-4 cursor-pointer"
                    />
                    {{ option.value }}
                </label>
            </div>
            <FieldError
                :message="
                    errors[`choice_options.${index}.values`] ??
                    errors[`choice_options.${index}.attribute_id`]
                "
            />
        </fieldset>
        <FieldError :message="errors.choice_options" />

        <p
            v-if="failure"
            role="alert"
            class="flex items-center gap-2 text-sm text-red-700 dark:text-red-300"
        >
            <CircleAlert class="h-4 w-4 shrink-0" />{{ failure }}
        </p>

        <div v-if="rows.length > 0" class="overflow-x-auto">
            <table class="w-full text-sm">
                <caption class="sr-only">
                    {{
                        t('store.products.form.variantsCaption')
                    }}
                </caption>
                <thead>
                    <tr class="text-start">
                        <th scope="col" class="p-2 text-start">
                            {{ t('store.products.form.variant') }}
                        </th>
                        <th scope="col" class="p-2 text-start">
                            {{ t('store.products.form.variantPrice') }}
                        </th>
                        <th scope="col" class="p-2 text-start">
                            {{ t('store.products.form.sku') }}
                        </th>
                        <th scope="col" class="p-2 text-start">
                            {{ t('store.products.form.quantity') }}
                        </th>
                        <th scope="col" class="p-2 text-start">
                            {{ t('store.products.form.photo') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, index) in rows"
                        :key="row.variant"
                        class="border-t border-gray-200 align-top dark:border-neutral-800"
                    >
                        <th scope="row" class="p-2 text-start font-medium">
                            <input
                                type="hidden"
                                :name="`variants[${index}][variant]`"
                                :value="row.variant"
                            />
                            <span dir="auto">{{ row.variant }}</span>
                            <FieldError
                                :message="errors[`variants.${index}.variant`]"
                            />
                        </th>
                        <td class="p-2">
                            <input
                                :name="`variants[${index}][price]`"
                                type="number"
                                :value.attr="row.price"
                                :aria-label="
                                    t('store.products.form.variantPriceOf', {
                                        variant: row.variant,
                                    })
                                "
                                :class="plainFieldInputClass"
                                min="0"
                                :max="productAmountMax"
                                step="0.01"
                                required
                            />
                            <FieldError
                                :message="errors[`variants.${index}.price`]"
                            />
                        </td>
                        <td class="p-2">
                            <input
                                :name="`variants[${index}][sku]`"
                                type="text"
                                :value.attr="row.sku"
                                :aria-label="
                                    t('store.products.form.skuOf', {
                                        variant: row.variant,
                                    })
                                "
                                :class="plainFieldInputClass"
                                maxlength="255"
                            />
                            <FieldError
                                :message="errors[`variants.${index}.sku`]"
                            />
                        </td>
                        <td class="p-2">
                            <input
                                :name="`variants[${index}][qty]`"
                                type="number"
                                :value.attr="row.qty"
                                :aria-label="
                                    t('store.products.form.quantityOf', {
                                        variant: row.variant,
                                    })
                                "
                                :class="plainFieldInputClass"
                                min="0"
                                max="2147483647"
                                step="1"
                                required
                            />
                            <FieldError
                                :message="errors[`variants.${index}.qty`]"
                            />
                        </td>
                        <td class="p-2">
                            <StoreUploadField
                                :id="`variant_image_${index}`"
                                :name="`variants[${index}][image]`"
                                :model-value="row.image ?? ''"
                                :initial-files="row.files ?? []"
                                type="image"
                                :label="
                                    t('store.products.form.photoOf', {
                                        variant: row.variant,
                                    })
                                "
                                :error="errors[`variants.${index}.image`]"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <FieldError :message="errors.variants" />
    </div>
</template>

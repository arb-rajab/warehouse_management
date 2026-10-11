<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { ref } from 'vue';
import {
    search as searchProducts,
    updateMinimum,
    updateRepresentative,
} from '@/actions/App/Http/Controllers/Store/Admin/OrderConfigurationController';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type {
    StoreOrderConfigurationSettings,
    StoreOrderProduct,
    StoreStaffOption,
} from '@/types/store';

const props = defineProps<{
    settings: StoreOrderConfigurationSettings;
    selectedProduct: StoreOrderProduct | null;
    representatives: StoreStaffOption[];
}>();

const productQuery = ref(props.settings.default_specific_product);
const selectedProductName = ref(props.selectedProduct?.name ?? '');
const results = ref<StoreOrderProduct[]>([]);
const searching = ref(false);
let searchController: AbortController | null = null;

async function search(): Promise<void> {
    searchController?.abort();
    results.value = [];

    if (productQuery.value.length < 4) {
        return;
    }

    searchController = new AbortController();
    searching.value = true;

    try {
        const url = new URL(searchProducts().url, window.location.origin);
        url.searchParams.set('query', productQuery.value);
        const response = await fetch(url, {
            signal: searchController.signal,
            headers: { Accept: 'application/json' },
        });

        if (response.ok) {
            results.value = (await response.json()) as StoreOrderProduct[];
        }
    } catch (error) {
        if (!(error instanceof DOMException && error.name === 'AbortError')) {
            throw error;
        }
    } finally {
        searching.value = false;
    }
}

function selectProduct(product: StoreOrderProduct): void {
    productQuery.value = product.mat_id;
    selectedProductName.value = product.name;
    results.value = [];
}
</script>

<template>
    <Head :title="t('store.orderConfiguration.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.orderConfiguration.title')" />
        <div class="grid gap-6 xl:grid-cols-2">
            <Form
                :action="updateMinimum()"
                class="space-y-4 rounded-md border border-gray-200 p-4 dark:border-neutral-800"
                #default="{ errors, processing }"
            >
                <h2 class="font-semibold">
                    {{ t('store.orderConfiguration.minimumTitle') }}
                </h2>
                <label class="flex cursor-pointer items-center gap-2 text-sm">
                    <input
                        type="hidden"
                        name="minimum_order_amount_check"
                        value="0"
                    />
                    <input
                        type="checkbox"
                        name="minimum_order_amount_check"
                        value="1"
                        :checked="settings.minimum_order_amount_check"
                        class="h-4 w-4 cursor-pointer rounded border-gray-300"
                    />
                    {{ t('store.orderConfiguration.minimumEnabled') }}
                </label>
                <FormField
                    id="minimum_order_amount"
                    :label="t('store.orderConfiguration.minimumAmount')"
                    type="number"
                    :value="settings.minimum_order_amount"
                    :error="errors.minimum_order_amount"
                    min="0"
                    step="0.01"
                    required
                />
                <div>
                    <label
                        for="default_specific_product"
                        :class="fieldLabelClass"
                    >
                        {{ t('store.orderConfiguration.defaultProduct') }}
                    </label>
                    <div class="relative">
                        <Search
                            class="absolute start-3 top-2.5 h-4 w-4 text-gray-400"
                        />
                        <input
                            id="default_specific_product"
                            v-model="productQuery"
                            name="default_specific_product"
                            type="search"
                            maxlength="100"
                            required
                            :placeholder="
                                t('store.orderConfiguration.productPlaceholder')
                            "
                            :class="[plainFieldInputClass, 'ps-9']"
                            @input="search"
                        />
                    </div>
                    <p
                        v-if="errors.default_specific_product"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ errors.default_specific_product }}
                    </p>
                    <p
                        v-if="selectedProductName"
                        class="mt-2 text-sm text-gray-600 dark:text-neutral-300"
                    >
                        {{
                            t('store.orderConfiguration.selectedProduct', {
                                name: selectedProductName,
                            })
                        }}
                    </p>
                    <p v-if="searching" class="mt-2 text-sm text-gray-500">
                        {{ t('store.orderConfiguration.searching') }}
                    </p>
                    <div
                        v-else-if="results.length"
                        class="mt-2 overflow-hidden rounded-md border border-gray-200 dark:border-neutral-800"
                    >
                        <button
                            v-for="product in results"
                            :key="product.id"
                            type="button"
                            class="block w-full cursor-pointer px-3 py-2 text-start text-sm hover:bg-gray-50 dark:hover:bg-neutral-800"
                            @click="selectProduct(product)"
                        >
                            <span class="font-medium">{{
                                product.mat_id
                            }}</span>
                            — {{ product.name }}
                        </button>
                    </div>
                </div>
                <SubmitButton
                    :label="t('common.save')"
                    :processing-label="t('common.saving')"
                    :processing="processing"
                />
            </Form>

            <Form
                :action="updateRepresentative()"
                class="space-y-4 rounded-md border border-gray-200 p-4 dark:border-neutral-800"
                #default="{ errors, processing }"
            >
                <h2 class="font-semibold">
                    {{ t('store.orderConfiguration.representativeTitle') }}
                </h2>
                <label class="flex cursor-pointer items-center gap-2 text-sm">
                    <input
                        type="hidden"
                        name="default_representative_check"
                        value="0"
                    />
                    <input
                        type="checkbox"
                        name="default_representative_check"
                        value="1"
                        :checked="settings.default_representative_check"
                        class="h-4 w-4 cursor-pointer rounded border-gray-300"
                    />
                    {{ t('store.orderConfiguration.representativeEnabled') }}
                </label>
                <div>
                    <label
                        for="default_representative_id"
                        :class="fieldLabelClass"
                    >
                        {{ t('store.orderConfiguration.representative') }}
                    </label>
                    <select
                        id="default_representative_id"
                        name="default_representative_id"
                        required
                        :class="plainFieldInputClass"
                    >
                        <option
                            value=""
                            disabled
                            :selected="!settings.default_representative_id"
                        >
                            {{
                                t(
                                    'store.orderConfiguration.representativePlaceholder',
                                )
                            }}
                        </option>
                        <option
                            v-for="representative in representatives"
                            :key="representative.id"
                            :value="representative.id"
                            :selected="
                                settings.default_representative_id ===
                                representative.id
                            "
                        >
                            {{ representative.name }}
                        </option>
                    </select>
                    <p
                        v-if="errors.default_representative_id"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ errors.default_representative_id }}
                    </p>
                </div>
                <SubmitButton
                    :label="t('common.save')"
                    :processing-label="t('common.saving')"
                    :processing="processing"
                />
            </Form>
        </div>
    </StoreAdminLayout>
</template>

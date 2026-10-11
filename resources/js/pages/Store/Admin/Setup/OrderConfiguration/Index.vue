<script setup lang="ts">
import { search } from '@/actions/App/Http/Controllers/Store/Admin/ProductSearchController';

import { Form, Head, useHttp } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { ref, onBeforeUnmount } from 'vue';
import {
    update,
    updateRepresentative,
} from '@/actions/App/Http/Controllers/Store/Admin/OrderConfigurationController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import StoreBooleanField from '@/components/StoreBooleanField.vue';
import StoreSelectField from '@/components/StoreSelectField.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { filterTriggerButtonClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { firstError } from '@/lib/pageErrors';
import { useStoreAccess } from '@/lib/storeAccess';
import type {
    StoreReferenceOption,
    OrderConfigurationProduct,
} from '@/types/store';
const props = defineProps<{
    settings: {
        minimum_order_amount_check: boolean;
        minimum_order_amount: string | number;
        default_specific_product: string;
        default_representative_check: boolean;
        default_representative_id: string | number | null;
    };
    selectedProduct: OrderConfigurationProduct | null;
    representatives: StoreReferenceOption[];
}>();
const access = useStoreAccess();
const productField = ref<InstanceType<typeof FormField> | null>(null);
const selected = ref(props.selectedProduct);
const results = ref<OrderConfigurationProduct[]>([]);
const http = useHttp<{ query: string }, OrderConfigurationProduct[]>({
    query: '',
});
const searched = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;
let requestVersion = 0;
function onSearch(event: Event): void {
    const query = (event.target as HTMLInputElement).value;
    clearTimeout(timer);
    http.cancel();
    const version = ++requestVersion;
    results.value = [];
    selected.value = null;
    searched.value = false;

    if (query.length < 4) {
        return;
    }

    timer = setTimeout(async () => {
        http.query = query;

        try {
            const data = await http.get(search.url());

            if (version === requestVersion) {
                results.value = data;
                searched.value = true;
            }
        } catch {
            /* useHttp exposes errors; cancellation must not restore old results. */
        }
    }, 300);
}
function selectProduct(product: OrderConfigurationProduct): void {
    ++requestVersion;
    clearTimeout(timer);
    http.cancel();
    const input = productField.value?.$el.querySelector(
        'input',
    ) as HTMLInputElement | null;

    if (input) {
        input.value = product.mat_id;
    }

    selected.value = product;
    results.value = [];
    searched.value = false;
}
onBeforeUnmount(() => {
    clearTimeout(timer);
    ++requestVersion;
    http.cancel();
});
</script>

<template>
    <StoreAdminLayout>
        <Head :title="t('store.orderConfiguration.title')" />
        <PageHeader :title="t('store.orderConfiguration.title')" />
        <div
            v-if="access.can('order_configuration')"
            class="grid gap-8 md:grid-cols-2"
        >
            <Form
                :action="update()"
                class="space-y-4"
                #default="{ errors, processing }"
            >
                <h2 class="text-lg font-semibold">
                    {{ t('store.orderConfiguration.minimum') }}
                </h2>
                <StoreBooleanField
                    name="minimum_order_amount_check"
                    :label="t('store.orderConfiguration.minimumCheck')"
                    :value="settings.minimum_order_amount_check"
                    :error="errors.minimum_order_amount_check"
                />
                <FormField
                    id="minimum_order_amount"
                    :label="t('store.orderConfiguration.amount')"
                    type="number"
                    :value="settings.minimum_order_amount"
                    :error="errors.minimum_order_amount"
                    required
                    min="0"
                    step="any"
                />
                <FormField
                    ref="productField"
                    id="default_specific_product"
                    :label="t('store.orderConfiguration.product')"
                    :value="settings.default_specific_product"
                    :error="errors.default_specific_product"
                    required
                    maxlength="255"
                    @input="onSearch"
                />
                <p class="text-sm">
                    {{ t('store.orderConfiguration.searchHint') }}
                </p>
                <p v-if="selected" class="text-sm">
                    {{
                        t('store.orderConfiguration.selected', {
                            name: selected.name,
                        })
                    }}
                </p>
                <ActionErrorBanner :message="firstError(http.errors)" />
                <p
                    v-if="http.processing"
                    class="inline-flex items-center gap-1"
                >
                    <LoaderCircle class="h-4 w-4 animate-spin" />{{
                        t('store.orderConfiguration.searching')
                    }}
                </p>
                <section v-if="results.length">
                    <h3>{{ t('store.orderConfiguration.searchResults') }}</h3>
                    <button
                        v-for="product in results"
                        :key="product.id"
                        type="button"
                        :class="[filterTriggerButtonClass, 'mt-2 block']"
                        @click="selectProduct(product)"
                    >
                        {{ product.name }} ({{ product.mat_id }})
                    </button>
                </section>
                <p v-else-if="searched">
                    {{ t('store.orderConfiguration.noResults') }}
                </p>
                <SubmitButton
                    :label="t('store.reference.save')"
                    :processing-label="t('store.reference.saving')"
                    :processing="processing"
                />
            </Form>
            <Form
                :action="updateRepresentative()"
                class="space-y-4"
                #default="{ errors, processing }"
            >
                <h2 class="text-lg font-semibold">
                    {{ t('store.orderConfiguration.representative') }}
                </h2>
                <StoreBooleanField
                    name="default_representative_check"
                    :label="t('store.orderConfiguration.representativeCheck')"
                    :value="settings.default_representative_check"
                    :error="errors.default_representative_check"
                />
                <StoreSelectField
                    id="default_representative_id"
                    :label="t('store.orderConfiguration.representative')"
                    :options="representatives"
                    :value="settings.default_representative_id"
                    :error="errors.default_representative_id"
                    required
                />
                <SubmitButton
                    :label="t('store.reference.save')"
                    :processing-label="t('store.reference.saving')"
                    :processing="processing"
                />
            </Form>
        </div>
    </StoreAdminLayout>
</template>

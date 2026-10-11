<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Pencil, Plus } from '@lucide/vue';
import { ref } from 'vue';
import {
    index as currenciesIndex,
    store,
    update,
    updateDefault,
    updateFormats,
    updateStatus,
} from '@/actions/App/Http/Controllers/Store/Admin/CurrencyController';
import DataTable from '@/components/DataTable.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreCurrencyFormFields from '@/components/StoreCurrencyFormFields.vue';
import StoreReferenceStatusForm from '@/components/StoreReferenceStatusForm.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { Paginated } from '@/types/admin';
import type { StoreCurrency, StoreCurrencySettings } from '@/types/store';

const props = defineProps<{
    currencies: Paginated<StoreCurrency>;
    activeCurrencies: StoreCurrency[];
    settings: StoreCurrencySettings;
    filters: { search?: string; per_page: number };
}>();

const createOpen = ref(false);
const editing = ref<StoreCurrency | null>(null);

function onPerPageChange(perPage: number): void {
    router.get(
        currenciesIndex().url,
        { search: props.filters.search ?? '', per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('store.currencies.index.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.currencies.index.title')">
            <button
                type="button"
                class="inline-flex cursor-pointer items-center gap-1.5 rounded-md bg-gray-900 px-3 py-2 text-sm font-medium text-white hover:bg-gray-700 dark:bg-white dark:text-gray-900"
                @click="createOpen = true"
            >
                <Plus class="h-4 w-4" />
                {{ t('store.currencies.index.add') }}
            </button>
        </PageHeader>

        <div class="mb-6 grid gap-6 xl:grid-cols-2">
            <Form
                :action="updateDefault()"
                class="space-y-4 rounded-md border border-gray-200 p-4 dark:border-neutral-800"
                #default="{ errors, processing }"
            >
                <h2 class="font-semibold">
                    {{ t('store.currencies.settings.defaultTitle') }}
                </h2>
                <div>
                    <label
                        for="system_default_currency"
                        :class="fieldLabelClass"
                    >
                        {{ t('store.currencies.settings.defaultCurrency') }}
                    </label>
                    <select
                        id="system_default_currency"
                        name="system_default_currency"
                        required
                        :class="plainFieldInputClass"
                    >
                        <option
                            v-for="currency in activeCurrencies"
                            :key="currency.id"
                            :value="currency.id"
                            :selected="
                                settings.system_default_currency === currency.id
                            "
                        >
                            {{ currency.name }}
                        </option>
                    </select>
                    <p
                        v-if="errors.system_default_currency"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ errors.system_default_currency }}
                    </p>
                </div>
                <SubmitButton
                    :label="t('common.save')"
                    :processing-label="t('common.saving')"
                    :processing="processing"
                />
            </Form>

            <Form
                :action="updateFormats()"
                class="grid gap-4 rounded-md border border-gray-200 p-4 sm:grid-cols-3 dark:border-neutral-800"
                #default="{ processing }"
            >
                <h2 class="font-semibold sm:col-span-3">
                    {{ t('store.currencies.settings.formatsTitle') }}
                </h2>
                <label :class="fieldLabelClass">
                    {{ t('store.currencies.settings.symbolFormat') }}
                    <select
                        name="symbol_format"
                        required
                        :class="plainFieldInputClass"
                    >
                        <option
                            v-for="value in [1, 2, 3, 4]"
                            :key="value"
                            :value="value"
                            :selected="settings.symbol_format === value"
                        >
                            {{ t(`store.currencies.settings.symbol${value}`) }}
                        </option>
                    </select>
                </label>
                <label :class="fieldLabelClass">
                    {{ t('store.currencies.settings.decimalSeparator') }}
                    <select
                        name="decimal_separator"
                        required
                        :class="plainFieldInputClass"
                    >
                        <option
                            :selected="settings.decimal_separator === 1"
                            value="1"
                        >
                            1,23,456.70
                        </option>
                        <option
                            :selected="settings.decimal_separator === 2"
                            value="2"
                        >
                            1.23.456,70
                        </option>
                    </select>
                </label>
                <label :class="fieldLabelClass">
                    {{ t('store.currencies.settings.decimals') }}
                    <select
                        name="no_of_decimals"
                        required
                        :class="plainFieldInputClass"
                    >
                        <option
                            v-for="value in [0, 1, 2, 3]"
                            :key="value"
                            :value="value"
                            :selected="settings.no_of_decimals === value"
                        >
                            {{ value }}
                        </option>
                    </select>
                </label>
                <SubmitButton
                    class="sm:col-span-3"
                    :label="t('common.save')"
                    :processing-label="t('common.saving')"
                    :processing="processing"
                />
            </Form>
        </div>

        <Form
            :action="currenciesIndex()"
            class="mb-4 flex max-w-xl items-end gap-2"
            #default="{ processing }"
        >
            <div class="flex-1">
                <FormField
                    id="search"
                    :label="t('store.reference.search')"
                    :value="filters.search"
                    maxlength="255"
                />
            </div>
            <input type="hidden" name="per_page" :value="filters.per_page" />
            <SubmitButton
                :label="t('store.reference.filter')"
                :processing-label="t('store.reference.filtering')"
                :processing="processing"
            />
        </Form>

        <DataTable
            :columns="[
                t('store.currencies.index.columnName'),
                t('store.currencies.index.columnSymbol'),
                t('store.currencies.index.columnCode'),
                t('store.currencies.index.columnRate'),
                t('store.currencies.index.columnStatus'),
                t('store.reference.actions'),
            ]"
            :rows="currencies.data"
            :empty-message="t('store.currencies.index.empty')"
        >
            <template #row="{ row: currency }">
                <td class="px-4 py-2 font-medium">{{ currency.name }}</td>
                <td class="px-4 py-2">{{ currency.symbol }}</td>
                <td class="px-4 py-2">{{ currency.code }}</td>
                <td class="px-4 py-2">{{ currency.exchange_rate }}</td>
                <td class="px-4 py-2">
                    <span
                        v-if="
                            currency.active &&
                            currency.id === settings.system_default_currency
                        "
                        class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800 dark:bg-green-950 dark:text-green-300"
                    >
                        {{ t('store.currencies.index.default') }}
                    </span>
                    <StoreReferenceStatusForm
                        v-else
                        :action="updateStatus(currency, { mergeQuery: {} })"
                        :active="currency.active"
                    />
                </td>
                <td class="px-4 py-2">
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1 rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-300"
                        @click="editing = currency"
                    >
                        <Pencil class="h-3.5 w-3.5" />
                        {{ t('common.edit') }}
                    </button>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="currencies.meta.links"
            :per-page="filters.per_page"
            @update:per-page="onPerPageChange"
        />

        <FilterDialog
            v-model:open="createOpen"
            :title="t('store.currencies.create.title')"
            :close-label="t('common.close')"
        >
            <Form
                :action="store()"
                class="space-y-4 pb-6"
                #default="{ errors, processing }"
                @success="createOpen = false"
            >
                <StoreCurrencyFormFields :errors="errors" />
                <SubmitButton
                    :label="t('common.save')"
                    :processing-label="t('common.saving')"
                    :processing="processing"
                />
            </Form>
        </FilterDialog>

        <FilterDialog
            :open="editing !== null"
            :title="t('store.currencies.edit.title')"
            :close-label="t('common.close')"
            @update:open="!$event && (editing = null)"
        >
            <Form
                v-if="editing"
                :action="update(editing)"
                class="space-y-4 pb-6"
                #default="{ errors, processing }"
                @success="editing = null"
            >
                <StoreCurrencyFormFields :currency="editing" :errors="errors" />
                <SubmitButton
                    :label="t('common.save')"
                    :processing-label="t('common.saving')"
                    :processing="processing"
                />
            </Form>
        </FilterDialog>
    </StoreAdminLayout>
</template>

<script setup lang="ts">
import { Head, Form, router } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { ref, watch } from 'vue';
import {
    index as currenciesIndex,
    create,
    edit,
    updateStatus,
    configure,
} from '@/actions/App/Http/Controllers/Store/Admin/CurrencyController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreCurrencyDialog from '@/components/StoreCurrencyDialog.vue';
import StoreSelectField from '@/components/StoreSelectField.vue';
import StoreStatusButton from '@/components/StoreStatusButton.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated } from '@/types/admin';
import type { StoreCurrency, StoreReferenceOption } from '@/types/store';
const props = defineProps<{
    currencies: Paginated<StoreCurrency>;
    filters: { per_page: number; search: string | null };
    activeCurrencies: StoreReferenceOption[];
    settings: {
        system_default_currency: number;
        symbol_format: number;
        decimal_separator: number;
        no_of_decimals: number;
    };
    dialog: 'create' | 'edit' | null;
    selectedCurrency: StoreCurrency | null;
}>();
const access = useStoreAccess();
const dialogOpen = ref(props.dialog !== null);
const selected = ref(props.selectedCurrency);
watch(
    () => props.dialog,
    () => {
        selected.value = props.selectedCurrency;
        dialogOpen.value = props.dialog !== null;
    },
);
function openCreate(): false {
    selected.value = null;
    dialogOpen.value = true;

    return false;
}
function openEdit(currency: StoreCurrency): false {
    selected.value = currency;
    dialogOpen.value = true;

    return false;
}
function changePerPage(per_page: number): void {
    router.get(
        currenciesIndex.url(),
        { ...props.filters, per_page },
        { preserveState: true, replace: true },
    );
}
function filter(event: Event): void {
    router.get(
        currenciesIndex.url(),
        {
            ...Object.fromEntries(
                new FormData(event.target as HTMLFormElement),
            ),
            per_page: props.filters.per_page,
        },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <StoreAdminLayout>
        <Head :title="t('store.currencies.title')" />
        <PageHeader :title="t('store.currencies.title')">
            <AddResourceLink
                v-if="access.can('currency_setup')"
                :href="create()"
                :label="t('store.currencies.add')"
                :on-before="openCreate"
            />
        </PageHeader>
        <form
            class="mb-6 flex flex-wrap items-end gap-3"
            @submit.prevent="filter"
        >
            <FormField
                id="search"
                :label="t('store.reference.search')"
                :value="filters.search ?? ''"
                maxlength="255"
            />
            <SubmitButton
                :label="t('store.reference.filter')"
                :processing-label="t('store.reference.filter')"
                :processing="false"
            />
        </form>
        <DataTable
            :rows="currencies.data"
            :columns="[
                t('store.reference.name'),
                t('store.currencies.symbol'),
                t('store.currencies.code'),
                t('store.currencies.rate'),
                t('store.reference.status'),
                t('store.reference.actions'),
            ]"
            :empty-message="t('store.reference.empty')"
        >
            <template #row="{ row }">
                <td class="px-4 py-2">{{ row.name }}</td>
                <td class="px-4 py-2">{{ row.symbol }}</td>
                <td class="px-4 py-2">{{ row.code }}</td>
                <td class="px-4 py-2">{{ row.exchange_rate }}</td>
                <td class="px-4 py-2">
                    <StoreStatusButton
                        v-if="access.can('currency_setup')"
                        :action="updateStatus(row, { mergeQuery: {} })"
                        :active="row.status"
                        :label="row.name"
                        :locked="
                            row.status &&
                            row.id === settings.system_default_currency
                        "
                    />
                </td>
                <td class="px-4 py-2">
                    <TableActionLink
                        v-if="access.can('currency_setup')"
                        :href="edit(row)"
                        :on-before="() => openEdit(row)"
                    >
                        <Pencil class="h-3.5 w-3.5" />{{
                            t('store.reference.edit')
                        }}</TableActionLink
                    >
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="currencies.meta.links"
            :per-page="filters.per_page"
            @update:per-page="changePerPage"
        />
        <StoreCurrencyDialog
            v-if="access.can('currency_setup')"
            v-model:open="dialogOpen"
            :currency="selected"
        />
        <section
            v-if="access.can('currency_setup')"
            class="mt-8 grid gap-8 md:grid-cols-2"
        >
            <Form
                :action="configure()"
                class="space-y-4"
                #default="{ errors, processing }"
            >
                <h2 class="text-lg font-semibold">
                    {{ t('store.currencies.defaultCurrency') }}
                </h2>
                <StoreSelectField
                    id="system_default_currency"
                    :label="t('store.currencies.defaultCurrency')"
                    :options="activeCurrencies"
                    :value="settings.system_default_currency"
                    :error="errors.system_default_currency"
                    required
                />
                <SubmitButton
                    :label="t('store.reference.save')"
                    :processing-label="t('store.reference.saving')"
                    :processing="processing"
                />
            </Form>
            <Form
                :action="configure()"
                class="space-y-4"
                #default="{ errors, processing }"
            >
                <h2 class="text-lg font-semibold">
                    {{ t('store.currencies.formats') }}
                </h2>
                <StoreSelectField
                    id="symbol_format"
                    :label="t('store.currencies.symbolFormat')"
                    :options="[
                        { id: 1, name: t('store.currencies.symbolFirst') },
                        { id: 2, name: t('store.currencies.amountFirst') },
                        { id: 3, name: t('store.currencies.symbolFirstSpace') },
                        { id: 4, name: t('store.currencies.amountFirstSpace') },
                    ]"
                    :value="settings.symbol_format"
                    :error="errors.symbol_format"
                    required
                />
                <StoreSelectField
                    id="decimal_separator"
                    :label="t('store.currencies.decimalSeparator')"
                    :options="[
                        { id: 1, name: '1,234.56' },
                        { id: 2, name: '1.234,56' },
                    ]"
                    :value="settings.decimal_separator"
                    :error="errors.decimal_separator"
                    required
                />
                <StoreSelectField
                    id="no_of_decimals"
                    :label="t('store.currencies.decimals')"
                    :options="
                        [0, 1, 2, 3].map((id) => ({ id, name: String(id) }))
                    "
                    :value="settings.no_of_decimals"
                    :error="errors.no_of_decimals"
                    required
                />
                <SubmitButton
                    :label="t('store.reference.save')"
                    :processing-label="t('store.reference.saving')"
                    :processing="processing"
                />
            </Form>
        </section>
    </StoreAdminLayout>
</template>

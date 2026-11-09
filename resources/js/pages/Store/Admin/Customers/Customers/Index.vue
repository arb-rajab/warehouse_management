<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Clock, Trash2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    create,
    repCreate,
    index as customersIndex,
    repIndex,
    edit,
    destroy,
    restore,
    ban,
    verify,
    important,
    notImportant,
    login,
    pageViews,
    bulkDestroy,
} from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import { assign as assignPackage } from '@/actions/App/Http/Controllers/Store/Admin/RepresentativePackageController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FieldError from '@/components/FieldError.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import RequiredLegend from '@/components/RequiredLegend.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import StoreColumnPicker from '@/components/StoreColumnPicker.vue';
import StoreListFilters from '@/components/StoreListFilters.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import {
    dangerButtonClass,
    fieldLabelClass,
    filterClearButtonClass,
    plainFieldInputClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import {
    availableCustomerColumns,
    customerCellText,
    formatProfit,
} from '@/lib/storeCustomerColumns';
import { useTableColumns } from '@/lib/useTableColumns';
import type { Paginated } from '@/types/admin';
import type { StoreFilterChip } from '@/types/store';
import type { StoreCustomer } from '@/types/storeCustomers';
const props = defineProps<{
    customers: Paginated<StoreCustomer>;
    filters: {
        per_page: number;
        search?: string;
        archived?: boolean;
        important?: boolean;
        verified?: boolean;
        from_api?: boolean;
        registration_completed?: boolean;
        activity_status?: string;
        profit_from?: string;
        profit_to?: string;
    };
    representatives: boolean;
    packages: { id: number; name: string; percentage: string }[];
}>();
const access = useStoreAccess();
const booleanFilters = [
    'important',
    'verified',
    'from_api',
    'registration_completed',
] as const;
type BooleanFilter = (typeof booleanFilters)[number];
/** A yes/no select's applied value; Important has no "all" and defaults to Yes. */
function flagValue(filter: BooleanFilter): '' | '0' | '1' {
    const value = props.filters[filter];

    if (value === undefined) {
        return filter === 'important' ? '1' : '';
    }

    return value ? '1' : '0';
}
/** The profit range (either bound) is set: Profits sums the orders created in it. */
const profitRangeActive = computed(() =>
    Boolean(props.filters.profit_from || props.filters.profit_to),
);
/**
 * Dialog filters only, one chip each; Important counts only when it is "No"
 * (its default is Yes). The profit range is one chip for both bounds.
 */
const appliedFilters = computed<StoreFilterChip[]>(() => {
    const chips: StoreFilterChip[] = [];

    if (!props.representatives) {
        booleanFilters
            .filter((filter) =>
                filter === 'important'
                    ? props.filters.important === false
                    : props.filters[filter] !== undefined,
            )
            .forEach((filter) =>
                chips.push({
                    key: filter,
                    label: t(`store.customers.filterLabels.${filter}`),
                    value: t(
                        flagValue(filter) === '1'
                            ? 'store.customers.yes'
                            : 'store.customers.no',
                    ),
                    keys: [filter],
                }),
            );

        if (props.filters.activity_status) {
            chips.push({
                key: 'activity_status',
                label: t('store.customers.activity'),
                value: t(
                    props.filters.activity_status === 'new_user'
                        ? 'store.customers.newUser'
                        : 'store.customers.contacted',
                ),
                keys: ['activity_status'],
            });
        }
    }

    if (profitRangeActive.value) {
        chips.push({
            key: 'profit',
            label: profitRangeText.value,
            keys: ['profit_from', 'profit_to'],
        });
    }

    return chips;
});
const listRoute = computed(() =>
    props.representatives ? repIndex : customersIndex,
);
/** "2026-11-01T14:00" read as "2026-11-01 14:00". */
function shownMoment(value: string | undefined): string {
    return (value ?? '').replace('T', ' ');
}
const profitRangeText = computed(() => {
    const from = shownMoment(props.filters.profit_from);
    const to = shownMoment(props.filters.profit_to);

    if (from && to) {
        return t('store.customers.profitRangeActive', { from, to });
    }

    return from
        ? t('store.customers.profitRangeFromOnly', { from })
        : t('store.customers.profitRangeToOnly', { to });
});
const selected = ref<number[]>([]);
const packageCustomer = ref<StoreCustomer | null>(null);
const packageOpen = ref(false);
const columns = useTableColumns({
    listId: () =>
        `customers.${props.representatives ? 'representatives' : 'customers'}`,
    // The choice used to be stored under this key, per list but not per user.
    legacyStorageKey: () =>
        `store.customers.columns.${props.representatives ? 'representatives' : 'customers'}`,
    columns: () =>
        availableCustomerColumns(
            props.representatives,
            access.value.can('edit_customer'),
        ),
});
const { visibleColumns } = columns;
const tableColumns = computed(() => [
    t('store.customers.select'),
    ...visibleColumns.value.map((column) =>
        t(
            column.key === 'profits' && profitRangeActive.value
                ? 'store.customers.profitsRange'
                : column.label,
        ),
    ),
    t('store.customers.actions'),
]);
/** Every applied filter as a query, so paging, resets and page size keep them. */
function currentQuery(
    overrides: Record<string, string | number> = {},
    keepRange = true,
): Record<string, string | number> {
    const query: Record<string, string | number> = {
        per_page: props.filters.per_page,
    };

    for (const key of ['search', 'activity_status'] as const) {
        if (props.filters[key]) {
            query[key] = props.filters[key];
        }
    }

    for (const key of ['archived', ...booleanFilters] as const) {
        const value = props.filters[key];

        if (value !== undefined) {
            query[key] = Number(value);
        }
    }

    if (keepRange) {
        for (const key of ['profit_from', 'profit_to'] as const) {
            if (props.filters[key]) {
                query[key] = props.filters[key];
            }
        }
    }

    return { ...query, ...overrides };
}
/** Change the page size, keeping every applied filter. */
function changePerPage(perPage: number): void {
    router.get(listRoute.value().url, currentQuery({ per_page: perPage }), {
        preserveState: true,
        replace: true,
    });
}
/** The same list with every filter but the profit range. */
const resetRangeHref = computed(() =>
    listRoute.value({ query: currentQuery({}, false) }),
);
function choosePackage(customer: StoreCustomer): void {
    packageCustomer.value = customer;
    packageOpen.value = true;
}
</script>
<template>
    <Head
        :title="
            t(
                representatives
                    ? 'store.customers.representatives'
                    : 'store.customers.title',
            )
        "
    />
    <StoreAdminLayout>
        <PageHeader
            :title="
                t(
                    representatives
                        ? 'store.customers.representatives'
                        : 'store.customers.title',
                )
            "
            ><AddResourceLink
                v-if="access.can('edit_customer')"
                :href="representatives ? repCreate() : create()"
                :label="t('store.customers.add')"
        /></PageHeader>
        <StoreListFilters :action="listRoute()" :chips="appliedFilters">
            <template #default="{ errors }">
                <input
                    type="hidden"
                    name="per_page"
                    :value="filters.per_page"
                />
                <label :class="fieldLabelClass"
                    >{{ t('store.customers.search')
                    }}<input
                        type="search"
                        name="search"
                        maxlength="255"
                        :value="filters.search ?? ''"
                        :class="plainFieldInputClass" /></label
                ><FieldError :message="errors.search" /><label
                    v-if="access.can('delete_customer')"
                    class="flex items-center gap-2"
                    ><input
                        type="checkbox"
                        name="archived"
                        value="1"
                        :checked="Boolean(filters.archived)"
                    />{{ t('store.customers.archived') }}</label
                >
            </template>
            <template #filters="{ errors }">
                <div
                    v-for="filter in representatives ? [] : booleanFilters"
                    :key="filter"
                >
                    <label :for="filter" :class="fieldLabelClass">{{
                        t(`store.customers.filterLabels.${filter}`)
                    }}</label>
                    <select
                        :id="filter"
                        :name="filter"
                        :class="plainFieldInputClass"
                    >
                        <option v-if="filter !== 'important'" value="">
                            {{ t('store.customers.all') }}
                        </option>
                        <option value="1" :selected="flagValue(filter) === '1'">
                            {{ t('store.customers.yes') }}
                        </option>
                        <option value="0" :selected="flagValue(filter) === '0'">
                            {{ t('store.customers.no') }}
                        </option>
                    </select>
                    <FieldError :message="errors[filter]" />
                </div>
                <div v-if="!representatives">
                    <label for="activity_status" :class="fieldLabelClass">{{
                        t('store.customers.activity')
                    }}</label>
                    <select
                        id="activity_status"
                        name="activity_status"
                        :class="plainFieldInputClass"
                    >
                        <option value="">{{ t('store.customers.all') }}</option>
                        <option
                            value="new_user"
                            :selected="filters.activity_status === 'new_user'"
                        >
                            {{ t('store.customers.newUser') }}
                        </option>
                        <option
                            value="has_been_contacted"
                            :selected="
                                filters.activity_status === 'has_been_contacted'
                            "
                        >
                            {{ t('store.customers.contacted') }}
                        </option>
                    </select>
                    <FieldError :message="errors.activity_status" />
                </div>
                <FormField
                    id="profit_from"
                    :label="t('store.customers.profitFrom')"
                    type="datetime-local"
                    :value="filters.profit_from"
                    :max="filters.profit_to"
                    :error="errors.profit_from"
                    class="dark:[color-scheme:dark]"
                />
                <FormField
                    id="profit_to"
                    :label="t('store.customers.profitTo')"
                    type="datetime-local"
                    :value="filters.profit_to"
                    :min="filters.profit_from"
                    :error="errors.profit_to"
                    class="dark:[color-scheme:dark]"
                />
                <p
                    class="text-sm text-gray-500 sm:col-span-2 dark:text-neutral-400"
                >
                    {{ t('store.customers.profitRangeHint') }}
                </p>
            </template>
        </StoreListFilters>
        <div
            v-if="profitRangeActive"
            role="status"
            class="mb-4 flex flex-wrap items-center gap-3 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-100"
        >
            <Clock class="h-4 w-4 shrink-0" />
            <span>{{ profitRangeText }}</span>
            <Link :href="resetRangeHref" :class="filterClearButtonClass"
                ><X class="h-4 w-4 shrink-0" />{{
                    t('store.customers.profitRangeReset')
                }}</Link
            >
        </div>
        <StoreColumnPicker :state="columns">
            <Form
                v-if="access.can('delete_customer') && !filters.archived"
                :action="bulkDestroy()"
                #default="{ processing, errors }"
                ><input
                    v-for="id in selected"
                    :key="id"
                    type="hidden"
                    name="id[]"
                    :value="id"
                /><FieldError :message="errors.id" /><button
                    type="submit"
                    :class="dangerButtonClass"
                    :disabled="processing || !selected.length"
                    @click="
                        (event: MouseEvent) => {
                            if (!confirmDelete(t('store.customers.selected')))
                                event.preventDefault();
                        }
                    "
                >
                    <Trash2 class="h-4 w-4 shrink-0" aria-hidden="true" />{{
                        t('store.customers.deleteSelected')
                    }}
                </button></Form
            >
        </StoreColumnPicker>
        <DataTable
            :columns="tableColumns"
            :rows="customers.data"
            :empty-message="t('store.customers.empty')"
        >
            <template #row="{ row: customer }">
                <td class="px-4 py-2">
                    <input
                        v-if="
                            access.can('delete_customer') && !filters.archived
                        "
                        v-model="selected"
                        type="checkbox"
                        :value="customer.id"
                        :aria-label="
                            t('store.customers.selectCustomer', {
                                name: customer.name,
                            })
                        "
                    />
                </td>
                <template v-for="column in visibleColumns" :key="column.key">
                    <td v-if="column.key === 'package'" class="px-4 py-2">
                        {{ customer.package?.name
                        }}<button
                            v-if="access.can('assign_representative_package')"
                            type="button"
                            @click="choosePackage(customer)"
                        >
                            {{ t('store.customers.assignPackage') }}
                        </button>
                    </td>
                    <td
                        v-else-if="column.key === 'profits'"
                        class="px-4 py-2 text-end whitespace-nowrap tabular-nums"
                    >
                        {{ formatProfit(customer.profits ?? 0) }}
                    </td>
                    <td v-else-if="column.key === 'status'" class="px-4 py-2">
                        {{
                            t(
                                customer.verified
                                    ? 'store.customers.verified'
                                    : 'store.customers.unverified',
                            )
                        }}
                        <div v-if="customer.banned">
                            {{ t('store.customers.banned') }}
                        </div>
                        <div v-if="customer.important">
                            {{ t('store.customers.important') }}
                        </div>
                    </td>
                    <td
                        v-else
                        :class="[
                            'px-4 py-2',
                            column.longText
                                ? 'max-w-xs truncate'
                                : 'whitespace-nowrap',
                        ]"
                        :title="
                            column.longText
                                ? customerCellText(customer, column.key)
                                : undefined
                        "
                    >
                        {{ customerCellText(customer, column.key) }}
                    </td>
                </template>
                <td class="px-4 py-2">
                    <div class="flex flex-nowrap gap-2">
                        <TableActionLink
                            v-if="access.can('edit_customer')"
                            :href="edit(customer)"
                            >{{ t('store.customers.edit') }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="access.can('view_all_customers')"
                            :href="pageViews(customer)"
                            >{{ t('store.customers.views') }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="access.can('edit_customer')"
                            :href="verify(customer)"
                            as="button"
                            method="post"
                            >{{
                                t('store.customers.toggleVerified')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="access.can('edit_customer')"
                            :href="
                                customer.important
                                    ? notImportant(customer)
                                    : important(customer)
                            "
                            as="button"
                            method="post"
                            >{{
                                t('store.customers.toggleImportant')
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="access.can('ban_customer')"
                            :href="ban(customer)"
                            as="button"
                            method="post"
                            >{{
                                t('store.customers.toggleBan')
                            }}</TableActionLink
                        >
                        <a
                            v-if="
                                access.can('login_as_customer') &&
                                !customer.banned &&
                                !customer.archived
                            "
                            :href="login.url(customer)"
                            >{{ t('store.customers.loginAs') }}</a
                        >
                        <TableActionLink
                            v-if="
                                access.can('delete_customer') &&
                                customer.archived
                            "
                            :href="restore(customer)"
                            as="button"
                            method="post"
                            >{{ t('store.customers.restore') }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="
                                access.can('delete_customer') &&
                                !customer.archived
                            "
                            :href="destroy(customer, { mergeQuery: {} })"
                            variant="danger"
                            as="button"
                            method="delete"
                            :on-before="() => confirmDelete(customer.name)"
                            >{{ t('store.customers.delete') }}</TableActionLink
                        >
                    </div>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="customers.meta.links"
            :per-page="filters.per_page"
            @update:per-page="changePerPage"
        />
        <FilterDialog
            v-model:open="packageOpen"
            :title="t('store.customers.assignPackage')"
            :close-label="t('common.close')"
            ><Form
                v-if="packageCustomer"
                :action="assignPackage()"
                @success="packageOpen = false"
                #default="{ processing, errors }"
                ><RequiredLegend /><input
                    type="hidden"
                    name="user_id"
                    :value="packageCustomer.id" /><label
                    for="package_id"
                    :class="fieldLabelClass"
                    >{{ t('store.customers.package') }}<RequiredMark /></label
                ><select
                    id="package_id"
                    name="package_id"
                    required
                    :class="plainFieldInputClass"
                >
                    <option
                        v-for="item in packages"
                        :key="item.id"
                        :value="item.id"
                        :selected="item.id === packageCustomer.package?.id"
                    >
                        {{ item.name }} ({{ item.percentage }}%)
                    </option></select
                ><FieldError :message="errors.package_id" /><SubmitButton
                    :label="t('store.customers.save')"
                    :processing-label="t('store.customers.saving')"
                    :processing="processing" /></Form
        ></FilterDialog>
    </StoreAdminLayout>
</template>

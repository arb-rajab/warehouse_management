<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
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
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import RequiredLegend from '@/components/RequiredLegend.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import StoreListFilters from '@/components/StoreListFilters.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import {
    countActive,
    fieldLabelClass,
    filterApplyButtonClass,
    plainFieldInputClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated } from '@/types/admin';
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
/** Dialog filters only; Important counts only when it is "No" (its default is Yes). */
const activeFilterCount = computed(() =>
    countActive([
        props.filters.important === false,
        props.filters.verified !== undefined,
        props.filters.from_api !== undefined,
        props.filters.registration_completed !== undefined,
        Boolean(props.filters.activity_status),
    ]),
);
const selected = ref<number[]>([]);
const packageCustomer = ref<StoreCustomer | null>(null);
const packageOpen = ref(false);
/** Change the page size, keeping every applied filter. */
function changePerPage(perPage: number): void {
    const query: Record<string, string | number> = { per_page: perPage };

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

    router.get(
        (props.representatives ? repIndex() : customersIndex()).url,
        query,
        { preserveState: true, replace: true },
    );
}
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
        <Form
            v-if="representatives"
            :action="repIndex()"
            class="mb-4 flex flex-wrap items-end gap-3"
        >
            <input type="hidden" name="per_page" :value="filters.per_page" />
            <label :class="fieldLabelClass"
                >{{ t('store.customers.search')
                }}<input
                    type="search"
                    name="search"
                    maxlength="255"
                    :value="filters.search ?? ''"
                    :class="plainFieldInputClass"
            /></label>
            <label
                v-if="access.can('delete_customer')"
                class="flex items-center gap-2"
                ><input
                    type="checkbox"
                    name="archived"
                    value="1"
                    :checked="Boolean(filters.archived)"
                />{{ t('store.customers.archived') }}</label
            >
            <button type="submit" :class="filterApplyButtonClass">
                <Check class="h-4 w-4 shrink-0" />{{
                    t('common.filters.apply')
                }}
            </button>
        </Form>
        <StoreListFilters
            v-else
            :action="customersIndex()"
            :active-count="activeFilterCount"
        >
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
                <div v-for="filter in booleanFilters" :key="filter">
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
                <div>
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
            </template>
        </StoreListFilters>
        <Form
            v-if="access.can('delete_customer') && !filters.archived"
            :action="bulkDestroy()"
            #default="{ processing, errors }"
            class="mb-4"
            ><input
                v-for="id in selected"
                :key="id"
                type="hidden"
                name="id[]"
                :value="id"
            /><FieldError :message="errors.id" /><button
                type="submit"
                :disabled="processing || !selected.length"
                @click="
                    (event: MouseEvent) => {
                        if (!confirmDelete(t('store.customers.selected')))
                            event.preventDefault();
                    }
                "
            >
                {{ t('store.customers.deleteSelected') }}
            </button></Form
        >
        <DataTable
            :columns="[
                t('store.customers.select'),
                t('store.customers.fields.name'),
                t('store.customers.fields.email'),
                t('store.customers.fields.phone'),
                ...(representatives ? [t('store.customers.package')] : []),
                t('store.customers.status'),
                t('store.customers.actions'),
            ]"
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
                <td class="px-4 py-2">
                    {{ customer.name }}
                    <div>{{ customer.company_name }}</div>
                </td>
                <td class="px-4 py-2">{{ customer.email }}</td>
                <td class="px-4 py-2">{{ customer.phone }}</td>
                <td v-if="representatives" class="px-4 py-2">
                    {{ customer.package?.name
                    }}<button
                        v-if="access.can('assign_representative_package')"
                        type="button"
                        @click="choosePackage(customer)"
                    >
                        {{ t('store.customers.assignPackage') }}
                    </button>
                </td>
                <td class="px-4 py-2">
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
                <td class="px-4 py-2">
                    <div class="flex flex-wrap gap-2">
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

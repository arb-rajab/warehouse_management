<script setup lang="ts">
import { Head, Form, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
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
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { plainFieldInputClass, fieldLabelClass } from '@/lib/filters';
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
const search = ref(props.filters.search ?? '');
const archived = ref(Boolean(props.filters.archived));
const booleanFilters = [
    'important',
    'verified',
    'from_api',
    'registration_completed',
] as const;
const flags = reactive<Record<(typeof booleanFilters)[number], string>>({
    important: props.representatives
        ? ''
        : props.filters.important === false
          ? '0'
          : '1',
    verified:
        props.filters.verified === undefined
            ? ''
            : props.filters.verified
              ? '1'
              : '0',
    from_api:
        props.filters.from_api === undefined
            ? ''
            : props.filters.from_api
              ? '1'
              : '0',
    registration_completed:
        props.filters.registration_completed === undefined
            ? ''
            : props.filters.registration_completed
              ? '1'
              : '0',
});
const activity = ref(props.filters.activity_status ?? '');
const selected = ref<number[]>([]);
const packageCustomer = ref<StoreCustomer | null>(null);
const packageOpen = ref(false);
function apply(perPage = props.filters.per_page): void {
    router.get(
        (props.representatives ? repIndex() : customersIndex()).url,
        {
            search: search.value,
            archived: archived.value ? 1 : 0,
            per_page: perPage,
            ...Object.fromEntries(
                booleanFilters
                    .filter((key) => flags[key] !== '')
                    .map((key) => [key, flags[key]]),
            ),
            ...(activity.value ? { activity_status: activity.value } : {}),
        },
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
        <form class="mb-4 flex flex-wrap gap-3" @submit.prevent="apply()">
            <label :class="fieldLabelClass"
                >{{ t('store.customers.search')
                }}<input
                    v-model="search"
                    type="search"
                    maxlength="255"
                    :class="plainFieldInputClass" /></label
            ><label
                v-if="access.can('delete_customer')"
                class="flex items-center gap-2"
                ><input v-model="archived" type="checkbox" />{{
                    t('store.customers.archived')
                }}</label
            ><button type="submit">{{ t('common.filters.apply') }}</button>
        </form>
        <div v-if="!representatives" class="mb-4 flex flex-wrap gap-3">
            <label
                v-for="filter in booleanFilters"
                :key="filter"
                :class="fieldLabelClass"
                >{{ t(`store.customers.filterLabels.${filter}`)
                }}<select v-model="flags[filter]" :class="plainFieldInputClass">
                    <option v-if="filter !== 'important'" value="">
                        {{ t('store.customers.all') }}
                    </option>
                    <option value="1">{{ t('store.customers.yes') }}</option>
                    <option value="0">{{ t('store.customers.no') }}</option>
                </select></label
            >
            <label :class="fieldLabelClass"
                >{{ t('store.customers.activity')
                }}<select v-model="activity" :class="plainFieldInputClass">
                    <option value="">{{ t('store.customers.all') }}</option>
                    <option value="new_user">
                        {{ t('store.customers.newUser') }}
                    </option>
                    <option value="has_been_contacted">
                        {{ t('store.customers.contacted') }}
                    </option>
                </select></label
            >
        </div>
        <Form
            v-if="access.can('delete_customer') && !archived"
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
                        v-if="access.can('delete_customer') && !archived"
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
            @update:per-page="apply"
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

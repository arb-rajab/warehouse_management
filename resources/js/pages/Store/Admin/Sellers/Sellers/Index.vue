<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Ban, Check, Eye, LogIn, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { payment_modal } from '@/actions/App/Http/Controllers/SellerController';
import {
    approved,
    ban,
    bulkDestroy,
    create,
    destroy,
    edit,
    index as sellersIndex,
    login,
    verification,
} from '@/actions/App/Http/Controllers/Store/Admin/SellerController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreSellerProfileDialog from '@/components/StoreSellerProfileDialog.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import {
    fieldLabelClass,
    filterApplyButtonClass,
    plainFieldInputClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated } from '@/types/admin';
import type { StoreSeller } from '@/types/store';

const props = defineProps<{
    sellers: Paginated<StoreSeller>;
    filters: {
        per_page: number;
        search: string;
        approved_status: number | null;
    };
    payouts: boolean;
    payment_links: boolean;
    payment_csrf: string | null;
}>();
const access = useStoreAccess();
const search = ref(props.filters.search);
const approval = ref(props.filters.approved_status?.toString() ?? '');
const selected = ref<number[]>([]);
const profileId = ref<number | null>(null);
const profileOpen = ref(false);
const columns = computed(() => [
    t('store.sellers.select'),
    t('store.sellers.shopName'),
    t('store.sellers.phone'),
    t('store.sellers.email'),
    t('store.sellers.verificationInfo'),
    t('store.sellers.approval'),
    t('store.sellers.products_count'),
    ...(props.payouts ? [t('store.sellers.due')] : []),
    t('store.sellers.options'),
]);
function apply(perPage = props.filters.per_page): void {
    selected.value = [];
    router.get(
        sellersIndex.url(),
        {
            search: search.value,
            approved_status: approval.value,
            per_page: perPage,
        },
        { preserveState: true, replace: true },
    );
}
function confirmBan(seller: StoreSeller): boolean {
    return confirm(
        t(
            seller.banned
                ? 'store.sellers.confirmUnban'
                : 'store.sellers.confirmBan',
        ),
    );
}
function showProfile(seller: StoreSeller): void {
    profileId.value = seller.id;
    profileOpen.value = true;
}
</script>

<template>
    <StoreAdminLayout>
        <Head :title="t('store.sellers.title')" />
        <PageHeader :title="t('store.sellers.title')"
            ><AddResourceLink
                v-if="access.can('edit_seller')"
                :href="create()"
                :label="t('store.sellers.add')"
        /></PageHeader>
        <form
            class="mb-4 flex flex-wrap items-end gap-3"
            @submit.prevent="apply()"
        >
            <label :class="fieldLabelClass"
                >{{ t('store.sellers.search')
                }}<input
                    v-model="search"
                    type="search"
                    maxlength="255"
                    :class="plainFieldInputClass"
            /></label>
            <label :class="fieldLabelClass"
                >{{ t('store.sellers.filterApproval')
                }}<select v-model="approval" :class="plainFieldInputClass">
                    <option value="">{{ t('store.sellers.all') }}</option>
                    <option value="1">{{ t('store.sellers.approved') }}</option>
                    <option value="0">
                        {{ t('store.sellers.unapproved') }}
                    </option>
                </select></label
            >
            <button type="submit" :class="filterApplyButtonClass">
                <Check class="h-4 w-4" />{{ t('common.filters.apply') }}
            </button>
        </form>
        <label
            v-if="access.can('delete_seller')"
            class="mb-3 inline-flex items-center gap-2"
        >
            <input
                type="checkbox"
                :checked="
                    sellers.data.length > 0 &&
                    sellers.data.every((seller) => selected.includes(seller.id))
                "
                @change="
                    selected = ($event.target as HTMLInputElement).checked
                        ? sellers.data.map((seller) => seller.id)
                        : []
                "
            />
            {{ t('store.sellers.selectAll') }}
        </label>
        <Form
            v-if="access.can('delete_seller') && selected.length"
            :action="bulkDestroy({ mergeQuery: {} })"
            :on-before="() => confirmDelete(t('store.sellers.selection'))"
            @success="selected = []"
            #default="{ processing }"
        >
            <input
                v-for="id in selected"
                :key="id"
                type="hidden"
                name="id[]"
                :value="id"
            />
            <SubmitButton
                :label="t('store.sellers.deleteSelection')"
                :processing-label="t('store.sellers.deleting')"
                :processing="processing"
            />
        </Form>
        <DataTable
            :columns="columns"
            :rows="sellers.data"
            :empty-message="t('store.sellers.empty')"
        >
            <template #row="{ row }">
                <td class="px-4 py-3">
                    <input
                        v-if="access.can('delete_seller')"
                        v-model="selected"
                        type="checkbox"
                        :value="row.id"
                        :aria-label="
                            t('store.sellers.selectSeller', {
                                name: row.name ?? row.account_name,
                            })
                        "
                    /><template v-else>{{ row.id }}</template>
                </td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center gap-2"
                        ><Ban
                            v-if="row.banned"
                            :aria-label="t('store.sellers.banned')"
                            class="h-4 w-4 text-red-700 dark:text-red-400"
                        />{{ row.name }}</span
                    >
                </td>
                <td class="px-4 py-3">{{ row.phone }}</td>
                <td class="px-4 py-3">{{ row.email }}</td>
                <td class="px-4 py-3">
                    <TableActionLink
                        v-if="!row.approved && row.has_verification"
                        :href="verification(row)"
                        >{{ t('store.sellers.show') }}</TableActionLink
                    >
                </td>
                <td class="px-4 py-3">
                    <Form
                        v-if="access.can('edit_seller')"
                        :action="approved({ mergeQuery: {} })"
                        #default="{ processing }"
                        ><input type="hidden" name="id" :value="row.id" /><input
                            type="hidden"
                            name="status"
                            :value="row.approved ? 0 : 1"
                        /><button
                            type="submit"
                            role="switch"
                            :aria-checked="row.approved"
                            :aria-label="
                                t('store.sellers.approvalFor', {
                                    name: row.name ?? row.account_name,
                                })
                            "
                            :disabled="processing"
                            class="inline-flex cursor-pointer items-center gap-1 rounded border px-2 py-1 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <Check v-if="row.approved" class="h-4 w-4" />{{
                                t(
                                    row.approved
                                        ? 'store.sellers.approved'
                                        : 'store.sellers.unapproved',
                                )
                            }}
                        </button></Form
                    >
                    <span v-else>{{
                        t(
                            row.approved
                                ? 'store.sellers.approved'
                                : 'store.sellers.unapproved',
                        )
                    }}</span>
                </td>
                <td class="px-4 py-3">{{ row.products_count }}</td>
                <td v-if="payouts" class="px-4 py-3">
                    {{ row.due
                    }}<span v-if="row.due_to_admin">
                        ({{ t('store.sellers.dueToAdmin') }})</span
                    >
                </td>
                <td class="px-4 py-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-if="access.can('view_seller_profile')"
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1 text-blue-700 underline dark:text-blue-400"
                            @click="showProfile(row)"
                        >
                            <Eye class="h-4 w-4" />{{
                                t('store.sellers.profile')
                            }}
                        </button>
                        <a
                            v-if="access.can('login_as_seller')"
                            :href="login.url(row)"
                            class="inline-flex items-center gap-1 text-blue-700 underline dark:text-blue-400"
                            ><LogIn class="h-4 w-4 rtl:rotate-180" />{{
                                t('store.sellers.login')
                            }}</a
                        >
                        <form
                            v-if="payment_links && access.can('pay_to_seller')"
                            :action="payment_modal.url()"
                            method="post"
                        >
                            <input
                                type="hidden"
                                name="_token"
                                :value="payment_csrf"
                            /><input
                                type="hidden"
                                name="id"
                                :value="row.id"
                            /><button
                                type="submit"
                                class="cursor-pointer text-blue-700 underline dark:text-blue-400"
                            >
                                {{ t('store.sellers.payment') }}
                            </button>
                        </form>
                        <a
                            v-if="
                                payment_links &&
                                access.can('seller_payment_history') &&
                                row.payment_history_url
                            "
                            :href="row.payment_history_url"
                            class="text-blue-700 underline dark:text-blue-400"
                            >{{ t('store.sellers.paymentHistory') }}</a
                        >
                        <TableActionLink
                            v-if="access.can('edit_seller')"
                            :href="edit(row)"
                            >{{ t('store.sellers.edit') }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="access.can('ban_seller')"
                            :href="ban(row, { mergeQuery: {} })"
                            as="button"
                            class="cursor-pointer"
                            :on-before="() => confirmBan(row)"
                            >{{
                                t(
                                    row.banned
                                        ? 'store.sellers.unban'
                                        : 'store.sellers.ban',
                                )
                            }}</TableActionLink
                        >
                        <TableActionLink
                            v-if="access.can('delete_seller')"
                            :href="destroy(row, { mergeQuery: {} })"
                            as="button"
                            class="cursor-pointer"
                            variant="danger"
                            :on-before="
                                () =>
                                    confirmDelete(row.name ?? row.account_name)
                            "
                            ><Trash2 class="h-4 w-4" />{{
                                t('store.sellers.delete')
                            }}</TableActionLink
                        >
                    </div>
                </td>
            </template>
        </DataTable>
        <Pagination
            :links="sellers.meta.links"
            :per-page="filters.per_page"
            @update:per-page="apply"
        />
        <StoreSellerProfileDialog
            v-if="access.can('view_seller_profile')"
            v-model:open="profileOpen"
            :seller-id="profileId"
        />
    </StoreAdminLayout>
</template>

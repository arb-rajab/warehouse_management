<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Eye, Pencil, ShieldCheck, Trash2 } from '@lucide/vue';
import {
    create,
    destroy,
    edit,
    show,
} from '@/actions/App/Http/Controllers/Admin/UserController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import type { Paginated, User } from '@/types/admin';

defineProps<{
    users: Paginated<User>;
}>();

const page = usePage();
</script>

<template>
    <Head :title="t('users.index.title')" />

    <AdminLayout>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ t('users.index.title') }}</h1>
            <AddResourceLink
                :href="create()"
                :label="t('users.index.addUser')"
            />
        </div>

        <DataTable
            :columns="[
                t('users.index.columnName'),
                t('users.index.columnEmail'),
                t('users.index.columnRole'),
                t('users.index.columnActions'),
            ]"
            :rows="users.data"
            :empty-message="t('users.index.empty')"
        >
            <template #row="{ row: user }">
                <td
                    class="px-4 py-2 font-medium text-gray-900 dark:text-neutral-100"
                >
                    {{ user.name }}
                </td>
                <td class="px-4 py-2">{{ user.email }}</td>
                <td class="px-4 py-2">
                    <span
                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="
                            user.is_admin
                                ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'
                                : 'bg-gray-100 text-gray-600 dark:bg-neutral-800 dark:text-neutral-400'
                        "
                    >
                        <ShieldCheck
                            v-if="user.is_admin"
                            class="h-3 w-3 shrink-0"
                        />
                        {{
                            user.is_admin
                                ? t('users.index.roleAdmin')
                                : t('users.index.roleMobile')
                        }}
                    </span>
                </td>
                <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <TableActionLink :href="show(user)">
                            <Eye class="h-3.5 w-3.5" />
                            {{ t('users.index.view') }}
                        </TableActionLink>
                        <TableActionLink :href="edit(user)">
                            <Pencil class="h-3.5 w-3.5" />
                            {{ t('users.index.edit') }}
                        </TableActionLink>
                        <TableActionLink
                            v-if="user.id !== page.props.auth.user?.id"
                            :href="destroy(user)"
                            variant="danger"
                            method="delete"
                            as="button"
                            :on-before="() => confirmDelete(user.name)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            {{ t('users.index.delete') }}
                        </TableActionLink>
                    </div>
                </td>
            </template>
        </DataTable>

        <Pagination :links="users.meta.links" />
    </AdminLayout>
</template>

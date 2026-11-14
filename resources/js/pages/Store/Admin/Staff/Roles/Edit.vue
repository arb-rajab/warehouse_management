<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Languages } from '@lucide/vue';
import {
    edit,
    index as rolesIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/RoleController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreRoleFormFields from '@/components/StoreRoleFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { selectedToggleClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { PermissionGroup, StoreLanguage, StoreRole } from '@/types/store';

/**
 * `name` is the role's name in `lang`, the language being edited: the
 * default language's is the role's own, any other only translates it.
 */
defineProps<{
    role: StoreRole;
    lang: string;
    name: string;
    languages: StoreLanguage[];
    permissionGroups: PermissionGroup[];
    permissionIds: number[];
}>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.roles.edit.title', { name: role.name })"
        :action="update(role)"
        :submit-label="t('store.roles.edit.submit')"
        :submitting-label="t('store.roles.edit.submitting')"
        :cancel-href="rolesIndex()"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <nav
            v-if="languages.length > 1"
            :aria-label="t('store.roles.edit.languages')"
            class="flex flex-wrap items-center gap-1 text-sm"
        >
            <Languages class="h-4 w-4 text-muted" />
            <Link
                v-for="language in languages"
                :key="language.code"
                :href="edit(role, { query: { lang: language.code } })"
                :aria-current="language.code === lang ? 'page' : undefined"
                class="rounded-md px-3 py-1"
                :class="
                    language.code === lang
                        ? selectedToggleClass
                        : 'text-muted hover:bg-subtle'
                "
            >
                {{ language.name }}
            </Link>
        </nav>
        <p v-if="languages.length > 1" class="text-sm text-muted">
            {{ t('store.roles.fields.nameTranslationHint') }}
        </p>
        <input type="hidden" name="lang" :value="lang" />
        <StoreRoleFormFields
            :key="lang"
            :permission-groups="permissionGroups"
            :name="name"
            :checked-permission-ids="permissionIds"
            :errors="errors"
        />
    </ResourceFormPage>
</template>

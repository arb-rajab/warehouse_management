<script setup lang="ts">
import { computed } from 'vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import { filterSectionHeadingClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { PermissionGroup } from '@/types/store';

/**
 * The store role form's fields (Store\Admin\StoreRoleRequest and
 * UpdateRoleRequest): the role's name and its permissions, one checkbox per
 * permission, grouped by the back-office section it belongs to.
 */
const props = withDefaults(
    defineProps<{
        permissionGroups: PermissionGroup[];
        name?: string;
        checkedPermissionIds?: number[];
        errors: Partial<Record<string, string>>;
    }>(),
    {
        name: '',
        checkedPermissionIds: () => [],
    },
);

/** The request reports a bad checkbox as `permissions.N`. */
const permissionsError = computed(
    () =>
        props.errors.permissions ??
        Object.entries(props.errors).find(([key]) =>
            key.startsWith('permissions.'),
        )?.[1],
);
</script>

<template>
    <div class="space-y-6">
        <div class="max-w-sm">
            <FormField
                id="name"
                :label="t('store.roles.fields.name')"
                :value="name"
                :error="errors.name"
                maxlength="50"
                required
            />
        </div>

        <div class="space-y-4">
            <h2 :class="filterSectionHeadingClass">
                {{ t('store.roles.fields.permissions') }}
            </h2>
            <FieldError :message="permissionsError" />
            <fieldset
                v-for="group in permissionGroups"
                :key="group.section"
                class="rounded-md border border-gray-200 p-3 dark:border-neutral-800"
            >
                <legend class="px-1 text-sm font-medium">
                    {{ group.label }}
                </legend>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <label
                        v-for="permission in group.permissions"
                        :key="permission.id"
                        class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-neutral-300"
                    >
                        <input
                            type="checkbox"
                            name="permissions[]"
                            :value="permission.id"
                            :checked="
                                checkedPermissionIds.includes(permission.id)
                            "
                            class="h-4 w-4 cursor-pointer rounded border-gray-300 dark:border-neutral-700"
                        />
                        {{ permission.label }}
                    </label>
                </div>
            </fieldset>
        </div>
    </div>
</template>

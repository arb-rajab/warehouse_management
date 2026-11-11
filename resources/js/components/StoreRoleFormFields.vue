<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import { filterSectionHeadingClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { PermissionGroup } from '@/types/store';

/**
 * The store role form's fields (Store\Admin\StoreRoleRequest and
 * UpdateRoleRequest): the role's name and its permissions, one checkbox per
 * permission, grouped by the back-office section it belongs to. A sensitive
 * permission (only a Super Admin may grant it, StorePermissions::SENSITIVE)
 * carries a warning; the server offers it to a Super Admin only.
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
                class="rounded-md border border-line p-3"
            >
                <legend class="px-1 text-sm font-medium">
                    {{ group.label }}
                </legend>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="permission in group.permissions"
                        :key="permission.id"
                        :class="{
                            'rounded-md border border-danger/30 p-2 sm:col-span-2 lg:col-span-3':
                                permission.sensitive,
                        }"
                        :data-sensitive="permission.sensitive ? '' : undefined"
                    >
                        <label
                            class="flex cursor-pointer items-center gap-2 text-sm text-ink"
                        >
                            <input
                                type="checkbox"
                                name="permissions[]"
                                :value="permission.id"
                                :checked="
                                    checkedPermissionIds.includes(permission.id)
                                "
                                :aria-describedby="
                                    permission.sensitive
                                        ? `permission-${permission.id}-warning`
                                        : undefined
                                "
                                class="h-4 w-4 cursor-pointer rounded border-line-strong"
                            />
                            {{ permission.label }}
                            <span
                                v-if="permission.sensitive"
                                class="inline-flex items-center gap-1 rounded-full bg-danger-soft px-2 py-0.5 text-xs font-medium text-danger-strong"
                            >
                                <TriangleAlert
                                    class="h-3.5 w-3.5"
                                    aria-hidden="true"
                                />
                                {{ t('store.roles.fields.sensitive') }}
                            </span>
                        </label>
                        <p
                            v-if="permission.sensitive"
                            :id="`permission-${permission.id}-warning`"
                            class="ms-6 mt-1 text-sm text-danger"
                        >
                            {{ t('store.roles.fields.sensitiveWarning') }}
                        </p>
                    </div>
                </div>
            </fieldset>
        </div>
    </div>
</template>

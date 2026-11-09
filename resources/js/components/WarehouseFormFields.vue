<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import { t } from '@/lib/i18n';

/**
 * Mirror ValidatesWarehouse (app/Http/Requests/Concerns) — the backend rules
 * stay authoritative, these are just client-side flood guards.
 */
const NAME_MAX_LENGTH = 100;
const CODE_MAX_LENGTH = 20;
const ADDRESS_MAX_LENGTH = 255;

withDefaults(
    defineProps<{
        /**
         * Prefixes every id this renders so a page can render this component
         * more than once without a DOM id collision — see RowFormFields.vue.
         */
        idPrefix?: string;
        name?: string;
        code?: string | null;
        address?: string | null;
        errors: Partial<Record<'name' | 'code' | 'address', string>>;
    }>(),
    {
        idPrefix: '',
        name: '',
    },
);
</script>

<template>
    <div class="space-y-4">
        <FormField
            :id="`${idPrefix}name`"
            :label="t('warehouses.fields.name')"
            :value="name"
            :error="errors.name"
            :maxlength="NAME_MAX_LENGTH"
            required
        />

        <FormField
            :id="`${idPrefix}code`"
            :label="t('warehouses.fields.code')"
            :value="code ?? ''"
            :error="errors.code"
            :maxlength="CODE_MAX_LENGTH"
        />

        <FormField
            :id="`${idPrefix}address`"
            :label="t('warehouses.fields.address')"
            :value="address ?? ''"
            :error="errors.address"
            :maxlength="ADDRESS_MAX_LENGTH"
        />
    </div>
</template>

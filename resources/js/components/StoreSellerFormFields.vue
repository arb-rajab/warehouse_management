<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import { t } from '@/lib/i18n';

defineProps<{
    name?: string;
    email?: string;
    passwordRequired?: boolean;
    errors: Record<string, string>;
}>();
</script>

<template>
    <div class="space-y-4">
        <FormField
            id="name"
            :label="t('store.sellers.name')"
            :value="name"
            :error="errors.name"
            maxlength="255"
            required
        />
        <FormField
            id="email"
            :label="t('store.sellers.email')"
            :value="email"
            :error="errors.email"
            type="email"
            maxlength="255"
            required
        />
        <FormField
            id="password"
            :label="t('store.sellers.password')"
            :error="errors.password"
            type="password"
            maxlength="255"
            autocomplete="new-password"
            :required="passwordRequired"
            :placeholder="
                passwordRequired ? '' : t('store.sellers.passwordHint')
            "
        />
    </div>
</template>

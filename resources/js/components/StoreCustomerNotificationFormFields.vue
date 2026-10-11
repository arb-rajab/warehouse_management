<script setup lang="ts">
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { NotificationCustomer } from '@/types/storeNotifications';

defineProps<{
    customers: NotificationCustomer[];
    errors: Partial<Record<'user_id' | 'title' | 'body', string>>;
}>();
</script>

<template>
    <div class="space-y-4">
        <FormField
            id="title"
            :label="t('store.notifications.fields.title')"
            :error="errors.title"
            maxlength="255"
            required
        />
        <FormField
            id="body"
            :label="t('store.notifications.fields.body')"
            :error="errors.body"
            required
        />
        <div>
            <label for="user_id" :class="fieldLabelClass">{{
                t('store.notifications.fields.customer')
            }}</label>
            <select
                id="user_id"
                name="user_id"
                required
                :class="plainFieldInputClass"
            >
                <option value="" disabled selected>
                    {{ t('store.notifications.fields.customerPlaceholder') }}
                </option>
                <option
                    v-for="customer in customers"
                    :key="customer.id"
                    :value="customer.id"
                >
                    {{ customer.name }} {{ customer.account_id ?? '' }}
                </option>
            </select>
            <FieldError :message="errors.user_id" />
        </div>
    </div>
</template>

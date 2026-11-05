<script setup lang="ts">
import { ref } from 'vue';
import { customers } from '@/actions/App/Http/Controllers/Store/Admin/CustomerNotificationController';
import FormField from '@/components/FormField.vue';
import StoreSearchSelect from '@/components/StoreSearchSelect.vue';
import { t } from '@/lib/i18n';
import type { SearchOption } from '@/types/searchSelect';

defineProps<{
    errors: Partial<Record<'user_id' | 'title' | 'body', string>>;
}>();
const customer = ref<SearchOption | null>(null);
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
        <StoreSearchSelect
            id="user_id"
            v-model="customer"
            name="user_id"
            :label="t('store.notifications.fields.customer')"
            :endpoint="customers"
            :placeholder="t('store.notifications.fields.customerPlaceholder')"
            :search-placeholder="
                t('store.notifications.fields.searchCustomers')
            "
            required
            :error="errors.user_id"
        />
    </div>
</template>

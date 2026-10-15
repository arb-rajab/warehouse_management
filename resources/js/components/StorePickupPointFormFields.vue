<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import StoreBooleanField from '@/components/StoreBooleanField.vue';
import StoreSelectField from '@/components/StoreSelectField.vue';
import { t } from '@/lib/i18n';
import type { StorePickupPoint, StoreReferenceOption } from '@/types/store';
defineProps<{
    pickupPoint?: StorePickupPoint;
    name?: string;
    address?: string;
    staffOptions: StoreReferenceOption[];
    errors: Record<string, string>;
}>();
</script>

<template>
    <div class="space-y-4">
        <FormField
            id="name"
            :label="t('store.reference.name')"
            :value="name ?? pickupPoint?.name"
            :error="errors.name"
            required
            maxlength="50"
        />
        <FormField
            id="address"
            :label="t('store.pickupPoints.address')"
            :value="address ?? pickupPoint?.address"
            :error="errors.address"
            required
            maxlength="65535"
        />
        <FormField
            id="phone"
            :label="t('store.pickupPoints.phone')"
            type="tel"
            :value="pickupPoint?.phone"
            :error="errors.phone"
            required
            maxlength="15"
        />
        <StoreSelectField
            id="staff_id"
            :label="t('store.pickupPoints.manager')"
            :options="staffOptions"
            :value="pickupPoint?.staff_id"
            :error="errors.staff_id"
            required
        />
        <StoreBooleanField
            name="pick_up_status"
            :label="t('store.reference.status')"
            :value="pickupPoint?.pick_up_status"
            :error="errors.pick_up_status"
        />
    </div>
</template>

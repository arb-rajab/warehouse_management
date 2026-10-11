<script setup lang="ts">
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StorePickupPoint, StoreStaffOption } from '@/types/store';

defineProps<{
    staffOptions: StoreStaffOption[];
    pickupPoint?: StorePickupPoint;
    name?: string;
    address?: string;
    errors: Partial<Record<string, string>>;
}>();
</script>

<template>
    <div class="space-y-4">
        <FormField
            id="name"
            :label="t('store.pickupPoints.fields.name')"
            :value="name ?? pickupPoint?.name"
            :error="errors.name"
            maxlength="50"
            required
        />
        <div>
            <label for="address" :class="fieldLabelClass">
                {{ t('store.pickupPoints.fields.address') }}
            </label>
            <textarea
                id="address"
                name="address"
                rows="5"
                maxlength="65535"
                required
                :class="plainFieldInputClass"
                :value="address ?? pickupPoint?.address"
            ></textarea>
            <FieldError :message="errors.address" />
        </div>
        <FormField
            id="phone"
            :label="t('store.pickupPoints.fields.phone')"
            :value="pickupPoint?.phone"
            :error="errors.phone"
            maxlength="15"
            required
        />
        <div>
            <label for="staff_id" :class="fieldLabelClass">
                {{ t('store.pickupPoints.fields.manager') }}
            </label>
            <select
                id="staff_id"
                name="staff_id"
                required
                :class="plainFieldInputClass"
            >
                <option value="" disabled :selected="!pickupPoint">
                    {{ t('store.pickupPoints.fields.managerPlaceholder') }}
                </option>
                <option
                    v-for="staff in staffOptions"
                    :key="staff.id"
                    :value="staff.id"
                    :selected="pickupPoint?.staff_id === staff.id"
                >
                    {{ staff.name }}
                </option>
            </select>
            <FieldError :message="errors.staff_id" />
        </div>
        <label class="flex cursor-pointer items-center gap-2 text-sm">
            <input type="hidden" name="pick_up_status" value="0" />
            <input
                type="checkbox"
                name="pick_up_status"
                value="1"
                :checked="pickupPoint?.active ?? false"
                class="h-4 w-4 cursor-pointer rounded border-gray-300 dark:border-neutral-700"
            />
            {{ t('store.pickupPoints.fields.active') }}
        </label>
        <FieldError :message="errors.pick_up_status" />
    </div>
</template>

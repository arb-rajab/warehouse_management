<script setup lang="ts">
import { computed, ref } from 'vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreUpload } from '@/types/store';
import type { StoreTruck, TruckPhoto } from '@/types/storeTruck';

const props = defineProps<{
    truck?: StoreTruck;
    photos: TruckPhoto[];
    errors: Record<string, string>;
}>();
const selected = ref(props.truck?.photos.join(',') ?? '');
const initialFiles = computed<StoreUpload[]>(() =>
    props.photos.map((photo) => ({ ...photo, type: 'image', size: null })),
);
</script>

<template>
    <div class="space-y-4">
        <FormField
            id="name"
            :label="t('store.trucks.fields.name')"
            :value="truck?.name ?? ''"
            :error="errors.name"
            maxlength="50"
            required
        />
        <FormField
            id="model"
            :label="t('store.trucks.fields.model')"
            :value="truck?.model ?? ''"
            :error="errors.model"
            maxlength="50"
        />
        <FormField
            id="max_pallets_number"
            :label="t('store.trucks.fields.capacity')"
            :value="truck?.max_pallets_number ?? ''"
            :error="errors.max_pallets_number"
            maxlength="50"
        />
        <FormField
            id="license_plate"
            :label="t('store.trucks.fields.plate')"
            :value="truck?.license_plate ?? ''"
            :error="errors.license_plate"
            maxlength="50"
        />
        <div>
            <label for="description" :class="fieldLabelClass">{{
                t('store.trucks.fields.description')
            }}</label>
            <textarea
                id="description"
                name="description"
                :default-value.camel="truck?.description ?? ''"
                maxlength="255"
                :class="plainFieldInputClass"
            />
            <FieldError :message="errors.description" />
        </div>
        <StoreUploadField
            id="photos"
            name="photos"
            v-model="selected"
            :label="t('store.trucks.fields.photos')"
            type="image"
            :multiple="true"
            :initial-files="initialFiles"
            :error="
                errors.photos ??
                Object.entries(errors).find(([key]) =>
                    key.startsWith('photos.'),
                )?.[1]
            "
        />
        <p class="text-sm text-gray-600 dark:text-neutral-300">
            {{ t('store.trucks.fields.photosHint') }}
        </p>
    </div>
</template>

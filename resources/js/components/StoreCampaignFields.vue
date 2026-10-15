<script setup lang="ts">
import { ref } from 'vue';
import FormField from '@/components/FormField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { t } from '@/lib/i18n';
import type { StoreCampaign } from '@/types/store';
const props = defineProps<{
    campaign?: StoreCampaign;
    title?: string;
    titleMax: number;
    errors: Record<string, string>;
}>();
const start = ref(props.campaign?.start_date ?? '');
const end = ref(props.campaign?.end_date ?? '');
const banner = ref(props.campaign?.banner ?? '');
</script>
<template>
    <div class="grid gap-4 md:grid-cols-2">
        <FormField
            id="title"
            :label="t('store.campaigns.fields.title')"
            :value="title ?? campaign?.title ?? ''"
            :maxlength="titleMax"
            required
            :error="errors.title"
        />
        <StoreUploadField
            id="banner"
            name="banner"
            v-model="banner"
            type="image"
            :label="t('store.campaigns.fields.banner')"
            :error="errors.banner"
        />
        <FormField
            id="start_date"
            type="datetime-local"
            step="1"
            input-class="dark:[color-scheme:dark]"
            :label="t('store.campaigns.fields.startDate')"
            :value="start"
            :max="end || undefined"
            required
            :error="errors.start_date"
            @input="start = ($event.target as HTMLInputElement).value"
        />
        <FormField
            id="end_date"
            type="datetime-local"
            step="1"
            input-class="dark:[color-scheme:dark]"
            :label="t('store.campaigns.fields.endDate')"
            :value="end"
            :min="start || undefined"
            required
            :error="errors.end_date"
            @input="end = ($event.target as HTMLInputElement).value"
        />
    </div>
</template>

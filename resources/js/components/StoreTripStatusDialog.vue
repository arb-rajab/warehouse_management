<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { updateStatus } from '@/actions/App/Http/Controllers/Store/Admin/TripController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import RequiredLegend from '@/components/RequiredLegend.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { firstError } from '@/lib/pageErrors';
import { provideRequiredFields } from '@/lib/requiredFields';
import type {
    StoreTrip,
    StoreTripListType,
    StoreTripStatus,
} from '@/types/store';

/**
 * Changes one trip's status from the trip lists, in place of the Blade list's
 * `update-trip-status-modal`. The server keeps the legacy transition rules; a
 * refusal (another open trip holds the truck) shows in the dialog.
 */
const props = defineProps<{
    trip: StoreTrip | null;
    statuses: StoreTripStatus[];
    list: StoreTripListType;
}>();
const open = defineModel<boolean>('open', { required: true });
const form = useForm({ status: '' as StoreTripStatus | '' });
watch(
    [open, () => props.trip],
    () => {
        form.clearErrors();
        form.status = props.trip?.status ?? '';
    },
    { immediate: true },
);
function submit(): void {
    if (!props.trip) {
        return;
    }

    form.submit(
        updateStatus(props.trip, { mergeQuery: { list: props.list } }),
        {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
        },
    );
}
const hasRequiredFields = provideRequiredFields();
</script>

<template>
    <FilterDialog
        v-model:open="open"
        :title="t('store.trips.status.title', { code: trip?.code ?? '' })"
        :close-label="t('common.close')"
    >
        <form id="trip-status-form" @submit.prevent="submit">
            <RequiredLegend v-if="hasRequiredFields" />
            <ActionErrorBanner :message="firstError(form.errors)" />
            <label for="trip-status" :class="fieldLabelClass"
                >{{ t('store.trips.status.label') }}<RequiredMark
            /></label>
            <select
                id="trip-status"
                v-model="form.status"
                required
                :class="plainFieldInputClass"
            >
                <option
                    v-for="status in statuses"
                    :key="status"
                    :value="status"
                >
                    {{ t(`store.trips.status.${status}`) }}
                </option>
            </select>
        </form>
        <template #footer>
            <SubmitButton
                form="trip-status-form"
                :label="t('store.trips.status.submit')"
                :processing-label="t('store.trips.status.submitting')"
                :processing="form.processing"
            />
        </template>
    </FilterDialog>
</template>

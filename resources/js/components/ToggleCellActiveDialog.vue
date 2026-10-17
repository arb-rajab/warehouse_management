<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed } from 'vue';
import { toggleActive } from '@/actions/App/Http/Controllers/Admin/CellController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { firstError } from '@/lib/pageErrors';
import type { Cell } from '@/types/admin';

const props = defineProps<{
    cell: Cell | null;
    label: string;
}>();

const open = defineModel<boolean>('open', { required: true });

const deactivating = computed(() => props.cell?.is_active ?? false);

const title = computed(() =>
    t(
        deactivating.value
            ? 'cells.toggleActive.deactivateTitle'
            : 'cells.toggleActive.reactivateTitle',
        { location: props.label },
    ),
);

const confirmText = computed(() =>
    t(
        deactivating.value
            ? 'cells.toggleActive.deactivateConfirm'
            : 'cells.toggleActive.reactivateConfirm',
        { location: props.label },
    ),
);

const submitLabel = computed(() =>
    t(
        deactivating.value
            ? 'cells.toggleActive.submitDeactivate'
            : 'cells.toggleActive.submitReactivate',
    ),
);
</script>

<template>
    <FilterDialog
        v-model:open="open"
        :title="title"
        :close-label="t('common.close')"
    >
        <Form
            v-if="cell"
            :action="toggleActive(cell, { mergeQuery: {} })"
            :options="{ preserveScroll: true }"
            class="space-y-4"
            #default="{ errors, processing }"
            @success="open = false"
        >
            <ActionErrorBanner :message="firstError(errors)" />

            <p class="text-sm text-gray-600 dark:text-neutral-300">
                {{ confirmText }}
            </p>

            <div>
                <label :for="'toggle-active-note'" :class="fieldLabelClass">
                    {{ t('cells.toggleActive.noteLabel') }}
                </label>
                <textarea
                    id="toggle-active-note"
                    name="note"
                    rows="3"
                    maxlength="1000"
                    :placeholder="t('cells.toggleActive.notePlaceholder')"
                    :class="plainFieldInputClass"
                ></textarea>
            </div>

            <SubmitButton
                :label="submitLabel"
                :processing-label="t('cells.toggleActive.submitting')"
                :processing="processing"
            />
        </Form>
    </FilterDialog>
</template>

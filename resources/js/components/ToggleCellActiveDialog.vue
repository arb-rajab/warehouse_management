<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toggleActive } from '@/actions/App/Http/Controllers/Admin/CellController';
import FilterDialog from '@/components/FilterDialog.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { Cell } from '@/types/admin';

const props = defineProps<{
    cell: Cell | null;
    label: string;
    returnTo?: 'row';
}>();

const open = defineModel<boolean>('open', { required: true });

const note = ref('');
const processing = ref(false);

watch(open, (isOpen) => {
    if (isOpen) {
        note.value = '';
    }
});

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

function submit(): void {
    if (!props.cell) {
        return;
    }

    processing.value = true;

    router.post(
        toggleActive({ cell: props.cell.id }, { mergeQuery: {} }).url,
        {
            note: note.value === '' ? null : note.value,
            return_to: props.returnTo ?? null,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                open.value = false;
            },
        },
    );
}
</script>

<template>
    <FilterDialog
        v-model:open="open"
        :title="title"
        :close-label="t('cellLog.filters.close')"
    >
        <form class="space-y-4" @submit.prevent="submit">
            <p class="text-sm text-gray-600 dark:text-neutral-300">
                {{ confirmText }}
            </p>

            <div>
                <label :for="'toggle-active-note'" :class="fieldLabelClass">
                    {{ t('cells.toggleActive.noteLabel') }}
                </label>
                <textarea
                    id="toggle-active-note"
                    v-model="note"
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
        </form>
    </FilterDialog>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import {
    store,
    updateImage,
} from '@/actions/App/Http/Controllers/Admin/ProductImageReviewController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import ProductImageReviewFields from '@/components/ProductImageReviewFields.vue';
import RequiredLegend from '@/components/RequiredLegend.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { firstError } from '@/lib/pageErrors';

const props = defineProps<{ productId: number }>();
const open = defineModel<boolean>('open', { required: true });
const ready = ref(false);
const form = useForm({
    image_review_status: '',
    image_review_note: '',
    image_source: '',
});
const upload = useForm({ image: null as File | null });
watch(
    () => props.productId,
    () => {
        form.reset();
        upload.reset();
        ready.value = false;
    },
);
function submit(): void {
    if (!ready.value || !form.image_review_status) {
        return;
    }

    form.submit(store(props.productId, { mergeQuery: {} }), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
function uploadImage(): void {
    if (!upload.image) {
        return;
    }

    upload.submit(updateImage(props.productId, { mergeQuery: {} }), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <FilterDialog
        v-model:open="open"
        :title="t('imageReview.title')"
        :close-label="t('common.close')"
    >
        <form class="space-y-4" @submit.prevent="submit">
            <RequiredLegend />
            <ActionErrorBanner :message="firstError(form.errors)" />
            <ProductImageReviewFields
                :product-id="productId"
                id-prefix="catalog-image-review"
                v-model:status="form.image_review_status"
                v-model:note="form.image_review_note"
                v-model:source="form.image_source"
                history
                @ready="ready = $event"
            />
            <SubmitButton
                :label="t('imageReview.save')"
                :processing-label="t('imageReview.saving')"
                :processing="form.processing"
                :disabled="
                    !ready || !form.image_review_status || upload.processing
                "
            />
        </form>
        <form
            class="mt-6 space-y-3 border-t border-gray-200 pt-4 dark:border-neutral-700"
            @submit.prevent="uploadImage"
        >
            <RequiredLegend />
            <ActionErrorBanner :message="firstError(upload.errors)" />
            <label for="reference-image-upload" :class="fieldLabelClass"
                >{{ t('imageReview.replace') }}<RequiredMark
            /></label>
            <input
                id="reference-image-upload"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                required
                :class="plainFieldInputClass"
                @change="
                    upload.image =
                        ($event.target as HTMLInputElement).files?.[0] ?? null
                "
            />
            <p class="text-sm text-gray-600 dark:text-neutral-300">
                {{ t('imageReview.uploadHint') }}
            </p>
            <SubmitButton
                :label="t('imageReview.upload')"
                :processing-label="t('imageReview.saving')"
                :processing="upload.processing"
                :disabled="upload.processing || form.processing"
            />
        </form>
    </FilterDialog>
</template>

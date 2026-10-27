<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
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
import { compressUploadImage, ImageUploadError } from '@/lib/imageUpload';
import { firstError } from '@/lib/pageErrors';

const props = defineProps<{ productId: number }>();
const open = defineModel<boolean>('open', { required: true });
const ready = ref(false);
const form = useForm({
    image_review_status: '',
    image_review_note: '',
    image_source: '',
});
const preparing = ref(false);
let compressionController = new AbortController();
function cancelCompression(): void {
    compressionController.abort();
    compressionController = new AbortController();
}
onBeforeUnmount(cancelCompression);
watch(open, (value) => {
    if (!value) {
        cancelCompression();
    }
});
const upload = useForm({ image: null as File | null });
watch(
    () => props.productId,
    () => {
        cancelCompression();
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
async function uploadImage(): Promise<void> {
    if (
        !upload.image ||
        preparing.value ||
        upload.processing ||
        form.processing
    ) {
        return;
    }

    const original = upload.image;
    const productId = props.productId;
    const signal = compressionController.signal;
    preparing.value = true;

    try {
        const image = await compressUploadImage(original, 1600, signal);

        if (signal.aborted || !open.value || productId !== props.productId) {
            return;
        }

        upload.image = image;
        upload.submit(updateImage(productId, { mergeQuery: {} }), {
            preserveScroll: true,
            onSuccess: () => {
                open.value = false;
            },
        });
    } catch (error) {
        if (error instanceof ImageUploadError) {
            upload.setError('image', t(`common.imageUpload.${error.code}`));
        }
    } finally {
        preparing.value = false;
    }
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
                    !ready ||
                    !form.image_review_status ||
                    preparing ||
                    upload.processing
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
                :disabled="preparing || upload.processing"
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
                :processing-label="
                    preparing
                        ? t('common.imageUpload.preparing')
                        : t('imageReview.saving')
                "
                :processing="preparing || upload.processing"
                :disabled="preparing || upload.processing || form.processing"
            />
        </form>
    </FilterDialog>
</template>

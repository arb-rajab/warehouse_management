<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { Check, LoaderCircle, TriangleAlert } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import { show } from '@/actions/App/Http/Controllers/Admin/ProductImageReviewController';
import { show as showUser } from '@/actions/App/Http/Controllers/Admin/UserController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import TableLink from '@/components/TableLink.vue';
import { formatDateTime } from '@/lib/date';
import {
    fieldLabelClass,
    filterTriggerButtonClass,
    plainFieldInputClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import { productName } from '@/lib/productName';
import type { ProductImageReviewDetails } from '@/types/admin';

const props = defineProps<{
    productId: number;
    idPrefix: string;
    history?: boolean;
}>();
const status = defineModel<string>('status', { required: true });
const note = defineModel<string>('note', { required: true });
const source = defineModel<string>('source', { required: true });
const emit = defineEmits<{ ready: [ready: boolean] }>();
const details = ref<ProductImageReviewDetails | null>(null);
const loading = ref(false);
const failed = ref(false);
const imageFailed = ref(false);
const http = useHttp({});
let sequence = 0;
onBeforeUnmount(() => {
    sequence++;
});
function imageLoadFailed(): void {
    imageFailed.value = true;

    if (status.value === 'matches') {
        status.value = '';
    }
}

async function load(page = 1): Promise<void> {
    const current = ++sequence;
    loading.value = true;
    failed.value = false;
    emit('ready', false);

    try {
        await http.get(show.url(props.productId, { query: { page } }), {
            onSuccess: (response: unknown) => {
                if (current !== sequence) {
                    return;
                }

                const body = response as ProductImageReviewDetails;

                if (!body.id) {
                    return;
                }

                details.value = body;
                source.value = body.image_source;
                emit('ready', true);
            },
        });
    } catch {
        if (current === sequence) {
            failed.value = true;
        }
    } finally {
        if (current === sequence) {
            loading.value = false;
        }
    }
}

watch(
    () => props.productId,
    () => {
        status.value = '';
        note.value = '';
        source.value = '';
        details.value = null;
        imageFailed.value = false;
        void load();
    },
    { immediate: true },
);
</script>

<template>
    <div class="space-y-3">
        <p
            v-if="loading"
            class="flex items-center gap-2 text-sm text-gray-600 dark:text-neutral-300"
            role="status"
        >
            <LoaderCircle class="h-4 w-4 animate-spin" />{{
                t('imageReview.loading')
            }}
        </p>
        <ActionErrorBanner
            v-if="failed"
            :message="t('imageReview.loadError')"
        />
        <button
            v-if="failed"
            type="button"
            :class="filterTriggerButtonClass"
            @click="load()"
        >
            {{ t('imageReview.retry') }}
        </button>
        <template v-if="details">
            <p class="font-medium text-gray-900 dark:text-neutral-100">
                {{ productName(details.name, details.ar_name) }}
            </p>
            <p class="text-sm text-gray-600 dark:text-neutral-300">
                {{ t('imageReview.productCode') }}: {{ details.id
                }}<span v-for="barcode in details.barcodes" :key="barcode">
                    · {{ barcode }}</span
                >
            </p>
            <img
                v-if="details.image_url && !imageFailed"
                :src="details.image_url"
                :alt="productName(details.name, details.ar_name)"
                class="h-48 w-full rounded object-contain"
                @error="imageLoadFailed"
            />
            <p
                v-else
                class="flex items-center gap-2 text-sm text-amber-800 dark:text-amber-300"
            >
                <TriangleAlert class="h-4 w-4" />{{
                    t(
                        details.image_url
                            ? 'imageReview.imageLoadError'
                            : 'imageReview.noImage',
                    )
                }}
            </p>
            <p class="text-sm text-gray-600 dark:text-neutral-300">
                {{ t('imageReview.instructions') }}
            </p>
            <label :for="`${idPrefix}-status`" :class="fieldLabelClass"
                >{{ t('imageReview.title') }}<RequiredMark
            /></label>
            <select
                :id="`${idPrefix}-status`"
                v-model="status"
                required
                :class="plainFieldInputClass"
            >
                <option value="" disabled>{{ t('imageReview.choose') }}</option>
                <template v-if="details.image_url">
                    <option value="matches" :disabled="imageFailed">
                        {{ t('imageReview.matches') }}
                    </option>
                    <option value="doesnt_match">
                        {{ t('imageReview.doesnt_match') }}
                    </option>
                </template>
                <option v-else value="image_missing">
                    {{ t('imageReview.image_missing') }}
                </option>
            </select>
            <label :for="`${idPrefix}-note`" :class="fieldLabelClass">{{
                t('imageReview.note')
            }}</label>
            <textarea
                :id="`${idPrefix}-note`"
                v-model="note"
                rows="3"
                maxlength="1000"
                :class="plainFieldInputClass"
            />
            <div v-if="history" class="space-y-3">
                <h3 class="font-medium text-gray-900 dark:text-neutral-100">
                    {{ t('imageReview.history') }}
                </h3>
                <p
                    v-if="!details.reviews.data.length"
                    class="text-sm text-gray-600 dark:text-neutral-300"
                >
                    {{ t('imageReview.noHistory') }}
                </p>
                <article
                    v-for="review in details.reviews.data"
                    :key="review.id"
                    class="space-y-1 rounded border border-gray-200 p-3 text-sm dark:border-neutral-700 dark:text-neutral-200"
                >
                    <p class="flex items-center gap-2">
                        <Check
                            v-if="review.status === 'matches'"
                            class="h-4 w-4"
                        /><TriangleAlert v-else class="h-4 w-4" />{{
                            t(`imageReview.${review.status}`)
                        }}
                    </p>
                    <p>
                        <TableLink :href="showUser(review.user)">{{
                            review.user.name
                        }}</TableLink>
                        · {{ formatDateTime(review.created_at) }}
                    </p>
                    <p v-if="review.note" class="whitespace-pre-wrap">
                        {{ review.note }}
                    </p>
                    <img
                        v-if="review.image_url"
                        :src="review.image_url"
                        :alt="t('imageReview.reviewedImage')"
                        class="h-16 w-16 rounded object-contain"
                    />
                </article>
                <div class="flex gap-2">
                    <button
                        v-if="details.reviews.current_page > 1"
                        type="button"
                        :class="filterTriggerButtonClass"
                        @click="load(details.reviews.current_page - 1)"
                    >
                        {{ t('imageReview.previous') }}
                    </button>
                    <button
                        v-if="
                            details.reviews.current_page <
                            details.reviews.last_page
                        "
                        type="button"
                        :class="filterTriggerButtonClass"
                        @click="load(details.reviews.current_page + 1)"
                    >
                        {{ t('imageReview.next') }}
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>

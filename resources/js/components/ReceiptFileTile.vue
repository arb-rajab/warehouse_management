<script setup lang="ts">
import { FileText, ImageOff } from '@lucide/vue';
import { computed, ref } from 'vue';
import { currentLocaleTag } from '@/lib/date';
import { t } from '@/lib/i18n';
import type { ReceiptFile } from '@/types/admin';

/**
 * One bill photo or PDF of a receipt. A photo is a thumbnail that opens the
 * full image in a new tab; a PDF is an icon tile with an Open link. Both read
 * the file through its authenticated download `url` (the session cookie of the
 * admin), never a storage path. A photo that fails to load turns into a
 * message that still links to the file.
 */
const props = defineProps<{
    file: ReceiptFile;
}>();

const failed = ref(false);

const isImage = computed(() => props.file.mime_type.startsWith('image/'));

const sizeLabel = computed(() => {
    const kilobytes = props.file.size / 1024;
    const tag = currentLocaleTag();

    return kilobytes >= 1024
        ? t('receipts.show.sizeMb', {
              size: (kilobytes / 1024).toLocaleString(tag, {
                  maximumFractionDigits: 1,
              }),
          })
        : t('receipts.show.sizeKb', {
              size: kilobytes.toLocaleString(tag, { maximumFractionDigits: 0 }),
          });
});
</script>

<template>
    <li
        class="flex min-w-0 flex-col gap-2 rounded-lg border border-line bg-surface p-3"
    >
        <a
            v-if="isImage && !failed"
            :href="file.url"
            target="_blank"
            rel="noopener"
            :aria-label="
                t('receipts.show.openFile', { name: file.original_name })
            "
            class="block"
        >
            <img
                :src="file.url"
                :alt="file.original_name"
                loading="lazy"
                class="h-40 w-full rounded-md bg-subtle object-cover"
                @error="failed = true"
            />
        </a>
        <div
            v-else
            class="flex h-40 flex-col items-center justify-center gap-2 rounded-md bg-subtle p-3 text-center text-sm text-muted"
        >
            <ImageOff v-if="isImage" class="h-8 w-8 shrink-0" />
            <FileText v-else class="h-8 w-8 shrink-0" />
            <span v-if="isImage">{{ t('receipts.show.imageFailed') }}</span>
        </div>
        <div class="min-w-0 text-sm">
            <p
                dir="auto"
                class="truncate font-medium text-ink"
                :title="file.original_name"
            >
                {{ file.original_name }}
            </p>
            <p class="text-xs text-muted">{{ sizeLabel }}</p>
        </div>
        <a
            :href="file.url"
            target="_blank"
            rel="noopener"
            :aria-label="
                t('receipts.show.openFile', { name: file.original_name })
            "
            class="text-sm text-link hover:underline"
        >
            {{ t('receipts.show.open') }}
        </a>
    </li>
</template>

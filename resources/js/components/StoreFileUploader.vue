<script setup lang="ts">
import type { HttpProgressEvent } from '@inertiajs/core';
import { useHttp } from '@inertiajs/vue3';
import { Check, CircleAlert, LoaderCircle, Upload } from '@lucide/vue';
import { reactive } from 'vue';
import { store as storeUpload } from '@/actions/App/Http/Controllers/Store/Admin/UploadController';
import { t } from '@/lib/i18n';
import type { StoreUpload } from '@/types/store';

/**
 * The upload half of the store's file manager (the upload page and the
 * dialog's upload tab), replacing the Blade screens' Uppy dashboard: pick one
 * or more files and each is posted on its own, one after another, to
 * `uploaded-files.store`, with a progress bar. The server decides what a file
 * may be from its content, so a refused file shows the server's message.
 */
defineProps<{
    /** The `accept` of the file input, e.g. `image/*` for an image field. */
    accept?: string;
}>();

const emit = defineEmits<{
    uploaded: [upload: StoreUpload];
}>();

/** StoreUploadRequest::MAX_KILOBYTES, in bytes. */
const MAX_BYTES = 512 * 1024 * 1024;

interface QueuedFile {
    key: number;
    name: string;
    status: 'waiting' | 'uploading' | 'done' | 'failed';
    progress: number;
    error: string | null;
}

const queue = reactive<QueuedFile[]>([]);
const pendingFiles = new Map<number, File>();
let nextKey = 0;

const http = useHttp<{ file: File | null }, StoreUpload>({
    file: null,
});

function onChoose(event: Event): void {
    const input = event.target as HTMLInputElement;

    for (const file of Array.from(input.files ?? [])) {
        const item: QueuedFile = {
            key: nextKey++,
            name: file.name,
            status: 'waiting',
            progress: 0,
            error: null,
        };

        if (file.size > MAX_BYTES) {
            item.status = 'failed';
            item.error = t('store.uploads.uploader.tooLarge');
        } else {
            pendingFiles.set(item.key, file);
        }

        queue.push(item);
    }

    input.value = '';
    uploadNext();
}

function percentage(event: HttpProgressEvent): number {
    if (event.percentage !== undefined) {
        return event.percentage;
    }

    return event.total ? Math.round((event.loaded / event.total) * 100) : 0;
}

function uploadNext(): void {
    if (http.processing) {
        return;
    }

    const item = queue.find((queued) => queued.status === 'waiting');

    if (!item) {
        return;
    }

    const fail = (message: string): void => {
        item.status = 'failed';
        item.error = message;
    };

    item.status = 'uploading';
    http.file = pendingFiles.get(item.key) ?? null;
    http.post(storeUpload.url(), {
        onProgress: (event) => {
            item.progress = percentage(event);
        },
        onSuccess: (response) => {
            item.status = 'done';
            item.progress = 100;
            emit('uploaded', response);
        },
        onError: (errors) => {
            fail(errors.file ?? t('store.uploads.uploader.failed'));
        },
        onHttpException: () => {
            fail(t('store.uploads.uploader.failed'));
        },
        onNetworkError: () => {
            fail(t('store.uploads.uploader.failed'));
        },
        onFinish: () => {
            pendingFiles.delete(item.key);
            uploadNext();
        },
    }).catch(() => undefined);
}
</script>

<template>
    <div class="space-y-4">
        <label
            class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-300 p-8 text-center text-sm text-gray-600 hover:bg-gray-50 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
        >
            <Upload class="h-6 w-6" />
            <span class="font-medium">{{
                t('store.uploads.uploader.choose')
            }}</span>
            <span class="text-xs text-gray-500 dark:text-neutral-400">
                {{ t('store.uploads.uploader.hint') }}
            </span>
            <input
                type="file"
                multiple
                class="sr-only"
                :accept="accept"
                @change="onChoose"
            />
        </label>

        <ul v-if="queue.length > 0" class="space-y-2">
            <li
                v-for="item in queue"
                :key="item.key"
                class="rounded-md border border-gray-200 p-3 text-sm dark:border-neutral-800"
            >
                <div class="flex items-center gap-2">
                    <LoaderCircle
                        v-if="item.status === 'uploading'"
                        class="h-4 w-4 shrink-0 animate-spin"
                    />
                    <Check
                        v-else-if="item.status === 'done'"
                        class="h-4 w-4 shrink-0 text-green-600 dark:text-green-400"
                    />
                    <CircleAlert
                        v-else-if="item.status === 'failed'"
                        class="h-4 w-4 shrink-0 text-red-600 dark:text-red-400"
                    />
                    <span class="min-w-0 flex-1 truncate">{{ item.name }}</span>
                    <span class="text-xs text-gray-500 dark:text-neutral-400">
                        {{ t(`store.uploads.uploader.status.${item.status}`) }}
                    </span>
                </div>
                <progress
                    v-if="item.status === 'uploading'"
                    class="mt-2 h-1.5 w-full overflow-hidden rounded-full accent-blue-600"
                    max="100"
                    :value="item.progress"
                    :aria-label="item.name"
                ></progress>
                <p
                    v-if="item.error"
                    class="mt-1 text-xs text-red-600 dark:text-red-400"
                >
                    {{ item.error }}
                </p>
            </li>
        </ul>
    </div>
</template>

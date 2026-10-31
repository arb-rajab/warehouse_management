<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { Check, Eye, File, LoaderCircle, Plus, Trash2 } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import {
    destroy,
    bulkDestroy,
    files as uploadFiles,
    store as storeUpload,
} from '@/actions/App/Http/Controllers/Store/Admin/UploadController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import FilterCheckbox from '@/components/FilterCheckbox.vue';
import FormField from '@/components/FormField.vue';
import Pagination from '@/components/Pagination.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { confirmDelete } from '@/lib/confirm';
import {
    fieldLabelClass,
    plainFieldInputClass,
    filterApplyButtonClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import { compressUploadImage, ImageUploadError } from '@/lib/imageUpload';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated } from '@/types/admin';
import type { StoreUpload } from '@/types/store';

const props = withDefaults(
    defineProps<{
        library?: boolean;
        multiple?: boolean;
        type?: string;
        initialTab?: 'library' | 'upload';
    }>(),
    { library: false, multiple: false, initialTab: 'library' },
);
const selected = defineModel<StoreUpload[]>('selected', { default: () => [] });
const emit = defineEmits<{ uploaded: [file: StoreUpload] }>();
const access = useStoreAccess();
const tab = ref(props.initialTab);
const filter = useHttp<
    {
        search: string;
        sort: string;
        type: string;
        per_page: number;
        page: number;
    },
    Paginated<StoreUpload>
>({
    search: '',
    sort: 'newest',
    type: props.type ?? '',
    per_page: 20,
    page: 1,
});
const upload = useHttp<{ file: File | null }, StoreUpload>({ file: null });
const deletion = useHttp<{ id: number[] }, { deleted: number | number[] }>({
    id: [],
});
const error = ref<string>();
const info = ref<StoreUpload>();
const queue = ref<File[]>([]);
const fileInput = ref<HTMLInputElement>();
let active = true;
const sending = ref(false);
const preparing = ref(false);
const compressionController = new AbortController();

async function load(page = 1): Promise<void> {
    if (!access.value.can('view_uploads')) {
        return;
    }

    filter.cancel();
    filter.page = page;
    error.value = undefined;

    try {
        await filter.get(uploadFiles.url());
    } catch {
        if (active) {
            error.value = t('store.uploads.loadFailed');
        }
    }
}
function navigate(url: string): void {
    void load(
        Number(
            new URL(url, window.location.origin).searchParams.get('page') ?? 1,
        ),
    );
}
function choose(file: StoreUpload): void {
    if (props.library) {
        info.value = file;

        return;
    }

    toggle(file);
}
function toggle(file: StoreUpload): void {
    if (selected.value.some((item) => item.id === file.id)) {
        selected.value = selected.value.filter((item) => item.id !== file.id);
    } else {
        selected.value =
            props.multiple || props.library
                ? [...selected.value, file].slice(0, 100)
                : [file];
    }
}
function pickFiles(event: Event): void {
    const input = event.target as HTMLInputElement;
    queue.value = Array.from(input.files ?? []);
    input.setCustomValidity(
        queue.value.some((file) => file.size > 512 * 1024 * 1024)
            ? t('store.uploads.tooLarge')
            : '',
    );
}
async function send(): Promise<void> {
    if (sending.value || upload.processing || !active) {
        return;
    }

    sending.value = true;

    try {
        error.value = undefined;

        while (queue.value.length > 0) {
            const file = queue.value[0];

            try {
                preparing.value = true;
                upload.file = await compressUploadImage(
                    file,
                    1500,
                    compressionController.signal,
                );
                preparing.value = false;

                if (!active) {
                    return;
                }

                const stored = await upload.post(storeUpload.url());

                if (!stored) {
                    error.value =
                        upload.errors.file ?? t('store.uploads.uploadFailed');

                    return;
                }

                if (!active) {
                    return;
                }

                emit('uploaded', stored);
                queue.value.shift();

                if (
                    !props.library &&
                    (!props.type || stored.type === props.type)
                ) {
                    selected.value = props.multiple
                        ? [...selected.value, stored].slice(0, 100)
                        : [stored];
                }
            } catch (failure) {
                if (!active) {
                    return;
                }

                error.value =
                    failure instanceof ImageUploadError
                        ? t(`common.imageUpload.${failure.code}`)
                        : (upload.errors.file ??
                          t('store.uploads.uploadFailed'));

                return;
            }
        }

        queue.value = [];
        upload.file = null;

        if (fileInput.value) {
            fileInput.value.value = '';
        }

        if (access.value.can('view_uploads')) {
            tab.value = 'library';
            await load();
        }
    } finally {
        sending.value = false;
        preparing.value = false;
    }
}

async function remove(file: StoreUpload): Promise<void> {
    if (!confirmDelete(file.name ?? String(file.id))) {
        return;
    }

    error.value = undefined;

    try {
        await deletion.delete(destroy.url(file, { mergeQuery: {} }));
        selected.value = selected.value.filter((item) => item.id !== file.id);

        if (info.value?.id === file.id) {
            info.value = undefined;
        }

        await refreshAfterDelete();
    } catch {
        error.value = t('store.uploads.deleteFailed');
    }
}
async function removeSelected(): Promise<void> {
    if (
        !confirmDelete(
            t('store.uploads.selectedFiles', { count: selected.value.length }),
        )
    ) {
        return;
    }

    error.value = undefined;
    deletion.id = selected.value.map((file) => file.id);

    try {
        const result = await deletion.post(bulkDestroy.url({ mergeQuery: {} }));

        if (!result) {
            error.value = t('store.uploads.deleteFailed');

            return;
        }

        if (info.value && deletion.id.includes(info.value.id)) {
            info.value = undefined;
        }

        selected.value = [];
        await refreshAfterDelete();
    } catch {
        error.value = t('store.uploads.deleteFailed');
    }
}
async function refreshAfterDelete(): Promise<void> {
    await load(filter.page);

    if (filter.response?.data.length === 0 && filter.page > 1) {
        await load(filter.page - 1);
    }
}
onMounted(() => {
    if (tab.value === 'library') {
        void load();
    }
});
onBeforeUnmount(() => {
    compressionController.abort();
    active = false;
    filter.cancel();
    upload.cancel();
    deletion.cancel();
});
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap gap-3">
            <button
                v-if="access.can('view_uploads')"
                type="button"
                :class="filterApplyButtonClass"
                :aria-pressed="tab === 'library'"
                @click="
                    tab = 'library';
                    load();
                "
            >
                {{ t('store.uploads.library') }}
            </button>
            <button
                v-if="access.can('upload_files')"
                type="button"
                :class="filterApplyButtonClass"
                :aria-pressed="tab === 'upload'"
                @click="tab = 'upload'"
            >
                <Plus class="h-4 w-4" />{{ t('store.uploads.upload') }}
            </button>
        </div>
        <ActionErrorBanner :message="error" />
        <div
            v-if="tab === 'library' && access.can('view_uploads')"
            class="space-y-4"
        >
            <form
                class="flex flex-wrap items-end gap-3"
                @submit.prevent="load()"
            >
                <FormField
                    id="upload-search"
                    :label="t('store.uploads.search')"
                    maxlength="255"
                    :value="filter.search"
                    :error="filter.errors.search"
                    @input="
                        filter.search = (
                            $event.target as HTMLInputElement
                        ).value
                    "
                />
                <label :class="fieldLabelClass"
                    >{{ t('store.uploads.sort') }}
                    <select v-model="filter.sort" :class="plainFieldInputClass">
                        <option
                            v-for="sort in [
                                'newest',
                                'oldest',
                                'smallest',
                                'largest',
                            ]"
                            :key="sort"
                            :value="sort"
                        >
                            {{ t(`store.uploads.${sort}`) }}
                        </option>
                    </select>
                </label>
                <label :class="fieldLabelClass"
                    >{{ t('store.uploads.type') }}
                    <select
                        v-model="filter.type"
                        :class="plainFieldInputClass"
                        :disabled="!!type"
                    >
                        <option value="">
                            {{ t('store.uploads.allTypes') }}
                        </option>
                        <option
                            v-for="kind in [
                                'image',
                                'video',
                                'audio',
                                'archive',
                                'document',
                            ]"
                            :key="kind"
                            :value="kind"
                        >
                            {{ t(`store.uploads.${kind}`) }}
                        </option>
                    </select>
                </label>
                <SubmitButton
                    :label="t('common.filters.apply')"
                    :processing-label="t('store.uploads.loading')"
                    :processing="filter.processing"
                />
            </form>
            <button
                v-if="
                    library &&
                    access.can('delete_uploads') &&
                    selected.length > 0
                "
                type="button"
                :class="filterApplyButtonClass"
                :disabled="deletion.processing"
                @click="removeSelected"
            >
                <Trash2 class="h-4 w-4" />{{
                    t('store.uploads.deleteSelected')
                }}
            </button>
            <p
                v-if="filter.processing"
                role="status"
                class="flex items-center gap-2"
            >
                <LoaderCircle class="h-4 w-4 animate-spin" />{{
                    t('store.uploads.loading')
                }}
            </p>
            <p
                v-else-if="filter.response?.data.length === 0"
                class="flex items-center gap-2"
            >
                <File class="h-4 w-4" />{{ t('store.uploads.empty') }}
            </p>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                <div
                    v-for="file in filter.response?.data ?? []"
                    :key="file.id"
                    class="rounded-md border border-gray-300 p-3 dark:border-neutral-700"
                >
                    <FilterCheckbox
                        v-if="library && access.can('delete_uploads')"
                        :id="`select-upload-${file.id}`"
                        :label="
                            t('store.uploads.selectFile', {
                                name: file.name ?? String(file.id),
                            })
                        "
                        :model-value="
                            selected.some((item) => item.id === file.id)
                        "
                        @update:model-value="toggle(file)"
                    />
                    <button
                        type="button"
                        :aria-label="file.name ?? String(file.id)"
                        class="flex w-full cursor-pointer flex-col items-center gap-2"
                        :aria-pressed="
                            !library &&
                            selected.some((item) => item.id === file.id)
                        "
                        @click="choose(file)"
                    >
                        <img
                            v-if="file.type === 'image' && file.url"
                            :src="file.url"
                            :alt="file.name ?? ''"
                            class="h-24 w-full object-contain"
                        />
                        <File
                            v-else
                            class="h-24 w-10 text-gray-500 dark:text-neutral-400"
                        />
                        <span class="w-full truncate">{{ file.name }}</span>
                        <Check
                            v-if="
                                !library &&
                                selected.some((item) => item.id === file.id)
                            "
                            class="h-4 w-4"
                        />
                        <Eye v-if="library" class="h-4 w-4" />
                    </button>
                    <button
                        v-if="library && access.can('delete_uploads')"
                        type="button"
                        class="mt-2 flex cursor-pointer items-center gap-1 text-red-700 disabled:cursor-not-allowed disabled:opacity-50 dark:text-red-400"
                        :disabled="deletion.processing"
                        @click="remove(file)"
                    >
                        <Trash2 class="h-4 w-4" />{{
                            t('store.uploads.delete')
                        }}
                    </button>
                </div>
            </div>
            <Pagination
                v-if="filter.response"
                :links="filter.response.meta.links"
                :per-page="filter.per_page"
                async
                @navigate="navigate"
                @update:per-page="
                    filter.per_page = $event;
                    load();
                "
            />
            <aside
                v-if="info"
                class="space-y-2 rounded-md border border-gray-300 p-4 dark:border-neutral-700"
                :aria-label="t('store.uploads.info')"
            >
                <h2 class="font-semibold">{{ t('store.uploads.info') }}</h2>
                <p>{{ info.name }}</p>
                <p>{{ t('store.uploads.type') }}: {{ info.type }}</p>
                <p>{{ t('store.uploads.size') }}: {{ info.size }}</p>
                <a
                    v-if="info.url"
                    :href="info.url"
                    target="_blank"
                    rel="noopener"
                    >{{ t('store.uploads.openFile') }}</a
                >
            </aside>
        </div>
        <form
            v-if="tab === 'upload' && access.can('upload_files')"
            class="space-y-3"
            @submit.prevent="send"
        >
            <label :class="fieldLabelClass" for="upload-files">{{
                t('store.uploads.files')
            }}</label>
            <input
                id="upload-files"
                ref="fileInput"
                type="file"
                multiple
                required
                :disabled="sending || upload.processing"
                :accept="type === 'image' ? 'image/*' : undefined"
                :class="plainFieldInputClass"
                @change="pickFiles"
            />
            <p class="text-sm text-gray-600 dark:text-neutral-400">
                {{ t('store.uploads.limits') }}
            </p>
            <progress
                v-if="upload.progress"
                :value="upload.progress.percentage"
                max="100"
                :aria-label="t('store.uploads.progress')"
            >
                {{ upload.progress.percentage }}%
            </progress>
            <SubmitButton
                :label="t('store.uploads.upload')"
                :processing-label="
                    preparing
                        ? t('common.imageUpload.preparing')
                        : t('store.uploads.uploading')
                "
                :processing="sending || upload.processing"
            />
        </form>
    </div>
</template>

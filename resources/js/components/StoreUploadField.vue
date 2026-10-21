<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { File, Plus, X } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import { previews } from '@/actions/App/Http/Controllers/Store/Admin/UploadController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import FieldError from '@/components/FieldError.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import StoreFileManagerDialog from '@/components/StoreFileManagerDialog.vue';
import { fieldLabelClass, filterTriggerButtonClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { StoreUpload } from '@/types/store';

const props = withDefaults(
    defineProps<{
        name: string;
        id?: string;
        required?: boolean;
        label: string;
        multiple?: boolean;
        type?: string;
        initialFiles?: StoreUpload[];
        error?: string;
    }>(),
    { multiple: false, required: false, initialFiles: () => [] },
);
const value = defineModel<string>({ default: '' });
const access = useStoreAccess();
const files = ref<StoreUpload[]>(props.initialFiles);
const draft = ref<StoreUpload[]>([]);
const open = ref(false);
const failure = ref<string>();
const preview = useHttp<{ ids: string }, StoreUpload[]>({ ids: '' });
let revision = 0;
watch(
    value,
    async (ids) => {
        const current = ++revision;
        preview.cancel();

        if (!ids) {
            files.value = [];

            return;
        }

        if (
            files.value.map((file) => file.id).join(',') === ids ||
            !access.value.can('view_uploads')
        ) {
            return;
        }

        preview.ids = ids;
        failure.value = undefined;

        try {
            const result = await preview.get(previews.url());

            if (revision === current) {
                if (result) {
                    files.value = result;
                } else {
                    failure.value = t('store.uploads.loadFailed');
                }
            }
        } catch {
            if (revision === current) {
                failure.value = t('store.uploads.loadFailed');
            }
        }
    },
    { immediate: true },
);
function choose(chosen: StoreUpload[]): void {
    files.value = chosen;
    value.value = chosen.map((file) => file.id).join(',');
}
function remove(id: number): void {
    choose(files.value.filter((file) => file.id !== id));
}
onBeforeUnmount(() => {
    revision++;
    preview.cancel();
});
</script>
<template>
    <div class="space-y-2">
        <label :for="id ?? name" :class="fieldLabelClass"
            >{{ label }}<RequiredMark v-if="required"
        /></label>
        <input
            :id="id ?? name"
            type="text"
            class="sr-only"
            tabindex="-1"
            :aria-label="label"
            :required="required"
            :value="value"
            @invalid="open = access.can('view_uploads')"
        />
        <input type="hidden" :name="name" :value="value" />
        <div class="flex flex-wrap gap-3">
            <div
                v-for="file in files"
                :key="file.id"
                class="rounded-md border border-gray-300 p-2 dark:border-neutral-700"
            >
                <img
                    v-if="file.type === 'image' && file.url"
                    :src="file.url"
                    :alt="file.name ?? ''"
                    class="h-20 w-20 object-contain"
                /><File v-else class="h-8 w-8" />
                <p>{{ file.name }}</p>
                <button
                    type="button"
                    class="flex cursor-pointer items-center gap-1"
                    :aria-label="t('store.uploads.remove', { name: file.name })"
                    @click="remove(file.id)"
                >
                    <X class="h-4 w-4" />{{
                        t('store.uploads.remove', { name: file.name })
                    }}
                </button>
            </div>
        </div>
        <button
            v-if="access.can('view_uploads')"
            type="button"
            :class="filterTriggerButtonClass"
            @click="
                draft = [...files];
                open = true;
            "
        >
            <Plus class="h-4 w-4" />{{ t('store.uploads.choose') }}
        </button>
        <ActionErrorBanner :message="failure" /><FieldError :message="error" />
        <StoreFileManagerDialog
            v-model:open="open"
            v-model:selected="draft"
            :multiple="multiple"
            :type="type"
            @choose="choose"
        />
    </div>
</template>

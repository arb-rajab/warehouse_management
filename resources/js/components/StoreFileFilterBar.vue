<script setup lang="ts">
import { Search } from '@lucide/vue';
import { filterApplyButtonClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { STORE_UPLOAD_SORTS } from '@/lib/storeUploads';
import type { StoreUploadSort } from '@/types/store';

/**
 * The search box and sort menu over the uploads library page and the file
 * manager dialog (FilterUploadsRequest's `search` and `sort`). Each emits
 * `submit` for its owner to reload: on Search, and as soon as the sort
 * changes, as the Blade library's sort menu did.
 */
defineProps<{
    idPrefix: string;
}>();

const search = defineModel<string>('search', { required: true });
const sort = defineModel<StoreUploadSort>('sort', { required: true });

const emit = defineEmits<{
    submit: [];
}>();
</script>

<template>
    <form
        role="search"
        class="flex flex-wrap items-end gap-2"
        @submit.prevent="emit('submit')"
    >
        <div class="min-w-48 flex-1">
            <label :for="`${idPrefix}-search`" class="sr-only">
                {{ t('store.uploads.filters.search') }}
            </label>
            <input
                :id="`${idPrefix}-search`"
                v-model="search"
                type="search"
                maxlength="255"
                :placeholder="t('store.uploads.filters.searchPlaceholder')"
                :class="plainFieldInputClass"
            />
        </div>
        <div>
            <label :for="`${idPrefix}-sort`" class="sr-only">
                {{ t('store.uploads.filters.sort') }}
            </label>
            <select
                :id="`${idPrefix}-sort`"
                v-model="sort"
                :class="plainFieldInputClass"
                @change="emit('submit')"
            >
                <option
                    v-for="option in STORE_UPLOAD_SORTS"
                    :key="option"
                    :value="option"
                >
                    {{ t(`store.uploads.sort.${option}`) }}
                </option>
            </select>
        </div>
        <button type="submit" :class="filterApplyButtonClass">
            <Search class="h-4 w-4 shrink-0" />
            {{ t('store.uploads.filters.submit') }}
        </button>
    </form>
</template>

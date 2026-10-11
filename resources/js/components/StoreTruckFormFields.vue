<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { LoaderCircle, Search } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { photos as searchPhotos } from '@/actions/App/Http/Controllers/Store/Admin/TruckController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import {
    fieldLabelClass,
    filterTriggerButtonClass,
    plainFieldInputClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreTruck, TruckPhoto } from '@/types/storeTruck';

const props = defineProps<{
    truck?: StoreTruck;
    photos: TruckPhoto[];
    errors: Record<string, string>;
}>();

const selected = ref([...(props.truck?.photos ?? [])]);
const known = reactive(new Map(props.photos.map((photo) => [photo.id, photo])));
const results = ref(props.photos);
const query = ref('');
const currentPage = ref(1);
const hasMore = ref(false);
const http = useHttp({});
const failed = ref(false);

const visiblePhotos = computed(() => {
    const ids = [
        ...new Set([
            ...selected.value,
            ...results.value.map((photo) => photo.id),
        ]),
    ];

    return ids
        .map((id) => known.get(id))
        .filter((photo): photo is TruckPhoto => photo !== undefined);
});

function reportSearchFailure(): false {
    failed.value = true;

    return false;
}

function loadPhotos(page = 1): void {
    failed.value = false;
    http.get(searchPhotos.url({ query: { search: query.value, page } }), {
        onSuccess: (response: unknown) => {
            const body = response as { data: TruckPhoto[]; has_more: boolean };

            for (const photo of body.data) {
                known.set(photo.id, photo);
            }

            results.value =
                page === 1 ? body.data : [...results.value, ...body.data];
            currentPage.value = page;
            hasMore.value = body.has_more;
        },
        onError: reportSearchFailure,
        onHttpException: reportSearchFailure,
        onNetworkError: reportSearchFailure,
    });
}
</script>

<template>
    <div class="space-y-4">
        <FormField
            id="name"
            :label="t('store.trucks.fields.name')"
            :value="truck?.name ?? ''"
            :error="errors.name"
            maxlength="50"
            required
        />
        <FormField
            id="model"
            :label="t('store.trucks.fields.model')"
            :value="truck?.model ?? ''"
            :error="errors.model"
            maxlength="50"
        />
        <FormField
            id="max_pallets_number"
            :label="t('store.trucks.fields.capacity')"
            :value="truck?.max_pallets_number ?? ''"
            :error="errors.max_pallets_number"
            maxlength="50"
        />
        <FormField
            id="license_plate"
            :label="t('store.trucks.fields.plate')"
            :value="truck?.license_plate ?? ''"
            :error="errors.license_plate"
            maxlength="50"
        />
        <div>
            <label for="description" :class="fieldLabelClass">{{
                t('store.trucks.fields.description')
            }}</label>
            <textarea
                id="description"
                name="description"
                :default-value.camel="truck?.description ?? ''"
                maxlength="255"
                :class="plainFieldInputClass"
            />
            <FieldError :message="errors.description" />
        </div>
        <fieldset class="space-y-3">
            <legend :class="fieldLabelClass">
                {{ t('store.trucks.fields.photos') }}
            </legend>
            <p class="text-sm text-gray-600 dark:text-neutral-300">
                {{ t('store.trucks.fields.photosHint') }}
            </p>
            <FormField
                id="photo_search"
                :label="t('store.trucks.fields.photoSearch')"
                :value="query"
                maxlength="255"
                @keydown.enter.prevent="loadPhotos()"
                @input="query = ($event.target as HTMLInputElement).value"
            />
            <button
                type="button"
                :class="filterTriggerButtonClass"
                :disabled="http.processing"
                @click="loadPhotos()"
            >
                <LoaderCircle
                    v-if="http.processing"
                    class="h-4 w-4 animate-spin"
                />
                <Search v-else class="h-4 w-4" />
                {{ t('store.trucks.fields.searchPhotos') }}
            </button>
            <ActionErrorBanner
                v-if="failed"
                :message="t('store.trucks.fields.photosFailed')"
            />
            <input
                v-for="id in selected"
                :key="id"
                type="hidden"
                name="photos[]"
                :value="id"
            />
            <label
                v-for="photo in visiblePhotos"
                :key="photo.id"
                class="flex cursor-pointer items-center gap-3 text-sm"
            >
                <input v-model="selected" type="checkbox" :value="photo.id" />
                <img
                    v-if="photo.url"
                    :src="photo.url"
                    alt=""
                    class="h-12 w-12 rounded object-cover"
                />
                <span>{{ photo.name }}</span>
            </label>
            <button
                v-if="hasMore"
                type="button"
                :class="filterTriggerButtonClass"
                :disabled="http.processing"
                @click="loadPhotos(currentPage + 1)"
            >
                {{ t('store.trucks.fields.morePhotos') }}
            </button>
            <FieldError
                :message="
                    errors.photos ??
                    Object.entries(errors).find(([key]) =>
                        key.startsWith('photos.'),
                    )?.[1]
                "
            />
        </fieldset>
    </div>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    exportAppTranslations,
    show,
    showAppTranslations,
    storeTranslations,
    storeAppTranslations,
} from '@/actions/App/Http/Controllers/Store/Admin/LanguageController';
import DataTable from '@/components/DataTable.vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import Pagination from '@/components/Pagination.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { filterApplyButtonClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated } from '@/types/admin';
import type { AdminLanguage, StoreTranslation } from '@/types/store';
const props = defineProps<{
    language: AdminLanguage;
    app: boolean;
    translations: Paginated<StoreTranslation>;
    filters: { search: string | null; per_page: number };
}>();
const access = useStoreAccess();
const search = ref(props.filters.search ?? '');
const fields = ref<HTMLElement | null>(null);
function filter(per_page = props.filters.per_page): void {
    router.get(
        (props.app ? showAppTranslations : show)(props.language).url,
        { search: search.value, per_page },
        { preserveState: false, replace: true },
    );
}
function copy(): void {
    fields.value
        ?.querySelectorAll<HTMLInputElement>('input[data-translation-index]')
        .forEach((input) => {
            input.value =
                props.translations.data[
                    Number(input.dataset.translationIndex)
                ].source;
        });
}
</script>
<template>
    <ResourceFormPage
        :title="
            t(
                app
                    ? 'store.languages.appTranslations'
                    : 'store.languages.translations',
            )
        "
        :action="
            (app ? storeAppTranslations : storeTranslations)({ mergeQuery: {} })
        "
        :submit-label="t('store.settings.save')"
        :submitting-label="t('store.settings.saving')"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <FieldError :message="errors.translations ?? errors.id" />
        <p>
            {{ language.name }} ({{
                app ? language.app_lang_code : language.code
            }})
        </p>
        <p>{{ t('store.languages.translationScope') }}</p>
        <p>
            {{
                t(
                    app
                        ? 'store.languages.mobileScope'
                        : 'store.languages.contentScope',
                )
            }}
        </p>
        <input type="hidden" name="id" :value="language.id" />
        <div class="flex flex-wrap items-end gap-3">
            <FormField
                id="search"
                :value="search"
                @input="search = ($event.target as HTMLInputElement).value"
                :label="t('store.languages.search')"
                maxlength="255"
            />
            <button
                type="button"
                :class="filterApplyButtonClass"
                @click="filter()"
            >
                {{ t('store.languages.search') }}
            </button>
            <button
                v-if="access.can('language_setup')"
                type="button"
                :class="filterApplyButtonClass"
                @click="copy()"
            >
                {{ t('store.languages.copy') }}
            </button>
            <a
                v-if="app && access.can('language_setup')"
                :href="exportAppTranslations.url(language)"
                :class="filterApplyButtonClass"
                >{{ t('store.languages.export') }}</a
            >
        </div>
        <div ref="fields">
            <DataTable
                :columns="[
                    t('store.languages.key'),
                    t('store.languages.source'),
                    t('store.languages.value'),
                ]"
                :rows="translations.data"
                :empty-message="t('store.languages.noTranslations')"
            >
                <template #row="{ row: translation }">
                    <td class="px-4 py-2">{{ translation.key }}</td>
                    <td class="px-4 py-2">{{ translation.source }}</td>
                    <td class="px-4 py-2">
                        <input
                            type="hidden"
                            :name="`translations[${translations.data.indexOf(translation)}][key]`"
                            :value="translation.key"
                        />
                        <FormField
                            :id="`translations[${translations.data.indexOf(translation)}][value]`"
                            :value="translation.value ?? ''"
                            :data-translation-index="
                                translations.data.indexOf(translation)
                            "
                            :label="t('store.languages.value')"
                            :maxlength="app ? 255 : 65535"
                            :readonly="!access.can('language_setup')"
                            :error="
                                errors[
                                    `translations.${translations.data.indexOf(translation)}.value`
                                ]
                            "
                        />
                    </td>
                </template>
            </DataTable>
        </div>
        <Pagination
            :links="translations.meta.links"
            :per-page="filters.per_page"
            @update:per-page="filter"
        />
    </ResourceFormPage>
</template>

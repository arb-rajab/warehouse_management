<script setup lang="ts">
import { update } from '@/actions/App/Http/Controllers/Store/Admin/ProductFieldSourceController';
import FieldError from '@/components/FieldError.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { fieldLabelClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type {
    StoreProductFieldSource,
    StoreProductFieldSourceRow,
} from '@/types/store';

/**
 * Setup > Product data sources: one radio pair per Otajer-synced product
 * field. Each pair shares one `sources[field]` name, so a field always has
 * exactly one owner; the server requires every field with `otajer` or
 * `admin` (UpdateProductFieldSourcesRequest).
 */
defineProps<{ fields: StoreProductFieldSourceRow[] }>();
const sources: StoreProductFieldSource[] = ['otajer', 'admin'];
const helpClass = 'text-xs text-muted';
</script>

<template>
    <ResourceFormPage
        :title="t('store.productFieldSources.title')"
        :action="update()"
        :submit-label="t('store.settings.save')"
        :submitting-label="t('store.settings.saving')"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <p class="text-sm text-muted">
            {{ t('store.productFieldSources.intro') }}
        </p>
        <FieldError :message="errors.sources" />
        <fieldset
            v-for="field in fields"
            :key="field.key"
            :data-field="field.key"
            class="space-y-2 rounded-lg border border-line p-4"
        >
            <legend :class="[fieldLabelClass, 'px-1']">
                {{ t(`store.productFieldSources.fields.${field.key}`)
                }}<RequiredMark />
            </legend>
            <label
                v-for="source in sources"
                :key="source"
                class="flex cursor-pointer items-start gap-2 text-sm"
            >
                <input
                    type="radio"
                    :name="`sources[${field.key}]`"
                    :value="source"
                    :checked="field.source === source"
                    class="mt-0.5 h-4 w-4 cursor-pointer"
                    required
                />
                <span>
                    <span class="block font-medium">{{
                        t(`store.productFieldSources.${source}`)
                    }}</span>
                    <span :class="['block', helpClass]">{{
                        t(`store.productFieldSources.${source}Hint`)
                    }}</span>
                </span>
            </label>
            <p :class="helpClass">
                {{
                    t('store.productFieldSources.default', {
                        source: t(`store.productFieldSources.${field.default}`),
                    })
                }}
            </p>
            <FieldError :message="errors[`sources.${field.key}`]" />
        </fieldset>
    </ResourceFormPage>
</template>

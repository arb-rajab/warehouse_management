<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { computed } from 'vue';
import DetailItem from '@/components/DetailItem.vue';
import DetailList from '@/components/DetailList.vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreBooleanField from '@/components/StoreBooleanField.vue';
import StoreHighlight from '@/components/StoreHighlight.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import type { ValidationSchema } from '@/lib/formValidation';
import { t } from '@/lib/i18n';
import type { StoreIntegrationId } from '@/types/store';

/**
 * A settings page of on/off switches that each print a public ID on the
 * storefront (Analytics, Facebook chat, Facebook comments). The ID saved here
 * wins over the server's `.env` value; an empty field uses `.env`
 * (StoreIntegrationIds, .ai/rules/admin-env-overrides.md). The IDs are public,
 * so they are shown in full. A switch that is on while no ID is in effect gets
 * a warning naming the missing field (setup-checklist-reasons.md).
 *
 * `ids` is keyed by the switch it belongs to, in display order. The ID fields
 * are optional (the `.env` may hold them), so they carry no required mark.
 */
const props = defineProps<{
    section: 'analytics' | 'facebookChat' | 'facebookComments';
    action: string | UrlMethodPair;
    settings: Record<string, boolean>;
    ids: Record<string, StoreIntegrationId>;
    validation: ValidationSchema;
    /** The switch or ID field a setup link sent the admin here to fix. */
    highlight?: string | null;
}>();

function label(entry: StoreIntegrationId): string {
    return t(`store.settings.integrations.fields.${entry.name}.label`);
}

const rows = computed(() =>
    Object.entries(props.ids).map(([toggle, entry]) => ({
        toggle,
        entry,
        warning:
            props.settings[toggle] && !entry.effective.value
                ? t('store.settings.integrations.missing', {
                      switch: t(`store.settings.keys.${toggle}`),
                      field: label(entry),
                  })
                : null,
        effective: t('store.settings.integrations.effective.withSource', {
            value:
                entry.effective.value ??
                t('store.settings.integrations.effective.notSet'),
            source: t(
                `store.settings.integrations.effective.sources.${entry.effective.source}`,
            ),
        }),
    })),
);
</script>

<template>
    <ResourceFormPage
        :title="t(`store.settings.titles.${section}`)"
        :action="action"
        :submit-label="t('store.settings.save')"
        :submitting-label="t('store.settings.saving')"
        :layout="StoreAdminLayout"
        :validation="validation"
        wide
    >
        <template #default="{ errors }">
            <p class="text-sm text-muted">
                {{ t(`store.settings.integrations.intro.${section}`) }}
            </p>
            <FieldError :message="errors.settings" />
            <p
                v-for="row in rows.filter((r) => r.warning)"
                :key="row.toggle"
                role="alert"
                class="text-sm text-danger"
                :data-warning="row.entry.name"
            >
                {{ row.warning }}
            </p>
            <div class="grid gap-4 sm:grid-cols-2">
                <template v-for="row in rows" :key="row.toggle">
                    <StoreBooleanField
                        :name="`settings[${row.toggle}]`"
                        :label="t(`store.settings.keys.${row.toggle}`)"
                        :value="settings[row.toggle]"
                        :highlighted="highlight === row.toggle"
                        :error="errors[`settings.${row.toggle}`]"
                    />
                    <StoreHighlight :active="highlight === row.entry.name">
                        <FormField
                            :id="row.entry.name"
                            :label="label(row.entry)"
                            :value="row.entry.value ?? ''"
                            :error="errors[row.entry.name]"
                            maxlength="40"
                            autocomplete="off"
                            spellcheck="false"
                            dir="ltr"
                            :aria-describedby="`${row.entry.name}_help`"
                        />
                        <p
                            :id="`${row.entry.name}_help`"
                            class="mt-1 text-sm text-muted"
                        >
                            {{
                                t(
                                    `store.settings.integrations.fields.${row.entry.name}.help`,
                                )
                            }}
                        </p>
                    </StoreHighlight>
                </template>
            </div>
        </template>
        <template #after-form>
            <div class="mt-6" data-section="integration_effective">
                <DetailList
                    :title="t('store.settings.integrations.effective.title')"
                >
                    <DetailItem
                        v-for="row in rows"
                        :key="row.toggle"
                        :label="label(row.entry)"
                        :value="row.effective"
                    />
                </DetailList>
            </div>
        </template>
    </ResourceFormPage>
</template>

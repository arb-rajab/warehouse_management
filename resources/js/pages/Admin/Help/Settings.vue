<script setup lang="ts">
import { edit as settingsEdit } from '@/actions/App/Http/Controllers/Admin/SettingController';
import HelpTopicPage from '@/components/HelpTopicPage.vue';
import HelpUiPreview from '@/components/HelpUiPreview.vue';
import SettingFormFields from '@/components/SettingFormFields.vue';
import { filterSectionHeadingClass as sectionHeadingClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
</script>

<template>
    <HelpTopicPage
        :title="t('help.settings.title')"
        :feature-href="settingsEdit()"
        :feature-label="t('settings.edit.title')"
    >
        <section>
            <h2 :class="sectionHeadingClass">
                {{ t('help.settings.qrSize.heading') }}
            </h2>
            <p class="text-sm text-gray-700 dark:text-neutral-300">
                {{ t('help.settings.qrSize.body') }}
            </p>
            <HelpUiPreview inert class="mt-2 block max-w-xs">
                <SettingFormFields
                    id-prefix="help-settings-"
                    :qr-code-width="240"
                    :qr-code-height="240"
                    :errors="{}"
                />
            </HelpUiPreview>
        </section>
    </HelpTopicPage>
</template>

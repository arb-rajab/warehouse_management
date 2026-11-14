<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { testSmtp } from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import FormField from '@/components/FormField.vue';
import RequiredLegend from '@/components/RequiredLegend.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { t } from '@/lib/i18n';
import { provideRequiredFields } from '@/lib/requiredFields';

/**
 * The SMTP page's test email: sent at once with the effective settings, so a
 * wrong host or login shows here as an error. Its own form, with its own
 * required-fields legend, below the settings form.
 */
const hasRequiredFields = provideRequiredFields();
</script>

<template>
    <Form
        :action="testSmtp()"
        class="mt-6 max-w-sm space-y-4"
        data-form="smtp-test"
        #default="{ errors, processing }"
    >
        <h2 class="text-lg font-semibold">
            {{ t('store.settings.smtp.testTitle') }}
        </h2>
        <p class="text-sm text-muted">
            {{ t('store.settings.smtp.testHelp') }}
        </p>
        <RequiredLegend v-if="hasRequiredFields" />
        <FormField
            id="email"
            type="email"
            :label="t('store.settings.testEmail')"
            maxlength="255"
            required
            :error="errors.email"
        />
        <SubmitButton
            :label="t('store.settings.sendTest')"
            :processing-label="t('store.settings.sendingTest')"
            :processing="processing"
        />
    </Form>
</template>

<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import {
    updateActivation,
    updateFacebookChat,
    updateFacebookComments,
    updateAnalytics,
    updateRecaptcha,
    updateFirebase,
    updatePayments,
    testSmtp,
} from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreBooleanField from '@/components/StoreBooleanField.vue';
import StoreServerSettingsStatus from '@/components/StoreServerSettingsStatus.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { StoreServerSettings } from '@/types/store';
const props = defineProps<{
    section:
        | 'activation'
        | 'smtp'
        | 'socialLogin'
        | 'facebookChat'
        | 'facebookComments'
        | 'analytics'
        | 'recaptcha'
        | 'firebase'
        | 'payments'
        | 'filesystem';
    settings: Record<string, boolean>;
    /** The option or section a button sent the admin here to change, if any. */
    highlight?: string | null;
    /** The server values a setup check reads (SMTP and Firebase pages only). */
    server?: StoreServerSettings | null;
}>();
// The read-only block of server values each page shows, by its deep-link key.
const serverAnchors = {
    smtp: 'mail_credentials',
    firebase: 'firebase_server',
} as const;
const access = useStoreAccess();
const actions = {
    activation: updateActivation,
    facebookChat: updateFacebookChat,
    facebookComments: updateFacebookComments,
    analytics: updateAnalytics['/temp/admin/google-analytics'],
    recaptcha: updateRecaptcha,
    firebase: updateFirebase,
    payments: updatePayments,
};
const permissions = {
    activation: 'features_activation',
    smtp: 'smtp_settings',
    socialLogin: 'social_media_logins',
    facebookChat: 'facebook_chat',
    facebookComments: 'facebook_comment',
    analytics: 'analytics_tools_configuration',
    recaptcha: 'google_recaptcha_configuration',
    firebase: 'google_firebase_setting',
    payments: 'payment_methods_configurations',
    filesystem: 'file_system_&_cache_configuration',
};
const action = computed(() =>
    props.section in actions
        ? actions[props.section as keyof typeof actions]()
        : null,
);
const canWrite = computed(() => access.value.can(permissions[props.section]));
</script>
<template>
    <div>
        <ResourceFormPage
            v-if="action && canWrite"
            :title="t(`store.settings.titles.${section}`)"
            :action="action"
            :submit-label="t('store.settings.save')"
            :submitting-label="t('store.settings.saving')"
            :layout="StoreAdminLayout"
            wide
            #default="{ errors }"
        >
            <p class="flex items-center gap-2">
                <TriangleAlert class="h-4 w-4" />{{
                    t('store.settings.environmentReadOnly')
                }}
            </p>
            <p v-if="section === 'payments' || section === 'activation'">
                {{ t('store.settings.gatewayNotice') }}
            </p>
            <FieldError :message="errors.settings" />
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <StoreBooleanField
                    v-for="(value, key) in settings"
                    :key="key"
                    :name="`settings[${key}]`"
                    :label="t(`store.settings.keys.${key}`)"
                    :value="value"
                    :highlighted="key === highlight"
                    :error="errors[`settings.${key}`]"
                />
            </div>
            <StoreServerSettingsStatus
                v-if="server && section === 'firebase'"
                section="firebase"
                :server="server"
                :anchor="serverAnchors.firebase"
                :highlight="highlight"
            />
        </ResourceFormPage>
        <StoreAdminLayout v-else>
            <Head :title="t(`store.settings.titles.${section}`)" />
            <PageHeader :title="t(`store.settings.titles.${section}`)" />
            <p class="flex items-center gap-2">
                <TriangleAlert class="h-4 w-4" />{{
                    t('store.settings.environmentReadOnly')
                }}
            </p>
            <StoreServerSettingsStatus
                v-if="server && section === 'smtp'"
                section="smtp"
                :server="server"
                :anchor="serverAnchors.smtp"
                :highlight="highlight"
            />
            <Form
                v-if="
                    section === 'smtp' &&
                    access.can('smtp_settings') &&
                    access.can('send_newsletter')
                "
                :action="testSmtp()"
                class="mt-6 max-w-sm space-y-4"
                #default="{ errors, processing }"
            >
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
        </StoreAdminLayout>
    </div>
</template>

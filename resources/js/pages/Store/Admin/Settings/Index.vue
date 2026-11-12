<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import {
    updateActivation,
    updateFirebase,
} from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import FieldError from '@/components/FieldError.vue';
import PageHeader from '@/components/PageHeader.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreBooleanField from '@/components/StoreBooleanField.vue';
import StoreServerSettingsStatus from '@/components/StoreServerSettingsStatus.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { StoreServerSettings } from '@/types/store';
const props = defineProps<{
    section: 'activation' | 'socialLogin' | 'firebase' | 'filesystem';
    settings: Record<string, boolean>;
    /** The option or section a button sent the admin here to change, if any. */
    highlight?: string | null;
    /** The server values a setup check reads (Firebase page only). */
    server?: StoreServerSettings | null;
}>();
// The read-only block of server values each page shows, by its deep-link key.
// The SMTP, Analytics, Facebook chat, Facebook comments, reCAPTCHA and Payment
// methods pages are their own components (Settings/Smtp.vue, Analytics.vue,
// FacebookChat.vue, FacebookComments.vue, Recaptcha.vue, Payments.vue): they
// edit values saved over `.env`.
const serverAnchors = {
    firebase: 'firebase_server',
} as const;
const access = useStoreAccess();
const actions = {
    activation: updateActivation,
    firebase: updateFirebase,
};
const permissions = {
    activation: 'features_activation',
    socialLogin: 'social_media_logins',
    firebase: 'google_firebase_setting',
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
            <p v-if="section === 'activation'">
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
        </StoreAdminLayout>
    </div>
</template>

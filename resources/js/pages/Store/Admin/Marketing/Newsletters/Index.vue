<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
import { send } from '@/actions/App/Http/Controllers/Store/Admin/NewsletterController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreNewsletterFormFields from '@/components/StoreNewsletterFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';

defineProps<{ smtpConfigured: boolean }>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.newsletters.title')"
        :action="send()"
        :submit-label="t('store.newsletters.send')"
        :submitting-label="t('store.newsletters.sending')"
        :layout="StoreAdminLayout"
        wide
        #default="{ errors }"
    >
        <p
            v-if="!smtpConfigured"
            role="status"
            class="flex items-center gap-2 text-amber-700 dark:text-amber-400"
        >
            <TriangleAlert class="size-4" aria-hidden="true" />{{
                t('store.newsletters.smtpMissing')
            }}
        </p>
        <StoreNewsletterFormFields :errors="errors" />
    </ResourceFormPage>
</template>

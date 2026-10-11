<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import { t } from '@/lib/i18n';
import type { AdminLanguage } from '@/types/store';
defineProps<{ language?: AdminLanguage; errors: Record<string, string> }>();
</script>
<template>
    <div class="space-y-4">
        <FormField
            id="name"
            :label="t('store.languages.name')"
            :value="language?.name"
            :error="errors.name"
            maxlength="100"
            required
        />
        <FormField
            id="code"
            :label="t('store.languages.code')"
            :value="language?.code"
            :error="errors.code"
            :readonly="language?.is_default || language?.is_english"
            maxlength="10"
            pattern="[a-zA-Z]{2,3}([\-_][a-zA-Z0-9]{2,4})?"
            required
        />
        <FormField
            id="app_lang_code"
            :label="t('store.languages.appCode')"
            :value="language?.app_lang_code ?? ''"
            :error="errors.app_lang_code"
            maxlength="10"
            pattern="[a-zA-Z]{2,3}([\-_][a-zA-Z0-9]{2,4})?"
            required
        />
    </div>
</template>

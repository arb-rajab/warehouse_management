<script setup lang="ts">
import { ref } from 'vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import StoreNewsletterRecipients from '@/components/StoreNewsletterRecipients.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import { t } from '@/lib/i18n';

defineProps<{ errors: Record<string, string> }>();
const users = ref<string[]>([]);
const subscribers = ref<string[]>([]);
const content = ref('');
</script>

<template>
    <div class="space-y-4">
        <p>{{ t('store.newsletters.recipientHelp') }}</p>
        <div class="grid gap-4 md:grid-cols-2">
            <StoreNewsletterRecipients
                v-model="users"
                kind="users"
                :required="subscribers.length === 0"
                :errors="errors"
            />
            <StoreNewsletterRecipients
                v-model="subscribers"
                kind="subscribers"
                :required="users.length === 0"
                :errors="errors"
            />
        </div>
        <FormField
            id="subject"
            :label="t('store.newsletters.subject')"
            :error="errors.subject"
            maxlength="255"
            required
        />
        <StoreRichTextField
            id="content"
            v-model="content"
            name="content"
            :label="t('store.newsletters.content')"
            :error="errors.content"
            required
        />
        <FieldError :message="errors.smtp" />
    </div>
</template>

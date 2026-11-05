<script setup lang="ts">
import { computed } from 'vue';
import { recipients } from '@/actions/App/Http/Controllers/Store/Admin/NewsletterController';
import FieldError from '@/components/FieldError.vue';
import StoreSearchSelect from '@/components/StoreSearchSelect.vue';
import { t } from '@/lib/i18n';
import type { SearchOption } from '@/types/searchSelect';

/**
 * One recipient list of the newsletter form: a multiple StoreSearchSelect over
 * the bounded `newsletters.recipients` search. The email is the option id, so
 * the picks post as `<field>[]` and stay chosen while the search changes.
 */
const props = defineProps<{
    kind: 'users' | 'subscribers';
    required: boolean;
    errors: Record<string, string>;
}>();
const selected = defineModel<SearchOption[]>({ default: () => [] });
const field = computed(() =>
    props.kind === 'users' ? 'user_emails' : 'subscriber_emails',
);
const fieldErrors = computed(() =>
    Object.entries(props.errors)
        .filter(
            ([key]) => key === field.value || key.startsWith(`${field.value}.`),
        )
        .map(([, message]) => message),
);
</script>

<template>
    <div>
        <StoreSearchSelect
            :id="field"
            v-model:items="selected"
            multiple
            :name="`${field}[]`"
            :label="t(`store.newsletters.recipients.${kind}`)"
            :endpoint="recipients"
            :params="{ kind }"
            :placeholder="t(`store.newsletters.choose.${kind}`)"
            :search-placeholder="t(`store.newsletters.search.${kind}`)"
            :required="required"
            :error="fieldErrors[0]"
        />
        <FieldError
            v-for="message in fieldErrors.slice(1)"
            :key="message"
            :message="message"
        />
    </div>
</template>

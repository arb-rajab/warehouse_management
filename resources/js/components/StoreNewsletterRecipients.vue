<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { recipients } from '@/actions/App/Http/Controllers/Store/Admin/NewsletterController';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';

const props = defineProps<{
    kind: 'users' | 'subscribers';
    required: boolean;
    errors: Record<string, string>;
}>();
const selected = defineModel<string[]>({ default: () => [] });
const results = ref<string[]>([]);
const http = useHttp<{ q: string; kind: string }, string[]>({
    q: '',
    kind: props.kind,
});
const options = computed(() => [
    ...new Set([...selected.value, ...results.value]),
]);
const field = computed(() =>
    props.kind === 'users' ? 'user_emails' : 'subscriber_emails',
);
let sequence = 0;
function search(): void {
    const current = ++sequence;
    http.get(recipients.url(), {
        onSuccess: (response) => {
            if (current === sequence) {
                results.value = response;
            }
        },
    }).catch(() => undefined);
}
onMounted(search);
</script>

<template>
    <div class="space-y-2">
        <FormField
            :id="`${field}_search`"
            :label="t(`store.newsletters.search.${kind}`)"
            maxlength="255"
            type="search"
            :error="http.errors.q"
            @input="
                http.q = ($event.target as HTMLInputElement).value;
                search();
            "
        />
        <p v-if="http.processing" role="status" class="flex items-center gap-2">
            <LoaderCircle class="size-4 animate-spin" aria-hidden="true" />{{
                t('store.newsletters.searching')
            }}
        </p>
        <label :for="field" :class="fieldLabelClass">{{
            t(`store.newsletters.recipients.${kind}`)
        }}</label>
        <select
            :id="field"
            v-model="selected"
            :name="`${field}[]`"
            multiple
            :required="required"
            size="6"
            :class="plainFieldInputClass"
        >
            <option v-for="email in options" :key="email" :value="email">
                {{ email }}
            </option>
        </select>
        <FieldError :message="errors[field]" />
        <FieldError
            v-for="(message, key) in errors"
            :key="key"
            :message="key.startsWith(`${field}.`) ? message : undefined"
        />
    </div>
</template>

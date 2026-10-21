<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { recipients } from '@/actions/App/Http/Controllers/Store/Admin/NewsletterController';
import FieldError from '@/components/FieldError.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import { debounce, fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';

/**
 * One recipient list of the newsletter form. Emails come from the bounded
 * `newsletters.recipients` search (30 at a time); the chosen ones stay
 * selected while the search changes and post as `<field>[]`.
 */
const props = defineProps<{
    kind: 'users' | 'subscribers';
    required: boolean;
    errors: Record<string, string>;
}>();
const selected = defineModel<string[]>({ default: () => [] });
const results = ref<string[]>([]);
const failed = ref(false);
const http = useHttp<{ q: string; kind: string }, string[]>({
    q: '',
    kind: props.kind,
});
const field = computed(() =>
    props.kind === 'users' ? 'user_emails' : 'subscriber_emails',
);
const options = computed(() => [
    ...new Set([...selected.value, ...results.value]),
]);
const fieldErrors = computed(() =>
    Object.entries(props.errors)
        .filter(
            ([key]) => key === field.value || key.startsWith(`${field.value}.`),
        )
        .map(([, message]) => message),
);
let sequence = 0;

async function search(): Promise<void> {
    const current = ++sequence;
    http.cancel();
    failed.value = false;

    try {
        const response = await http.get(recipients.url());

        if (current === sequence && response) {
            results.value = response;
        }
    } catch {
        if (current === sequence) {
            failed.value = true;
        }
    }
}

const searchSoon = debounce(() => void search(), 300);

function onSearchInput(event: Event): void {
    http.q = (event.target as HTMLInputElement).value;
    searchSoon();
}

onMounted(() => void search());
onBeforeUnmount(() => http.cancel());
</script>

<template>
    <div class="space-y-2">
        <label :for="`${field}_search`" :class="fieldLabelClass">{{
            t(`store.newsletters.search.${kind}`)
        }}</label>
        <input
            :id="`${field}_search`"
            type="search"
            maxlength="255"
            autocomplete="off"
            :class="plainFieldInputClass"
            @input="onSearchInput"
        />
        <FieldError :message="http.errors.q" />
        <p
            v-if="http.processing"
            role="status"
            class="flex items-center gap-2 text-sm"
        >
            <LoaderCircle class="size-4 animate-spin" aria-hidden="true" />{{
                t('store.newsletters.searching')
            }}
        </p>
        <FieldError
            v-if="failed"
            :message="t('store.newsletters.searchFailed')"
        />
        <label :for="field" :class="fieldLabelClass"
            >{{ t(`store.newsletters.recipients.${kind}`)
            }}<RequiredMark v-if="required"
        /></label>
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
        <FieldError
            v-for="message in fieldErrors"
            :key="message"
            :message="message"
        />
    </div>
</template>

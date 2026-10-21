<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { LoaderCircle, Search } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import { search } from '@/actions/App/Http/Controllers/Store/Admin/OrderEntryController';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import {
    debounce,
    fieldLabelClass,
    plainFieldInputClass,
    filterTriggerButtonClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import { useRequiredField } from '@/lib/requiredFields';
import type { Paginated } from '@/types/admin';
import type { StoreOrderOption } from '@/types/store';
const props = defineProps<{
    id: string;
    name: string;
    label: string;
    kind: 'customers' | 'representatives' | 'products';
    selected?: StoreOrderOption;
    required?: boolean;
    error?: string;
}>();
const emit = defineEmits<{ select: [option: StoreOrderOption] }>();
useRequiredField(() => props.required);
const query = ref('');
const results = ref<StoreOrderOption[]>([]);
const current = ref(1);
const last = ref(1);
const loading = ref(false);
const failed = ref(false);
const http = useHttp({});
let sequence = 0;
let mounted = true;
function fetchPage(page = 1): void {
    if (!mounted) {
        return;
    }

    const requestSequence = ++sequence;
    loading.value = true;
    failed.value = false;
    http.get(
        search.url({ query: { kind: props.kind, q: query.value, page } }),
        {
            onSuccess: (response: unknown) => {
                if (sequence !== requestSequence) {
                    return;
                }

                const body = response as Paginated<StoreOrderOption>;
                results.value = body.data;
                current.value = body.meta.current_page;
                last.value = body.meta.last_page;
            },
            onError: () => {
                if (sequence === requestSequence) {
                    failed.value = true;
                }
            },
            onFinish: () => {
                if (sequence === requestSequence) {
                    loading.value = false;
                }
            },
        },
    );
}
watch(
    query,
    debounce(() => fetchPage(), 300),
);
onBeforeUnmount(() => {
    mounted = false;
    sequence++;
    http.cancel();
});
function choose(event: Event): void {
    const id = Number((event.target as HTMLSelectElement).value);
    const option = results.value.find((item) => item.id === id);

    if (option) {
        emit('select', option);
    }
}
</script>
<template>
    <div class="space-y-2">
        <FormField
            :id="`${id}-search`"
            :label="t('store.orders.searchFor', { label })"
            type="search"
            maxlength="100"
            @input="query = ($event.target as HTMLInputElement).value"
            @focus="fetchPage()"
        />
        <label :for="id" :class="fieldLabelClass"
            ><Search class="inline h-4 w-4" /> {{ label
            }}<RequiredMark v-if="required"
        /></label>
        <select
            :id="id"
            :name="name"
            :value="selected?.id ?? ''"
            :class="plainFieldInputClass"
            :required="required"
            @change="choose"
        >
            <option value="">{{ t('store.orders.choose') }}</option>
            <option
                v-if="
                    selected &&
                    !results.some((item) => item.id === selected?.id)
                "
                :value="selected.id"
            >
                {{ selected.name }}
            </option>
            <option
                v-for="option in results"
                :key="option.id"
                :value="option.id"
            >
                {{ option.name }}
                {{ option.serial ?? option.AccSysID ?? option.member_serial }}
            </option>
        </select>
        <FieldError :message="error" />
        <p v-if="failed" role="alert">{{ t('store.orders.searchFailed') }}</p>
        <div class="flex items-center gap-2">
            <LoaderCircle
                v-if="loading"
                class="h-4 w-4 animate-spin"
                :aria-label="t('store.orders.loading')"
            />
            <button
                v-if="current > 1"
                type="button"
                :class="filterTriggerButtonClass"
                :disabled="loading"
                @click="fetchPage(current - 1)"
            >
                {{ t('store.orders.previous') }}
            </button>
            <button
                v-if="current < last"
                type="button"
                :class="filterTriggerButtonClass"
                :disabled="loading"
                @click="fetchPage(current + 1)"
            >
                {{ t('store.orders.next') }}
            </button>
        </div>
    </div>
</template>

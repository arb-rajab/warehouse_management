<script setup lang="ts">
import { Mail, MapPin, Package, Tag, Truck } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { initialsOf, splitMatches } from '@/lib/highlightMatch';
import type { SearchOption, SearchOptionTone } from '@/types/searchSelect';

/**
 * The default content of a StoreSearchSelect result (.ai/rules/store-search-select.md):
 * a leading image, icon tile or initials, the label, a muted description, and
 * a badge, with the words of the query highlighted. `selected` is the same
 * content at the size of the closed field and `chip` is one picked value of a
 * multiple select (label only). Every part can be replaced through the
 * `image` and `badge` slots.
 */
const props = defineProps<{
    option: SearchOption;
    query?: string;
    variant?: 'row' | 'selected' | 'chip';
}>();

const icons = {
    package: Package,
    'map-pin': MapPin,
    tag: Tag,
    truck: Truck,
    mail: Mail,
} as const;
const tones: Record<SearchOptionTone, string> = {
    neutral:
        'bg-gray-100 text-gray-700 dark:bg-neutral-700 dark:text-neutral-200',
    success:
        'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    warning:
        'bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200',
    danger: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    info: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
};

const imageFailed = ref(false);
watch(
    () => props.option.image,
    () => {
        imageFailed.value = false;
    },
);

const variant = computed(() => props.variant ?? 'row');
const size = computed(
    () =>
        ({ row: 'size-10', selected: 'size-6', chip: 'size-5' })[variant.value],
);
const icon = computed(() => {
    const name = props.option.icon;

    return name && name !== 'user' ? icons[name] : null;
});
const initials = computed(() => initialsOf(props.option.label));
const labelParts = computed(() =>
    splitMatches(props.option.label, props.query ?? ''),
);
const descriptionParts = computed(() =>
    splitMatches(props.option.description ?? '', props.query ?? ''),
);
</script>

<template>
    <span
        class="flex min-w-0 flex-1 items-center"
        :class="variant === 'row' ? 'gap-3' : 'gap-2'"
    >
        <slot name="image" :option="option">
            <img
                v-if="option.image && !imageFailed"
                :src="option.image"
                alt=""
                loading="lazy"
                class="shrink-0 rounded-md border border-gray-200 object-cover dark:border-neutral-600"
                :class="size"
                data-testid="search-option-image"
                @error="imageFailed = true"
            />
            <span
                v-else-if="icon"
                class="grid shrink-0 place-items-center rounded-md bg-gray-100 text-gray-600 dark:bg-neutral-700 dark:text-neutral-300"
                :class="size"
                data-testid="search-option-icon"
            >
                <component
                    :is="icon"
                    :class="variant === 'row' ? 'size-5' : 'size-3.5'"
                    aria-hidden="true"
                />
            </span>
            <span
                v-else
                class="grid shrink-0 place-items-center rounded-full bg-blue-100 font-semibold text-blue-800 dark:bg-blue-900/40 dark:text-blue-200"
                :class="[size, variant === 'row' ? 'text-xs' : 'text-[10px]']"
                aria-hidden="true"
                data-testid="search-option-initials"
                >{{ initials }}</span
            >
        </slot>
        <span class="min-w-0 flex-1">
            <span
                dir="auto"
                class="block truncate text-sm text-gray-900 dark:text-neutral-100"
                :class="variant === 'chip' ? '' : 'font-medium'"
                data-testid="search-option-label"
                ><template v-for="(part, index) in labelParts" :key="index"
                    ><mark
                        v-if="part.match"
                        class="rounded bg-yellow-200 px-0.5 text-inherit dark:bg-yellow-500/40 dark:text-white"
                        >{{ part.text }}</mark
                    ><template v-else>{{ part.text }}</template></template
                ></span
            >
            <span
                v-if="option.description && variant !== 'chip'"
                dir="auto"
                class="block truncate text-xs text-gray-500 dark:text-neutral-400"
                data-testid="search-option-description"
                ><template
                    v-for="(part, index) in descriptionParts"
                    :key="index"
                    ><mark
                        v-if="part.match"
                        class="rounded bg-yellow-200 px-0.5 text-inherit dark:bg-yellow-500/40 dark:text-white"
                        >{{ part.text }}</mark
                    ><template v-else>{{ part.text }}</template></template
                ></span
            >
        </span>
        <slot v-if="variant === 'row'" name="badge" :option="option">
            <span
                v-if="option.badge"
                class="inline-flex shrink-0 items-center rounded-full px-1.5 py-0.5 text-xs font-semibold whitespace-nowrap"
                :class="tones[option.badge.tone]"
                data-testid="search-option-badge"
                >{{ option.badge.text }}</span
            >
        </slot>
    </span>
</template>

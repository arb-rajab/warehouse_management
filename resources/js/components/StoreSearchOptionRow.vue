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
    neutral: 'bg-subtle text-ink',
    success: 'bg-success-soft text-success-strong',
    warning: 'bg-warning-soft text-warning-strong',
    danger: 'bg-danger-soft text-danger-strong',
    info: 'bg-info-soft text-info-strong',
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
                class="shrink-0 rounded-md border border-line object-cover"
                :class="size"
                data-testid="search-option-image"
                @error="imageFailed = true"
            />
            <span
                v-else-if="icon"
                class="grid shrink-0 place-items-center rounded-md bg-subtle text-muted"
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
                class="grid shrink-0 place-items-center rounded-full bg-info-soft font-semibold text-info-strong"
                :class="[size, variant === 'row' ? 'text-xs' : 'text-[10px]']"
                aria-hidden="true"
                data-testid="search-option-initials"
                >{{ initials }}</span
            >
        </slot>
        <span class="min-w-0 flex-1">
            <span
                dir="auto"
                class="block truncate text-sm text-ink"
                :class="variant === 'chip' ? '' : 'font-medium'"
                data-testid="search-option-label"
                ><template v-for="(part, index) in labelParts" :key="index"
                    ><mark
                        v-if="part.match"
                        class="rounded bg-warning-soft px-0.5 text-inherit ring-1 ring-warning/40"
                        >{{ part.text }}</mark
                    ><template v-else>{{ part.text }}</template></template
                ></span
            >
            <span
                v-if="option.description && variant !== 'chip'"
                dir="auto"
                class="block truncate text-xs text-muted"
                data-testid="search-option-description"
                ><template
                    v-for="(part, index) in descriptionParts"
                    :key="index"
                    ><mark
                        v-if="part.match"
                        class="rounded bg-warning-soft px-0.5 text-inherit ring-1 ring-warning/40"
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

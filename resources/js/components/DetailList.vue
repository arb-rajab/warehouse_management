<script setup lang="ts">
import { computed } from 'vue';

/**
 * A titled group of label/value details (detail-lists.md): a card holding a
 * `<dl>` of DetailItem children, one column on a phone and `columns` from `md`
 * up (4 columns is two from `sm` and four from `lg`). `keepColumns` keeps the columns on a phone too, for short values such as
 * amounts. `plain` drops the card, for a dialog or form that already has its
 * own container.
 */
const props = withDefaults(
    defineProps<{
        title?: string;
        columns?: 1 | 2 | 3 | 4;
        keepColumns?: boolean;
        plain?: boolean;
    }>(),
    { columns: 1, keepColumns: false, plain: false },
);

const columnClass = computed(() =>
    props.keepColumns
        ? {
              1: 'grid-cols-1',
              2: 'grid-cols-2',
              3: 'grid-cols-3',
              4: 'grid-cols-4',
          }[props.columns]
        : {
              1: 'grid-cols-1',
              2: 'grid-cols-1 md:grid-cols-2',
              3: 'grid-cols-1 md:grid-cols-3',
              4: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
          }[props.columns],
);
</script>

<template>
    <section
        class="min-w-0"
        :class="{
            'rounded-lg border border-line bg-surface p-4': !plain,
        }"
    >
        <h2 v-if="title" class="mb-3 text-sm font-semibold">{{ title }}</h2>
        <dl class="grid gap-x-6 gap-y-5" :class="columnClass">
            <slot />
        </dl>
    </section>
</template>

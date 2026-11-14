<script setup lang="ts">
import { Comment, Fragment, Text, useSlots } from 'vue';
import type { VNode } from 'vue';

/**
 * One label/value pair of a DetailList (detail-lists.md): the label is a small
 * muted line directly above its value, so the two are always read together.
 *
 * Plain values go in `value`; rich content (a TableObjectLink, an
 * AddressMapLink, a download link) goes in the default slot. A missing value,
 * or a slot that renders nothing (a `v-if` that is false), shows a muted "-"
 * instead of a blank cell.
 */
defineProps<{
    label: string;
    value?: string | number | null;
    /** Spans every column of the list. */
    full?: boolean;
    /** Keeps line breaks in multi-line text such as notes. */
    preserve?: boolean;
    /** A money amount or percentage: larger, medium weight, tabular figures. */
    amount?: boolean;
}>();

const slots = useSlots();

function hasContent(nodes: VNode[]): boolean {
    return nodes.some((node) => {
        if (node.type === Comment) {
            return false;
        }

        if (node.type === Text) {
            return String(node.children ?? '').trim() !== '';
        }

        if (node.type === Fragment) {
            return Array.isArray(node.children)
                ? hasContent(node.children as VNode[])
                : false;
        }

        return true;
    });
}

const slotFilled = () => (slots.default ? hasContent(slots.default()) : false);
const valueFilled = (value: string | number | null | undefined) =>
    value !== null && value !== undefined && String(value).trim() !== '';
</script>

<template>
    <div class="min-w-0" :class="{ 'col-span-full': full }">
        <dt class="text-xs text-muted">
            {{ label }}
        </dt>
        <dd
            class="mt-0.5"
            :class="[
                amount
                    ? 'text-base font-medium tabular-nums [unicode-bidi:isolate]'
                    : 'text-sm break-words',
                { 'whitespace-pre-wrap': preserve },
            ]"
        >
            <slot v-if="slotFilled()" />
            <template v-else-if="valueFilled(value)">{{ value }}</template>
            <span v-else data-detail-empty class="text-muted">-</span>
        </dd>
    </div>
</template>

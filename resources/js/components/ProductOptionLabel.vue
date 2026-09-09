<script setup lang="ts">
import { computed } from 'vue';
import { productAlternateName, productName } from '@/lib/productName';
import type { ProductFilterOption } from '@/types/admin';

const props = defineProps<{
    product: ProductFilterOption;
}>();

const label = computed(() =>
    productName(props.product.name, props.product.ar_name),
);

const alternateLabel = computed(() =>
    productAlternateName(props.product.name, props.product.ar_name),
);
</script>

<template>
    <span class="min-w-0">
        <!--
            `dir="auto"` per line, not on a shared wrapper: the document's
            direction is set once on `<html>` (see resources/views/app.blade.php),
            so the Latin name inside the Arabic panel — or the Arabic one inside
            the English panel — reorders against the surrounding layout without
            its own isolation. Two elements rather than one interpolated string
            for the same reason: no shared punctuation to be reordered.
        -->
        <span
            dir="auto"
            class="block break-words"
            data-testid="product-option-name"
            >{{ label }}</span
        >
        <span
            v-if="alternateLabel"
            dir="auto"
            class="block text-xs break-words text-gray-500 dark:text-neutral-400"
            data-testid="product-option-alternate-name"
            >{{ alternateLabel }}</span
        >
    </span>
</template>

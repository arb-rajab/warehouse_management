<script setup lang="ts">
/** Only HTML produced by server clean_html(); never use with user input or unsanitized fields. */
defineProps<{ html: string }>();
</script>
<template>
    <!-- Server-sanitized ticket details_html / reply_html only. -->
    <!-- eslint-disable-next-line vue/no-v-html -->
    <div
        class="break-words [&_a]:underline [&_li]:ms-6 [&_ol]:list-decimal [&_table]:w-full [&_td]:border [&_th]:border [&_ul]:list-disc"
        v-html="html"
    />
</template>

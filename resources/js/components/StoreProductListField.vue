<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import { nextTick, ref } from 'vue';
import FieldError from '@/components/FieldError.vue';
import { plainFieldInputClass } from '@/lib/filters';

/**
 * A list of free texts a product form adds row by row and posts as
 * `<name>[]`: the product types and the store barcodes, starting from the
 * stored `values` on the edit form. Empty rows are posted and skipped by the
 * server, as the legacy form's were. The `after-input` slot renders below
 * each row's input with its current `value` (the barcode images).
 *
 * With `seenName`, the field also posts the values it was loaded with as
 * `<seenName>[]` hidden inputs (the edit form's `product_barcodes_seen`), so
 * the server deletes only what the form showed and the user removed. A list
 * loaded empty still posts one blank entry: the browser drops an empty array
 * from a multipart post, and without the key the server would fall back to
 * replacing the whole list. The server skips the blank entry.
 */
const props = defineProps<{
    id: string;
    name: string;
    label: string;
    addLabel: string;
    removeLabel: string;
    placeholder: string;
    values?: string[];
    seenName?: string;
    errors: Record<string, string>;
}>();
let nextKey = 0;
// The values the rows were loaded with, whatever the user edits afterwards.
const loaded = [...(props.values ?? [])];
const rows = ref<{ key: number; value: string }[]>(
    loaded.map((value) => ({ key: nextKey++, value })),
);
const list = ref<HTMLElement>();

async function add(): Promise<void> {
    rows.value.push({ key: nextKey++, value: '' });
    await nextTick();
    list.value?.querySelector<HTMLInputElement>('li:last-child input')?.focus();
}
function remove(key: number): void {
    rows.value = rows.value.filter((row) => row.key !== key);
}
</script>

<template>
    <div :id="id" role="group" :aria-labelledby="`${id}_label`">
        <p :id="`${id}_label`" class="text-sm font-medium">{{ label }}</p>
        <template v-if="seenName">
            <input
                v-for="(value, index) in loaded.length > 0 ? loaded : ['']"
                :key="index"
                type="hidden"
                :name="`${seenName}[]`"
                :value="value"
            />
        </template>
        <ul ref="list" class="mt-2 space-y-2">
            <li
                v-for="(row, index) in rows"
                :key="row.key"
                class="flex items-start gap-2"
            >
                <div class="flex-1">
                    <input
                        v-model="row.value"
                        type="text"
                        :name="`${name}[]`"
                        :aria-label="`${label} ${index + 1}`"
                        :placeholder="placeholder"
                        :class="plainFieldInputClass"
                        maxlength="255"
                    />
                    <slot
                        name="after-input"
                        :value="row.value"
                        :index="index"
                    />
                    <FieldError :message="errors[`${name}.${index}`]" />
                </div>
                <button
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-red-300 px-2 py-2 text-sm text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950"
                    :aria-label="`${removeLabel} ${index + 1}`"
                    @click="remove(row.key)"
                >
                    <Trash2 class="h-4 w-4" />
                </button>
            </li>
        </ul>
        <button
            type="button"
            class="mt-2 inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
            @click="add"
        >
            <Plus class="h-4 w-4" />{{ addLabel }}
        </button>
        <FieldError :message="errors[name]" />
    </div>
</template>

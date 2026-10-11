<script setup lang="ts">
import {
    Bold,
    Italic,
    Underline,
    List,
    ListOrdered,
    Undo2,
    Redo2,
    Link,
    ImagePlus,
    Table,
    Code,
    Maximize,
    X,
    AlignLeft,
    AlignCenter,
    AlignRight,
    RemoveFormatting,
} from '@lucide/vue';
import { Extension } from '@tiptap/core';
import Image from '@tiptap/extension-image';
import { TableKit } from '@tiptap/extension-table';
import TextAlign from '@tiptap/extension-text-align';
import { TextStyleKit } from '@tiptap/extension-text-style';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { computed, ref, watch } from 'vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import StoreFileManagerDialog from '@/components/StoreFileManagerDialog.vue';
import {
    fieldLabelClass,
    plainFieldInputClass,
    filterTriggerButtonClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import { safeEditorHtml, safeEditorUrl } from '@/lib/storeRichText';
import type { StoreUpload } from '@/types/store';
const props = withDefaults(
    defineProps<{
        id: string;
        name: string;
        label: string;
        error?: string;
        required?: boolean;
        maxlength?: number;
        basic?: boolean;
    }>(),
    { required: false, maxlength: 100000, basic: false },
);
const html = defineModel<string>({ required: true });
const source = ref(false);
const fullscreen = ref(false);
const picker = ref(false);
const linkUrl = ref('');
const linkOpen = ref(false);
const Attributes = Extension.create({
    name: 'legacyFormatting',
    addGlobalAttributes() {
        return [
            {
                types: [
                    'paragraph',
                    'heading',
                    'blockquote',
                    'bulletList',
                    'orderedList',
                    'listItem',
                    'table',
                    'tableRow',
                    'tableCell',
                    'tableHeader',
                    'image',
                    'textStyle',
                ],
                attributes: Object.fromEntries(
                    [
                        'class',
                        'dir',
                        'lang',
                        'title',
                        'style',
                        'width',
                        'height',
                    ].map((name) => [
                        name,
                        {
                            default: null,
                            parseHTML: (element: HTMLElement) =>
                                element.getAttribute(name),
                        },
                    ]),
                ),
            },
        ];
    },
});
const editor = useEditor({
    content: safeEditorHtml(html.value),
    extensions: [
        StarterKit.configure({ link: { openOnClick: false } }),
        TextStyleKit,
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        Image.configure({ allowBase64: true }),
        TableKit,
        Attributes,
    ],
    editorProps: {
        attributes: {
            id: props.id,
            role: 'textbox',
            'aria-label': props.label,
            'aria-multiline': 'true',
            class: 'min-h-40 rounded-md border border-gray-300 bg-white p-3 text-gray-900 outline-none focus:ring-2 focus:ring-gray-400 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 [&_ul]:list-disc [&_ol]:list-decimal [&_li]:ms-6 [&_table]:w-full [&_td]:border [&_th]:border [&_td]:p-2 [&_th]:p-2 [&_a]:underline',
        },
        transformPastedHTML: safeEditorHtml,
    },
    onUpdate: ({ editor }) => {
        html.value = editor.isEmpty ? '' : editor.getHTML();
    },
});
watch(html, (value) => {
    if (!source.value && editor.value && value !== editor.value.getHTML()) {
        editor.value.commands.setContent(safeEditorHtml(value), {
            emitUpdate: false,
        });
    }
});
const tools = computed(() =>
    editor.value
        ? [
              {
                  key: 'bold',
                  icon: Bold,
                  active: editor.value.isActive('bold'),
                  run: () => editor.value?.chain().focus().toggleBold().run(),
              },
              {
                  key: 'italic',
                  icon: Italic,
                  active: editor.value.isActive('italic'),
                  run: () => editor.value?.chain().focus().toggleItalic().run(),
              },
              {
                  key: 'underline',
                  icon: Underline,
                  active: editor.value.isActive('underline'),
                  run: () =>
                      editor.value?.chain().focus().toggleUnderline().run(),
              },
              {
                  key: 'bulletList',
                  icon: List,
                  active: editor.value.isActive('bulletList'),
                  run: () =>
                      editor.value?.chain().focus().toggleBulletList().run(),
              },
              {
                  key: 'orderedList',
                  icon: ListOrdered,
                  active: editor.value.isActive('orderedList'),
                  run: () =>
                      editor.value?.chain().focus().toggleOrderedList().run(),
              },
              {
                  key: 'clear',
                  icon: RemoveFormatting,
                  active: false,
                  run: () =>
                      editor.value
                          ?.chain()
                          .focus()
                          .clearNodes()
                          .unsetAllMarks()
                          .run(),
              },
              {
                  key: 'undo',
                  icon: Undo2,
                  active: false,
                  run: () => editor.value?.chain().focus().undo().run(),
              },
              {
                  key: 'redo',
                  icon: Redo2,
                  active: false,
                  run: () => editor.value?.chain().focus().redo().run(),
              },
          ]
        : [],
);
function toggleSource() {
    source.value = !source.value;

    if (!source.value) {
        editor.value?.commands.setContent(safeEditorHtml(html.value), {
            emitUpdate: false,
        });
    }
}
function addLink() {
    if (safeEditorUrl(linkUrl.value)) {
        editor.value
            ?.chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: linkUrl.value })
            .run();
        linkOpen.value = false;
    }
}
function insertImages(files: StoreUpload[]) {
    for (const file of files) {
        if (file.url && safeEditorUrl(file.url, true)) {
            editor.value
                ?.chain()
                .focus()
                .setImage({ src: file.url, alt: file.name ?? '' })
                .run();
        }
    }
}
</script>
<template>
    <div
        :class="
            fullscreen
                ? 'fixed inset-2 z-50 overflow-auto rounded-lg bg-white p-4 dark:bg-neutral-900'
                : ''
        "
        class="space-y-2"
    >
        <label :for="id" :class="fieldLabelClass">{{ label }}</label>
        <div
            v-if="!source"
            class="flex flex-wrap gap-1"
            role="group"
            :aria-label="t('store.editor.toolbar')"
        >
            <button
                v-for="tool in tools"
                :key="tool.key"
                type="button"
                :data-action="tool.key"
                :aria-label="t(`store.editor.${tool.key}`)"
                :aria-pressed="tool.active"
                :class="filterTriggerButtonClass"
                @click="tool.run"
            >
                <component :is="tool.icon" class="h-4 w-4" />
            </button>
            <button
                type="button"
                :class="filterTriggerButtonClass"
                :aria-label="t('store.editor.link')"
                @click="linkOpen = !linkOpen"
            >
                <Link class="h-4 w-4" />
            </button>
            <template v-if="!basic">
                <select
                    :aria-label="t('store.editor.heading')"
                    :class="plainFieldInputClass"
                    class="w-auto"
                    @change="
                        Number(($event.target as HTMLSelectElement).value)
                            ? editor
                                  ?.chain()
                                  .focus()
                                  .setHeading({
                                      level: Number(
                                          ($event.target as HTMLSelectElement)
                                              .value,
                                      ) as 1 | 2 | 3,
                                  })
                                  .run()
                            : editor?.chain().focus().setParagraph().run()
                    "
                >
                    <option value="0">{{ t('store.editor.paragraph') }}</option>
                    <option v-for="n in 3" :key="n" :value="n">
                        {{ t('store.editor.headingNumber', { n }) }}
                    </option>
                </select>
                <button
                    v-for="(icon, alignment) in {
                        left: AlignLeft,
                        center: AlignCenter,
                        right: AlignRight,
                    }"
                    :key="alignment"
                    type="button"
                    :class="filterTriggerButtonClass"
                    :aria-label="t(`store.editor.${alignment}`)"
                    @click="
                        editor?.chain().focus().setTextAlign(alignment).run()
                    "
                >
                    <component :is="icon" class="h-4 w-4" />
                </button>
                <label :class="filterTriggerButtonClass"
                    >{{ t('store.editor.color')
                    }}<input
                        type="color"
                        :aria-label="t('store.editor.color')"
                        @input="
                            editor
                                ?.chain()
                                .focus()
                                .setColor(
                                    ($event.target as HTMLInputElement).value,
                                )
                                .run()
                        "
                /></label>
                <label :class="filterTriggerButtonClass"
                    >{{ t('store.editor.backgroundColor')
                    }}<input
                        type="color"
                        :aria-label="t('store.editor.backgroundColor')"
                        @input="
                            editor
                                ?.chain()
                                .focus()
                                .setBackgroundColor(
                                    ($event.target as HTMLInputElement).value,
                                )
                                .run()
                        "
                /></label>
                <button
                    type="button"
                    :class="filterTriggerButtonClass"
                    :aria-label="t('store.editor.image')"
                    @click="picker = true"
                >
                    <ImagePlus class="h-4 w-4" />
                </button>
                <button
                    type="button"
                    :class="filterTriggerButtonClass"
                    :aria-label="t('store.editor.table')"
                    @click="
                        editor
                            ?.chain()
                            .focus()
                            .insertTable({
                                rows: 3,
                                cols: 3,
                                withHeaderRow: true,
                            })
                            .run()
                    "
                >
                    <Table class="h-4 w-4" />
                </button>
                <button
                    v-if="editor?.isActive('table')"
                    type="button"
                    :class="filterTriggerButtonClass"
                    @click="editor?.chain().focus().deleteTable().run()"
                >
                    <X class="h-4 w-4" />{{ t('store.editor.removeTable') }}
                </button>
            </template>
        </div>
        <div v-if="linkOpen" class="flex items-end gap-2">
            <FormField
                :id="`${id}-link`"
                :label="t('store.editor.link')"
                :value="linkUrl"
                maxlength="2048"
                @input="linkUrl = ($event.target as HTMLInputElement).value"
            /><button
                type="button"
                :class="filterTriggerButtonClass"
                @click="addLink"
            >
                {{ t('store.editor.insertLink') }}</button
            ><button
                type="button"
                :class="filterTriggerButtonClass"
                @click="editor?.chain().focus().unsetLink().run()"
            >
                {{ t('store.editor.removeLink') }}
            </button>
        </div>
        <EditorContent v-show="!source" :editor="editor" />
        <textarea
            :id="`${id}-source`"
            :name="name"
            v-model="html"
            :aria-label="source ? t('store.editor.source') : label"
            :class="source ? plainFieldInputClass : 'sr-only'"
            :tabindex="source ? 0 : -1"
            :required="required"
            :maxlength="maxlength"
            rows="10"
        />
        <div class="flex gap-2">
            <button
                type="button"
                :class="filterTriggerButtonClass"
                :aria-pressed="source"
                @click="toggleSource"
            >
                <Code class="h-4 w-4" />{{ t('store.editor.source') }}</button
            ><button
                type="button"
                :class="filterTriggerButtonClass"
                :aria-pressed="fullscreen"
                @click="fullscreen = !fullscreen"
            >
                <Maximize class="h-4 w-4" />{{ t('store.editor.fullscreen') }}
            </button>
        </div>
        <FieldError :message="error" />
        <StoreFileManagerDialog
            v-if="!basic"
            v-model:open="picker"
            :selected="[]"
            type="image"
            multiple
            @select="insertImages"
        />
    </div>
</template>

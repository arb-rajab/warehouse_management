<script setup lang="ts">
import {
    AlignCenter,
    AlignJustify,
    AlignLeft,
    AlignRight,
    Baseline,
    BetweenHorizontalEnd,
    BetweenVerticalEnd,
    Bold,
    Highlighter,
    ImagePlus,
    Italic,
    Link as LinkIcon,
    List,
    ListOrdered,
    Quote,
    Redo2,
    RemoveFormatting,
    Strikethrough,
    Table,
    Trash2,
    Underline,
    Undo2,
} from '@lucide/vue';
import { Extension } from '@tiptap/core';
import type { Editor, EditorOptions } from '@tiptap/core';
import Image from '@tiptap/extension-image';
import { TableKit } from '@tiptap/extension-table';
import TextAlign from '@tiptap/extension-text-align';
import {
    BackgroundColor,
    Color,
    TextStyle,
} from '@tiptap/extension-text-style';
import type { EditorView } from '@tiptap/pm/view';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import type { Component } from 'vue';
import {
    computed,
    onMounted,
    onUpdated,
    ref,
    useTemplateRef,
    watch,
} from 'vue';
import FieldError from '@/components/FieldError.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import StoreFileManagerDialog from '@/components/StoreFileManagerDialog.vue';
import {
    fieldLabelClass,
    mapToolbarButtonClass,
    plainFieldInputClass,
    selectedToggleClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { StoreUpload } from '@/types/store';

/**
 * The store back office's shared rich-text field (Tiptap). It posts the
 * editor's HTML under `name`; the server cleans it with
 * App\Services\HtmlSanitizer before storing it. Images come only from the
 * canonical upload picker, never as base64. Until the content is edited, the
 * stored HTML posts back unchanged.
 */
const props = withDefaults(
    defineProps<{
        id: string;
        name: string;
        label: string;
        error?: string;
        required?: boolean;
    }>(),
    { required: false },
);
const html = defineModel<string>({ default: '' });
const access = useStoreAccess();
const toolbar = useTemplateRef<HTMLElement>('toolbar');
const linkButton = useTemplateRef<HTMLButtonElement>('linkButton');
const linkInput = useTemplateRef<HTMLInputElement>('linkInput');
const picker = ref(false);
const draft = ref<StoreUpload[]>([]);
const linkOpen = ref(false);
const linkUrl = ref('');
const linkError = ref<string>();
const requiredError = ref<string>();
const hasContent = ref(html.value.trim() !== '');
const revision = ref(0);
const message = computed(() => props.error ?? requiredError.value);
let tabStop = 'blockStyle';

/** The schemes HtmlSanitizer::safeUrl() keeps, for links and for images. */
const linkSchemes = ['http', 'https', 'mailto', 'tel'];
const imageSchemes = ['http', 'https'];

function isSafeUrl(url: string, schemes: string[]): boolean {
    const compact = url.replace(/[\u0000- \u007f-\u009f]+/g, '');
    const scheme = /^([a-z][a-z0-9+.-]*):/i.exec(compact)?.[1].toLowerCase();

    return compact !== '' && (scheme === undefined || schemes.includes(scheme));
}

/** Declarations the alignment and colour extensions own. */
const managedStyles = ['text-align', 'color', 'background-color'];

function styleDeclarations(style: string | null): string[] {
    return (style ?? '')
        .split(';')
        .map((declaration) => declaration.trim())
        .filter((declaration) => declaration.includes(':'));
}

function isManagedStyle(declaration: string): boolean {
    return managedStyles.includes(
        declaration.split(':', 1)[0].trim().toLowerCase(),
    );
}

/**
 * Keeps the attributes stored HTML carries that no editor extension models
 * (a table's class, a paragraph's dir or indent, an image's width), so
 * editing a legacy post does not drop them.
 */
const StoredAttributes = Extension.create({
    name: 'storedAttributes',
    addGlobalAttributes() {
        return [
            {
                types: [
                    'paragraph',
                    'heading',
                    'blockquote',
                    'codeBlock',
                    'bulletList',
                    'orderedList',
                    'listItem',
                    'table',
                    'tableRow',
                    'tableHeader',
                    'tableCell',
                    'image',
                    'textStyle',
                ],
                attributes: {
                    class: {
                        default: null,
                        parseHTML: (element) => element.getAttribute('class'),
                    },
                    dir: {
                        default: null,
                        parseHTML: (element) => element.getAttribute('dir'),
                    },
                    lang: {
                        default: null,
                        parseHTML: (element) => element.getAttribute('lang'),
                    },
                    storedStyle: {
                        default: null,
                        parseHTML: (element) =>
                            styleDeclarations(element.getAttribute('style'))
                                .filter(
                                    (declaration) =>
                                        !isManagedStyle(declaration),
                                )
                                .join('; ') || null,
                        renderHTML: (attributes) =>
                            attributes.storedStyle
                                ? { style: attributes.storedStyle }
                                : {},
                    },
                },
            },
        ];
    },
});

/**
 * Pasted or dropped HTML loses its classes, any style the toolbar cannot
 * set, and every image that is not an http(s) or relative URL, so nothing
 * base64 or foreign to the editor's own formatting gets in that way.
 */
function cleanPastedHtml(pasted: string): string {
    const document = new DOMParser().parseFromString(pasted, 'text/html');

    for (const image of Array.from(document.body.querySelectorAll('img'))) {
        if (!isSafeUrl(image.getAttribute('src') ?? '', imageSchemes)) {
            image.remove();
        }
    }

    for (const element of Array.from(document.body.querySelectorAll('*'))) {
        element.removeAttribute('class');
        const kept = styleDeclarations(element.getAttribute('style')).filter(
            isManagedStyle,
        );

        if (kept.length > 0) {
            element.setAttribute('style', kept.join('; '));
        } else {
            element.removeAttribute('style');
        }
    }

    return document.body.innerHTML;
}

/**
 * Takes the browser's selection into the editor before a key moves focus
 * away: Chromium may not have dispatched `selectionchange` yet (after
 * Shift+Home, say), and ProseMirror ignores it once the content has lost
 * focus, so a toolbar command would act on a stale caret. A selection the
 * DOM already shows is not written back, so focus stays where it is.
 */
function readDomSelection(view: EditorView): void {
    const selection = view.dom.ownerDocument.getSelection();
    const { anchorNode, focusNode } = selection ?? {};

    if (
        selection &&
        anchorNode &&
        focusNode &&
        view.dom.contains(anchorNode) &&
        view.dom.contains(focusNode)
    ) {
        editor.value?.commands.setTextSelection({
            from: view.posAtDOM(anchorNode, selection.anchorOffset),
            to: view.posAtDOM(focusNode, selection.focusOffset),
        });
    }
}

function editorProps(): EditorOptions['editorProps'] {
    return {
        attributes: {
            id: props.id,
            role: 'textbox',
            'aria-multiline': 'true',
            'aria-labelledby': `${props.id}-label`,
            ...(props.required ? { 'aria-required': 'true' } : {}),
            ...(message.value
                ? {
                      'aria-invalid': 'true',
                      'aria-describedby': `${props.id}-error`,
                  }
                : {}),
            class: [
                'min-h-48 rounded-md border bg-white p-3 text-sm text-gray-900 focus:ring-2 focus:ring-gray-400 focus:outline-none dark:bg-neutral-900 dark:text-neutral-100',
                message.value
                    ? 'border-red-600 dark:border-red-400'
                    : 'border-gray-300 dark:border-neutral-700',
                '[&_a]:text-blue-700 [&_a]:underline dark:[&_a]:text-blue-300 [&_blockquote]:border-s-4 [&_blockquote]:border-gray-300 [&_blockquote]:ps-3 dark:[&_blockquote]:border-neutral-600 [&_h1]:text-2xl [&_h1]:font-bold [&_h2]:text-xl [&_h2]:font-bold [&_h3]:text-lg [&_h3]:font-semibold [&_h4]:font-semibold [&_h5]:font-semibold [&_h6]:font-semibold [&_img]:inline [&_img]:max-w-full [&_li]:ms-6 [&_ol]:list-decimal [&_p]:my-1 [&_pre]:rounded [&_pre]:bg-gray-100 [&_pre]:p-2 dark:[&_pre]:bg-neutral-800 [&_table]:w-full [&_table]:border-collapse [&_td]:border [&_td]:border-gray-300 [&_td]:p-2 dark:[&_td]:border-neutral-600 [&_th]:border [&_th]:border-gray-300 [&_th]:p-2 [&_th]:font-semibold dark:[&_th]:border-neutral-600 [&_ul]:list-disc [&_.selectedCell]:bg-blue-100 dark:[&_.selectedCell]:bg-blue-950 [&_.ProseMirror-selectednode]:outline-2 [&_.ProseMirror-selectednode]:outline-blue-500',
            ].join(' '),
        },
        transformPastedHTML: cleanPastedHtml,
        handleKeyDown: (view, event) => {
            const toToolbar = event.altKey && event.key === 'F10';

            if (toToolbar || event.key === 'Tab') {
                readDomSelection(view);
            }

            if (toToolbar) {
                focusToolbar();
            }

            return toToolbar;
        },
    };
}

/**
 * The HTML to post: nothing for a blank document (one empty paragraph), the
 * editor's HTML otherwise, so an empty table or image row is not lost.
 */
function serialize(current: Editor): string {
    const { doc } = current.state;
    const blank =
        doc.childCount === 1 &&
        doc.firstChild?.type.name === 'paragraph' &&
        doc.firstChild.childCount === 0;

    return blank ? '' : current.getHTML();
}

const editor = useEditor({
    content: html.value,
    extensions: [
        StarterKit.configure({
            link: {
                openOnClick: false,
                defaultProtocol: 'https',
                isAllowedUri: (url) => isSafeUrl(url, linkSchemes),
            },
        }),
        TextStyle,
        Color,
        BackgroundColor,
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        // Stored legacy posts may hold base64 raster images, which
        // HtmlSanitizer keeps; they round-trip, but the toolbar and paste
        // only ever add upload URLs.
        Image.configure({ inline: true, allowBase64: true }),
        TableKit,
        StoredAttributes,
    ],
    editorProps: editorProps(),
    onCreate: ({ editor: created }) => {
        hasContent.value = !created.isEmpty;
    },
    onTransaction: () => {
        revision.value++;
    },
    onUpdate: ({ editor: updated }) => {
        html.value = serialize(updated);
        hasContent.value = !updated.isEmpty;
        requiredError.value = undefined;
    },
});

watch(html, (value) => {
    if (editor.value && value !== serialize(editor.value)) {
        editor.value.commands.setContent(value, { emitUpdate: false });
        hasContent.value = !editor.value.isEmpty;
    }
});
watch(message, () => editor.value?.setOptions({ editorProps: editorProps() }));

/**
 * The editor, read so that the toolbar's computed state follows every
 * transaction at once: @tiptap/vue-3 only re-renders two animation frames
 * later, and in between Vue would reset the style select to a stale value.
 */
function liveEditor() {
    return revision.value >= 0 ? editor.value : undefined;
}

interface Tool {
    key: string;
    icon: Component;
    label: string;
    run: () => void;
    active?: boolean;
    disabled?: boolean;
}

const blockStyle = computed(() => {
    const current = liveEditor();
    const level = current?.getAttributes('heading').level;

    return current?.isActive('heading') ? String(level) : 'paragraph';
});
const textColor = computed(() =>
    hexColor(liveEditor()?.getAttributes('textStyle').color, '#000000'),
);
const highlightColor = computed(() =>
    hexColor(
        liveEditor()?.getAttributes('textStyle').backgroundColor,
        '#ffffff',
    ),
);
const inTable = computed(() => liveEditor()?.isActive('table') ?? false);
const inLink = computed(() => liveEditor()?.isActive('link') ?? false);

function hexColor(value: unknown, fallback: string): string {
    return typeof value === 'string' && /^#[0-9a-f]{6}$/i.test(value)
        ? value
        : fallback;
}

/**
 * A command chain that returns focus to the content, as a toolbar button
 * does. The style select and colour inputs pass `false`, so a keyboard user
 * stays on them while changing their value.
 */
function chain(focus = true) {
    const commands = editor.value!.chain();

    return focus ? commands.focus() : commands;
}

const formatTools = computed<Tool[]>(() => {
    const current = liveEditor();

    if (!current) {
        return [];
    }

    const mark = (key: string, icon: Component, run: () => void): Tool => ({
        key,
        icon,
        label: t(`store.editor.${key}`),
        active: current.isActive(key),
        run,
    });
    const align = (
        key: 'left' | 'center' | 'right' | 'justify',
        icon: Component,
    ): Tool => ({
        key,
        icon,
        label: t(`store.editor.${key}`),
        active: current.isActive({ textAlign: key }),
        run: () => chain().setTextAlign(key).run(),
    });

    return [
        mark('bold', Bold, () => chain().toggleBold().run()),
        mark('italic', Italic, () => chain().toggleItalic().run()),
        mark('underline', Underline, () => chain().toggleUnderline().run()),
        mark('strike', Strikethrough, () => chain().toggleStrike().run()),
        mark('bulletList', List, () => chain().toggleBulletList().run()),
        mark('orderedList', ListOrdered, () =>
            chain().toggleOrderedList().run(),
        ),
        mark('blockquote', Quote, () => chain().toggleBlockquote().run()),
        align('left', AlignLeft),
        align('center', AlignCenter),
        align('right', AlignRight),
        align('justify', AlignJustify),
    ];
});
const historyTools = computed<Tool[]>(() => {
    const current = liveEditor();

    return current
        ? [
              {
                  key: 'clear',
                  icon: RemoveFormatting,
                  label: t('store.editor.clear'),
                  run: () => chain().unsetAllMarks().clearNodes().run(),
              },
              {
                  key: 'undo',
                  icon: Undo2,
                  label: t('store.editor.undo'),
                  disabled: !current.can().undo(),
                  run: () => chain().undo().run(),
              },
              {
                  key: 'redo',
                  icon: Redo2,
                  label: t('store.editor.redo'),
                  disabled: !current.can().redo(),
                  run: () => chain().redo().run(),
              },
          ]
        : [];
});
const tableTools = computed<Tool[]>(() =>
    inTable.value
        ? [
              {
                  key: 'addRow',
                  icon: BetweenHorizontalEnd,
                  label: t('store.editor.addRow'),
                  run: () => chain().addRowAfter().run(),
              },
              {
                  key: 'addColumn',
                  icon: BetweenVerticalEnd,
                  label: t('store.editor.addColumn'),
                  run: () => chain().addColumnAfter().run(),
              },
              {
                  key: 'deleteRow',
                  icon: Trash2,
                  label: t('store.editor.deleteRow'),
                  run: () => chain().deleteRow().run(),
              },
              {
                  key: 'deleteColumn',
                  icon: Trash2,
                  label: t('store.editor.deleteColumn'),
                  run: () => chain().deleteColumn().run(),
              },
              {
                  key: 'deleteTable',
                  icon: Trash2,
                  label: t('store.editor.deleteTable'),
                  run: () => chain().deleteTable().run(),
              },
          ]
        : [],
);

/**
 * Runs a select's or colour input's command and keeps focus on that control:
 * the command re-syncs the editor's DOM selection, which in Chromium also
 * moves focus into the content.
 */
function applyFromControl(event: Event, command: (value: string) => void) {
    const control = event.target as HTMLInputElement | HTMLSelectElement;
    command(control.value);
    control.focus();
}

function setColor(value: string): void {
    chain(false).setColor(value).run();
}

function setBackgroundColor(value: string): void {
    chain(false).setBackgroundColor(value).run();
}

function setBlockStyle(value: string): void {
    if (value === 'paragraph') {
        chain(false).setParagraph().run();
    } else {
        chain(false)
            .setHeading({ level: Number(value) as 1 | 2 | 3 | 4 | 5 | 6 })
            .run();
    }
}

function openLink(): void {
    linkOpen.value = !linkOpen.value;
    linkError.value = undefined;
    linkUrl.value = editor.value?.getAttributes('link').href ?? '';

    if (linkOpen.value) {
        setTimeout(() => linkInput.value?.focus());
    }
}

function closeLink(): void {
    linkOpen.value = false;
    linkButton.value?.focus();
}

function applyLink(): void {
    const url = linkUrl.value.trim();

    if (url === '') {
        chain().extendMarkRange('link').unsetLink().run();
    } else if (isSafeUrl(url, linkSchemes)) {
        chain().extendMarkRange('link').setLink({ href: url }).run();
    } else {
        linkError.value = t('store.editor.invalidLink');

        return;
    }

    linkOpen.value = false;
}

function removeLink(): void {
    chain().extendMarkRange('link').unsetLink().run();
    linkOpen.value = false;
}

function openPicker(): void {
    draft.value = [];
    picker.value = true;
}

function insertImages(files: StoreUpload[]): void {
    for (const file of files) {
        if (
            file.type === 'image' &&
            file.url &&
            isSafeUrl(file.url, imageSchemes) &&
            !file.url.trim().toLowerCase().startsWith('data:')
        ) {
            chain()
                .setImage({ src: file.url, alt: file.name ?? '' })
                .run();
        }
    }
}

function onInvalid(): void {
    requiredError.value = t('store.editor.required');
    editor.value?.commands.focus();
}

/**
 * The toolbar is one tab stop (WAI-ARIA toolbar pattern): arrow keys, Home
 * and End move between its controls, mirrored in right-to-left pages.
 */
function toolbarItems(): HTMLElement[] {
    return Array.from(
        toolbar.value?.querySelectorAll<HTMLElement>(
            '[data-toolbar-item]:not(:disabled)',
        ) ?? [],
    );
}

function syncTabStops(): void {
    const items = toolbarItems();
    const current =
        items.find((item) => item.dataset.toolbarItem === tabStop) ?? items[0];
    toolbar.value
        ?.querySelectorAll<HTMLElement>('[data-toolbar-item]')
        .forEach((item) => {
            item.tabIndex = item === current ? 0 : -1;
        });
}

function focusToolbar(): void {
    syncTabStops();
    toolbar.value?.querySelector<HTMLElement>('[tabindex="0"]')?.focus();
}

function onToolbarKeydown(event: KeyboardEvent): void {
    const items = toolbarItems();
    const index = items.indexOf(event.target as HTMLElement);

    if (index === -1) {
        return;
    }

    const rtl = toolbar.value?.closest('[dir]')?.getAttribute('dir') === 'rtl';
    const next = {
        ArrowRight: rtl ? index - 1 : index + 1,
        ArrowLeft: rtl ? index + 1 : index - 1,
        Home: 0,
        End: items.length - 1,
    }[event.key];

    if (next === undefined) {
        return;
    }

    event.preventDefault();
    const target = items[(next + items.length) % items.length];
    tabStop = target.dataset.toolbarItem ?? tabStop;
    syncTabStops();
    target.focus();
}

function onToolbarFocusin(event: FocusEvent): void {
    const key = (event.target as HTMLElement).dataset.toolbarItem;

    if (key) {
        tabStop = key;
        syncTabStops();
    }
}

onMounted(syncTabStops);
onUpdated(syncTabStops);
</script>
<template>
    <div class="space-y-2">
        <label
            :id="`${id}-label`"
            :for="id"
            :class="fieldLabelClass"
            @click="editor?.commands.focus()"
            >{{ label }}<RequiredMark v-if="required"
        /></label>
        <div
            ref="toolbar"
            role="toolbar"
            :aria-label="t('store.editor.toolbar')"
            :aria-controls="id"
            class="flex flex-wrap items-center gap-1"
            @keydown="onToolbarKeydown"
            @focusin="onToolbarFocusin"
        >
            <select
                data-toolbar-item="blockStyle"
                :aria-label="t('store.editor.heading')"
                :value="blockStyle"
                class="cursor-pointer rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                @change="applyFromControl($event, setBlockStyle)"
            >
                <option value="paragraph">
                    {{ t('store.editor.paragraph') }}
                </option>
                <option v-for="level in 6" :key="level" :value="String(level)">
                    {{ t('store.editor.headingNumber', { n: level }) }}
                </option>
            </select>
            <button
                v-for="tool in formatTools"
                :key="tool.key"
                type="button"
                :data-toolbar-item="tool.key"
                :aria-label="tool.label"
                :title="tool.label"
                :aria-pressed="tool.active"
                :class="[
                    mapToolbarButtonClass,
                    tool.active ? selectedToggleClass : '',
                ]"
                @click="tool.run"
            >
                <component :is="tool.icon" class="h-4 w-4" />
            </button>
            <label
                :class="mapToolbarButtonClass"
                class="inline-flex items-center gap-1"
                :title="t('store.editor.color')"
            >
                <Baseline class="h-4 w-4" />
                <input
                    type="color"
                    data-toolbar-item="color"
                    :aria-label="t('store.editor.color')"
                    :value="textColor"
                    class="h-5 w-6 cursor-pointer"
                    @change="applyFromControl($event, setColor)"
                />
            </label>
            <label
                :class="mapToolbarButtonClass"
                class="inline-flex items-center gap-1"
                :title="t('store.editor.backgroundColor')"
            >
                <Highlighter class="h-4 w-4" />
                <input
                    type="color"
                    data-toolbar-item="backgroundColor"
                    :aria-label="t('store.editor.backgroundColor')"
                    :value="highlightColor"
                    class="h-5 w-6 cursor-pointer"
                    @change="applyFromControl($event, setBackgroundColor)"
                />
            </label>
            <button
                ref="linkButton"
                type="button"
                data-toolbar-item="link"
                :aria-label="t('store.editor.link')"
                :title="t('store.editor.link')"
                :aria-expanded="linkOpen"
                :aria-pressed="inLink"
                :class="[
                    mapToolbarButtonClass,
                    inLink ? selectedToggleClass : '',
                ]"
                @click="openLink"
            >
                <LinkIcon class="h-4 w-4" />
            </button>
            <button
                v-if="access.can('view_uploads')"
                type="button"
                data-toolbar-item="image"
                :aria-label="t('store.editor.image')"
                :title="t('store.editor.image')"
                aria-haspopup="dialog"
                :class="mapToolbarButtonClass"
                @click="openPicker"
            >
                <ImagePlus class="h-4 w-4" />
            </button>
            <button
                type="button"
                data-toolbar-item="table"
                :aria-label="t('store.editor.table')"
                :title="t('store.editor.table')"
                :disabled="inTable"
                :class="mapToolbarButtonClass"
                class="disabled:cursor-not-allowed disabled:opacity-50"
                @click="
                    chain()
                        .insertTable({ rows: 3, cols: 3, withHeaderRow: true })
                        .run()
                "
            >
                <Table class="h-4 w-4" />
            </button>
            <button
                v-for="tool in historyTools"
                :key="tool.key"
                type="button"
                :data-toolbar-item="tool.key"
                :aria-label="tool.label"
                :title="tool.label"
                :disabled="tool.disabled"
                :class="mapToolbarButtonClass"
                class="disabled:cursor-not-allowed disabled:opacity-50"
                @click="tool.run"
            >
                <component :is="tool.icon" class="h-4 w-4" />
            </button>
            <button
                v-for="tool in tableTools"
                :key="tool.key"
                type="button"
                :data-toolbar-item="tool.key"
                :class="mapToolbarButtonClass"
                class="inline-flex items-center gap-1 text-sm"
                @click="tool.run"
            >
                <component :is="tool.icon" class="h-4 w-4" />{{ tool.label }}
            </button>
        </div>
        <div
            v-if="linkOpen"
            class="flex flex-wrap items-end gap-2"
            @keydown.esc.prevent="closeLink"
        >
            <div class="grow">
                <label :for="`${id}-link`" :class="fieldLabelClass">{{
                    t('store.editor.linkUrl')
                }}</label>
                <input
                    :id="`${id}-link`"
                    ref="linkInput"
                    v-model="linkUrl"
                    type="text"
                    inputmode="url"
                    maxlength="2048"
                    :class="plainFieldInputClass"
                    :aria-invalid="linkError ? 'true' : undefined"
                    @keydown.enter.prevent="applyLink"
                />
                <FieldError :message="linkError" />
            </div>
            <button
                type="button"
                :class="mapToolbarButtonClass"
                @click="applyLink"
            >
                {{ t('store.editor.insertLink') }}
            </button>
            <button
                type="button"
                :class="mapToolbarButtonClass"
                @click="removeLink"
            >
                {{ t('store.editor.removeLink') }}
            </button>
        </div>
        <EditorContent :editor="editor" />
        <input type="hidden" :name="name" :value="html" />
        <textarea
            v-if="required"
            class="sr-only"
            tabindex="-1"
            aria-hidden="true"
            required
            :value="hasContent ? html : ''"
            @invalid.prevent="onInvalid"
        />
        <FieldError :id="`${id}-error`" :message="message" />
        <StoreFileManagerDialog
            v-model:open="picker"
            v-model:selected="draft"
            type="image"
            multiple
            @choose="insertImages"
        />
    </div>
</template>

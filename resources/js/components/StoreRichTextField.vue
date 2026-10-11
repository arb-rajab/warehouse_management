<script setup lang="ts">
import Image from '@tiptap/extension-image';
import { TableKit } from '@tiptap/extension-table';
import TextAlign from '@tiptap/extension-text-align';
import { TextStyleKit } from '@tiptap/extension-text-style';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { onBeforeUnmount, ref, watch, watchEffect } from 'vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import {
    fieldLabelClass,
    filterTriggerButtonClass,
    plainFieldInputClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';

const props = defineProps<{
    id: string;
    name: string;
    label: string;
    error?: string;
    required?: boolean;
    maxlength?: number;
}>();
const model = defineModel<string>({ default: '' });
const sourceMode = ref(false);
const source = ref(model.value);
const linkUrl = ref('');
const imageUrl = ref('');
const urlError = ref('');
const nativeError = ref('');
const validityInput = ref<HTMLTextAreaElement>();
// Contenteditable does not participate in native form validation. Keep a
// focusable proxy without a name; the named hidden input remains the payload.
watchEffect(() => {
    const error =
        props.required && !model.value.trim()
            ? t('store.richText.required')
            : props.maxlength !== undefined &&
                model.value.length > props.maxlength
              ? t('store.richText.tooLong')
              : '';
    validityInput.value?.setCustomValidity(error);

    if (!error) {
        nativeError.value = '';
    }
});
function onInvalid(): void {
    nativeError.value = validityInput.value?.validationMessage ?? '';

    if (sourceMode.value) {
        document.getElementById(`${props.id}-source`)?.focus();
    } else {
        editor.value?.commands.focus();
    }
}

/** Match the server's HTML allowlist before any content reaches the editor DOM. */
const allowedTags = new Set(
    'a abbr b blockquote br caption cite code col colgroup dd del div dl dt em figcaption figure h1 h2 h3 h4 h5 h6 hr i img ins li mark ol p pre q s small span strike strong sub sup table tbody td tfoot th thead tr u ul'.split(
        ' ',
    ),
);
const droppedTags = new Set(
    'applet audio base button canvas dialog embed form frame frameset head iframe input link math meta noembed noframes noscript object optgroup option plaintext script select source style svg template textarea title track video xmp'.split(
        ' ',
    ),
);
const cssProperties = new Set(
    'background-color border border-bottom border-collapse border-color border-left border-right border-style border-top border-width color font-family font-size font-style font-weight height letter-spacing line-height list-style-type margin margin-bottom margin-left margin-right margin-top max-width padding padding-bottom padding-left padding-right padding-top text-align text-decoration text-indent vertical-align width'.split(
        ' ',
    ),
);
const tagAttributes: Record<string, string[]> = {
    a: ['href', 'target'],
    col: ['span', 'width'],
    colgroup: ['span'],
    img: ['alt', 'height', 'src', 'width'],
    ol: ['start', 'type'],
    td: ['align', 'colspan', 'rowspan', 'valign'],
    th: ['align', 'colspan', 'rowspan', 'scope', 'valign'],
};

function safeUrl(value: string, image = false): string | null {
    // Browser URL parsers discard controls and whitespace inside schemes.
    const compact = [...value]
        .filter((character) => {
            const code = character.charCodeAt(0);

            return code > 32 && !(code >= 127 && code <= 159);
        })
        .join('');

    if (!compact) {
        return null;
    }

    const scheme = compact.match(/^([a-z][a-z0-9+.\-]*):/i)?.[1]?.toLowerCase();

    if (!scheme) {
        return value.trim();
    }

    if (image && scheme === 'data') {
        return /^data:image\/(png|jpe?g|gif|webp);base64,[a-z0-9+/=]+$/i.test(
            compact,
        )
            ? compact
            : null;
    }

    return (image
        ? ['http', 'https']
        : ['http', 'https', 'mailto', 'tel']
    ).includes(scheme)
        ? value.trim()
        : null;
}

function cleanStyle(style: string): string {
    return style
        .split(';')
        .flatMap((declaration) => {
            const colon = declaration.indexOf(':');
            const property = declaration.slice(0, colon).trim().toLowerCase();
            const value = declaration.slice(colon + 1).trim();
            const plain =
                property === 'font-family'
                    ? /^[a-z0-9\s,\-'"\u0600-\u06ff]+$/i.test(value)
                    : /^[a-z0-9\s#%.,()+\-]+$/i.test(value);

            return colon >= 0 &&
                cssProperties.has(property) &&
                value &&
                plain &&
                !/url\s*\(|expression|javascript|import|behavior|binding/i.test(
                    value,
                )
                ? [`${property}: ${value}`]
                : [];
        })
        .join('; ');
}

function cleanHtml(value: string): string {
    if (typeof DOMParser === 'undefined') {
        return '';
    }

    const document = new DOMParser().parseFromString(value, 'text/html');
    function clean(parent: Node): void {
        for (const node of [...parent.childNodes]) {
            if (node.nodeType === Node.TEXT_NODE) {
                continue;
            }

            if (!(node instanceof Element)) {
                parent.removeChild(node);
                continue;
            }

            const tag = node.localName.toLowerCase();

            if (
                node.namespaceURI !== 'http://www.w3.org/1999/xhtml' ||
                droppedTags.has(tag)
            ) {
                node.remove();
                continue;
            }

            clean(node);

            if (!allowedTags.has(tag)) {
                node.replaceWith(...node.childNodes);
                continue;
            }

            const allowed = new Set([
                'class',
                'dir',
                'lang',
                'style',
                'title',
                ...(tagAttributes[tag] ?? []),
            ]);

            for (const attribute of [...node.attributes]) {
                const name = attribute.name.toLowerCase();

                if (!allowed.has(name)) {
                    node.removeAttribute(attribute.name);
                    continue;
                }

                const checked =
                    name === 'style'
                        ? cleanStyle(attribute.value)
                        : name === 'href'
                          ? safeUrl(attribute.value)
                          : name === 'src'
                            ? safeUrl(attribute.value, true)
                            : name === 'target'
                              ? attribute.value === '_blank'
                                  ? '_blank'
                                  : null
                              : attribute.value;

                if (checked === null || checked === '') {
                    node.removeAttribute(attribute.name);
                } else {
                    node.setAttribute(attribute.name, checked);
                }
            }

            if (tag === 'a') {
                node.setAttribute('rel', 'noopener noreferrer nofollow');
            }
        }
    }
    clean(document.body);

    return document.body.innerHTML;
}

/** Keep safe legacy formatting attributes as users edit the visual document. */
const legacyFormatting = StarterKit.extend({
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
                    'codeBlock',
                    'horizontalRule',
                    'image',
                    'table',
                    'tableRow',
                    'tableCell',
                    'tableHeader',
                    'textStyle',
                    'bold',
                    'italic',
                    'underline',
                    'strike',
                    'link',
                ],
                attributes: Object.fromEntries(
                    ['class', 'dir', 'lang', 'style', 'title'].map(
                        (attribute) => [
                            attribute,
                            {
                                default: null,
                                parseHTML: (element: HTMLElement) =>
                                    element.getAttribute(attribute),
                            },
                        ],
                    ),
                ),
            },
        ];
    },
});
const editor = useEditor({
    content: cleanHtml(model.value),
    extensions: [
        legacyFormatting.configure({
            link: {
                openOnClick: false,
                isAllowedUri: (value) => safeUrl(value) !== null,
            },
        }),
        Image.configure({ allowBase64: true }),
        TableKit.configure({ table: { resizable: false } }),
        TextStyleKit,
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
    ],
    editorProps: {
        attributes: {
            id: `${props.id}-editor`,
            role: 'textbox',
            'aria-multiline': 'true',
            'aria-labelledby': `${props.id}-label`,
            class: 'min-h-48 p-3 text-start outline-none',
        },
        transformPastedHTML: cleanHtml,
    },
    onCreate: () => {
        model.value = cleanHtml(model.value);
        source.value = model.value;
    },
    onUpdate: ({ editor }) => {
        model.value = cleanHtml(editor.getHTML());
        source.value = model.value;
    },
});
watch(model, (value) => {
    const safe = cleanHtml(value);

    if (safe !== value) {
        model.value = safe;

        return;
    }

    if (safe !== editor.value?.getHTML()) {
        editor.value?.commands.setContent(safe, { emitUpdate: false });
    }

    if (!sourceMode.value) {
        source.value = safe;
    }
});
onBeforeUnmount(() => editor.value?.destroy());

function updateSource(value: string): void {
    source.value = value;
    model.value = cleanHtml(value);
}
function insertLink(): void {
    const url = safeUrl(linkUrl.value);

    if (!url) {
        urlError.value = t('store.richText.invalidUrl');

        return;
    }

    editor.value
        ?.chain()
        .focus()
        .extendMarkRange('link')
        .setLink({ href: url })
        .run();
    urlError.value = '';
}
function insertImage(): void {
    const url = safeUrl(imageUrl.value, true);

    if (!url) {
        urlError.value = t('store.richText.invalidUrl');

        return;
    }

    editor.value?.chain().focus().setImage({ src: url }).run();
    urlError.value = '';
}
</script>

<template>
    <div class="space-y-2">
        <label
            :id="`${id}-label`"
            :for="`${id}-editor`"
            :class="fieldLabelClass"
            >{{ label }}</label
        >
        <input type="hidden" :name="name" :value="model" />
        <textarea
            ref="validityInput"
            :value="model"
            :required="required"
            :maxlength="maxlength"
            :aria-label="label"
            tabindex="-1"
            class="sr-only"
            @invalid.prevent="onInvalid"
        />
        <div
            v-if="editor && !sourceMode"
            class="flex flex-wrap gap-1"
            role="toolbar"
            :aria-label="t('store.richText.toolbar')"
        >
            <button
                type="button"
                :class="filterTriggerButtonClass"
                :aria-pressed="editor.isActive('bold')"
                @click="editor.chain().focus().toggleBold().run()"
            >
                {{ t('store.richText.bold') }}
            </button>
            <button
                type="button"
                :class="filterTriggerButtonClass"
                :aria-pressed="editor.isActive('italic')"
                @click="editor.chain().focus().toggleItalic().run()"
            >
                {{ t('store.richText.italic') }}
            </button>
            <button
                type="button"
                :class="filterTriggerButtonClass"
                :aria-pressed="editor.isActive('underline')"
                @click="editor.chain().focus().toggleUnderline().run()"
            >
                {{ t('store.richText.underline') }}
            </button>
            <button
                type="button"
                :class="filterTriggerButtonClass"
                @click="editor.chain().focus().setParagraph().run()"
            >
                {{ t('store.richText.paragraph') }}
            </button>
            <button
                type="button"
                :class="filterTriggerButtonClass"
                @click="
                    editor.chain().focus().toggleHeading({ level: 2 }).run()
                "
            >
                {{ t('store.richText.heading') }}
            </button>
            <button
                type="button"
                :class="filterTriggerButtonClass"
                @click="editor.chain().focus().toggleBulletList().run()"
            >
                {{ t('store.richText.bulletList') }}
            </button>
            <button
                type="button"
                :class="filterTriggerButtonClass"
                @click="editor.chain().focus().toggleOrderedList().run()"
            >
                {{ t('store.richText.orderedList') }}
            </button>
            <button
                type="button"
                :class="filterTriggerButtonClass"
                @click="editor.chain().focus().toggleBlockquote().run()"
            >
                {{ t('store.richText.quote') }}
            </button>
            <button
                type="button"
                :class="filterTriggerButtonClass"
                @click="
                    editor
                        .chain()
                        .focus()
                        .insertTable({ rows: 3, cols: 3, withHeaderRow: true })
                        .run()
                "
            >
                {{ t('store.richText.table') }}
            </button>
            <button
                type="button"
                :class="filterTriggerButtonClass"
                @click="
                    editor.chain().focus().unsetAllMarks().clearNodes().run()
                "
            >
                {{ t('store.richText.clear') }}
            </button>
            <button
                type="button"
                :class="filterTriggerButtonClass"
                @click="editor.chain().focus().undo().run()"
            >
                {{ t('store.richText.undo') }}
            </button>
            <button
                type="button"
                :class="filterTriggerButtonClass"
                @click="editor.chain().focus().redo().run()"
            >
                {{ t('store.richText.redo') }}
            </button>
        </div>
        <button
            type="button"
            :class="filterTriggerButtonClass"
            :aria-pressed="sourceMode"
            @click="
                sourceMode = !sourceMode;
                source = model;
            "
        >
            {{
                t(
                    sourceMode
                        ? 'store.richText.visual'
                        : 'store.richText.source',
                )
            }}
        </button>
        <textarea
            v-if="sourceMode"
            :id="`${id}-source`"
            :value="source"
            :aria-label="t('store.richText.source')"
            rows="10"
            :class="plainFieldInputClass"
            @input="updateSource(($event.target as HTMLTextAreaElement).value)"
        />
        <EditorContent
            v-else
            :editor="editor"
            class="overflow-x-auto rounded-md border border-gray-300 bg-white text-gray-900 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100"
        />
        <div v-if="!sourceMode" class="grid gap-2 sm:grid-cols-2">
            <div class="space-y-1">
                <FormField
                    :id="`${id}-link`"
                    :label="t('store.richText.linkUrl')"
                    @input="linkUrl = ($event.target as HTMLInputElement).value"
                />
                <button
                    type="button"
                    :class="filterTriggerButtonClass"
                    @click="insertLink"
                >
                    {{ t('store.richText.addLink') }}
                </button>
            </div>
            <div class="space-y-1">
                <FormField
                    :id="`${id}-image`"
                    :label="t('store.richText.imageUrl')"
                    @input="
                        imageUrl = ($event.target as HTMLInputElement).value
                    "
                />
                <button
                    type="button"
                    :class="filterTriggerButtonClass"
                    @click="insertImage"
                >
                    {{ t('store.richText.addImage') }}
                </button>
            </div>
        </div>
        <FieldError :message="urlError" />
        <FieldError :message="nativeError" />
        <FieldError :message="error" />
    </div>
</template>

<style scoped>
:deep(.tiptap p) {
    margin-block: 0.5rem;
}
:deep(.tiptap h1),
:deep(.tiptap h2),
:deep(.tiptap h3),
:deep(.tiptap h4),
:deep(.tiptap h5),
:deep(.tiptap h6) {
    font-weight: 700;
    font-size: 1.3em;
    margin-block: 0.75rem;
}
:deep(.tiptap ul) {
    list-style-type: disc;
    padding-inline-start: 1.5rem;
}
:deep(.tiptap ol) {
    list-style-type: decimal;
    padding-inline-start: 1.5rem;
}
:deep(.tiptap blockquote) {
    border-inline-start: 3px solid currentColor;
    padding-inline-start: 1rem;
}
:deep(.tiptap table) {
    border-collapse: collapse;
    width: 100%;
}
:deep(.tiptap th),
:deep(.tiptap td) {
    border: 1px solid currentColor;
    padding: 0.5rem;
}
:deep(.tiptap a) {
    text-decoration: underline;
}
:deep(.tiptap img) {
    max-width: 100%;
    height: auto;
}
</style>

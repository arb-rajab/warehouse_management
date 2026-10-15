import type { Editor } from '@tiptap/vue-3';
import { EditorContent } from '@tiptap/vue-3';
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import FieldError from '@/components/FieldError.vue';
import StoreFileManagerDialog from '@/components/StoreFileManagerDialog.vue';
import { i18n, t } from '@/lib/i18n';
import { paginated, storeSharedProps, storeUpload } from '@/testing/factories';
import { createUseHttpStub } from '@/testing/inertiaStubs';
import StoreRichTextField from './StoreRichTextField.vue';

const { usePageMock, useHttpMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    useHttpMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, createFormStub } =
        await import('@/testing/inertiaStubs');

    return {
        usePage: usePageMock,
        useHttp: useHttpMock,
        Link: createLinkStub(),
        Head: { template: '<span />' },
        Form: createFormStub(() => ({ errors: {}, processing: false })),
        router: { post: vi.fn() },
    };
});

/** Stored legacy (Blade editor) markup as HtmlSanitizer returns it. */
const legacyHtml =
    '<h2>Fresh <b>rice</b></h2><p style="text-align: center">Red and <span style="background-color: rgb(255, 255, 0)">marked</span></p>' +
    '<p style="margin-left: 25px">Indented <u>under</u> <i>italic</i></p><ul><li>One</li></ul><ol><li>First</li></ol><blockquote>Quote</blockquote><pre>code</pre>' +
    '<table class="table table-bordered"><tbody><tr><td>A</td><td><br></td></tr></tbody></table>' +
    '<p><a href="https://example.com/rice" target="_blank" rel="noopener noreferrer nofollow">Link</a></p><p><img src="https://example.com/uploads/all/legacy.png" style="width: 50%"></p>' +
    '<p dir="rtl" lang="ar">أرز</p><div class="embed-responsive embed-responsive-16by9"></div>';

const wrappers: VueWrapper[] = [];

function mountField(
    props: Record<string, unknown> = {},
    permissions = ['view_uploads'],
    attachTo?: HTMLElement,
) {
    usePageMock.mockReturnValue({
        props: { store: storeSharedProps({ permissions }) },
    });
    const wrapper = mount(StoreRichTextField, {
        props: {
            id: 'description',
            name: 'description',
            label: 'Content',
            modelValue: '<p>Hello</p>',
            'onUpdate:modelValue': (value: string) =>
                wrapper.setProps({ modelValue: value }),
            ...props,
        },
        attachTo,
    });
    wrappers.push(wrapper);

    return wrapper;
}

/**
 * Waits for @tiptap/vue-3's state, which re-renders the toolbar two
 * animation frames after a transaction.
 */
async function settle(): Promise<void> {
    for (let frame = 0; frame < 2; frame++) {
        await new Promise((resolve) => requestAnimationFrame(resolve));
    }

    await flushPromises();
}

function editorOf(wrapper: VueWrapper): Editor {
    const editor = wrapper.getComponent(EditorContent).props('editor');

    if (!editor) {
        throw new Error('The editor must mount');
    }

    return editor as Editor;
}

function lastHtml(wrapper: VueWrapper): string {
    return wrapper.emitted('update:modelValue')!.at(-1)![0] as string;
}

function postedValue(wrapper: VueWrapper): string {
    return (
        wrapper.get('input[type="hidden"][name="description"]')
            .element as HTMLInputElement
    ).value;
}

function toolbarButton(wrapper: VueWrapper, key: string) {
    return wrapper.get(`[data-toolbar-item="${key}"]`);
}

const buttonKeys = [
    'bold',
    'italic',
    'underline',
    'strike',
    'bulletList',
    'orderedList',
    'blockquote',
    'left',
    'center',
    'right',
    'justify',
    'link',
    'image',
    'table',
    'clear',
    'undo',
    'redo',
];

beforeEach(() => {
    vi.clearAllMocks();
    useHttpMock.mockImplementation((data) =>
        createUseHttpStub(data, paginated([storeUpload()])),
    );
    // jsdom has no ClipboardEvent, which ProseMirror's pasteHTML() creates.
    vi.stubGlobal(
        'ClipboardEvent',
        class extends Event {
            clipboardData = null;
        },
    );
    i18n.global.locale.value = 'en';
});
afterEach(() => {
    vi.unstubAllGlobals();
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    document.body.innerHTML = '';
    i18n.global.locale.value = 'en';
});

describe('StoreRichTextField', () => {
    it.each(['en', 'ar'] as const)(
        'labels every control in %s',
        async (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountField({ label: 'المحتوى' });
            await flushPromises();
            expect(wrapper.get('#description-label').text()).toBe('المحتوى');
            expect(wrapper.get('[role="toolbar"]').attributes()).toMatchObject({
                'aria-label': t('store.editor.toolbar'),
                'aria-controls': 'description',
            });
            const select = wrapper.get('select');
            expect(select.attributes('aria-label')).toBe(
                t('store.editor.heading'),
            );
            expect(select.findAll('option').map((o) => o.text())).toEqual([
                t('store.editor.paragraph'),
                ...[1, 2, 3, 4, 5, 6].map((n) =>
                    t('store.editor.headingNumber', { n }),
                ),
            ]);

            for (const key of buttonKeys) {
                expect(
                    toolbarButton(wrapper, key).attributes('aria-label'),
                ).toBe(t(`store.editor.${key}`));
            }

            for (const key of ['color', 'backgroundColor']) {
                expect(toolbarButton(wrapper, key).attributes()).toMatchObject({
                    type: 'color',
                    'aria-label': t(`store.editor.${key}`),
                });
            }

            editorOf(wrapper)
                .chain()
                .insertTable({ rows: 2, cols: 2, withHeaderRow: true })
                .run();
            await settle();

            for (const key of [
                'addRow',
                'addColumn',
                'deleteRow',
                'deleteColumn',
                'deleteTable',
            ]) {
                expect(toolbarButton(wrapper, key).text()).toBe(
                    t(`store.editor.${key}`),
                );
            }

            await toolbarButton(wrapper, 'link').trigger('click');
            expect(wrapper.get('label[for="description-link"]').text()).toBe(
                t('store.editor.linkUrl'),
            );
            expect(wrapper.text()).toContain(t('store.editor.insertLink'));
            expect(wrapper.text()).toContain(t('store.editor.removeLink'));
        },
    );

    it('posts stored HTML unchanged until edited, then posts the editor HTML under its name', async () => {
        const wrapper = mountField({ modelValue: legacyHtml });
        await flushPromises();
        expect(
            wrapper.findAll('[name]').map((el) => el.attributes('name')),
        ).toEqual(['description']);
        expect(postedValue(wrapper)).toBe(legacyHtml);
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();

        editorOf(wrapper).chain().selectAll().toggleBold().run();
        await flushPromises();
        expect(lastHtml(wrapper)).toContain('<strong>');
        expect(postedValue(wrapper)).toBe(lastHtml(wrapper));

        editorOf(wrapper).commands.clearContent(true);
        await flushPromises();
        expect(lastHtml(wrapper)).toBe('');
        expect(postedValue(wrapper)).toBe('');
    });

    it('keeps legacy formatting, tables, links, images and direction when legacy content is edited', async () => {
        const wrapper = mountField({ modelValue: legacyHtml });
        await flushPromises();
        editorOf(wrapper).chain().focus('end').insertContent(' edited').run();
        await flushPromises();
        const html = lastHtml(wrapper);

        for (const kept of [
            '<h2>Fresh <strong>rice</strong></h2>',
            '<p style="text-align: center;">Red and <span style="background-color: rgb(255, 255, 0);">marked</span></p>',
            '<p style="margin-left: 25px;">Indented <u>under</u> <em>italic</em></p>',
            '<ul><li><p>One</p></li></ul>',
            '<ol><li><p>First</p></li></ol>',
            '<blockquote><p>Quote</p></blockquote>',
            '<pre><code>code</code></pre>',
            '<table class="table table-bordered"',
            '<td colspan="1" rowspan="1"><p>A</p></td>',
            '<a target="_blank" rel="noopener noreferrer nofollow" href="https://example.com/rice">Link</a>',
            '<img style="width: 50%;" src="https://example.com/uploads/all/legacy.png">',
            '<p dir="rtl" lang="ar">أرز edited</p>',
        ]) {
            expect(html).toContain(kept);
        }
    });

    it('formats through the real editor commands, including alignment, colours and table tools', async () => {
        const wrapper = mountField();
        await flushPromises();
        const editor = editorOf(wrapper);
        editor.commands.setTextSelection({ from: 1, to: 6 });
        const pressed = ['bold', 'italic', 'underline', 'strike', 'center'];

        for (const key of pressed) {
            await toolbarButton(wrapper, key).trigger('click');
        }

        await settle();
        // ProseMirror writes styles through the CSSOM, which normalises them.
        expect(lastHtml(wrapper)).toBe(
            '<p style="text-align: center;"><strong><em><s><u>Hello</u></s></em></strong></p>',
        );

        for (const key of pressed) {
            expect(toolbarButton(wrapper, key).attributes('aria-pressed')).toBe(
                'true',
            );
        }

        expect(toolbarButton(wrapper, 'left').attributes('aria-pressed')).toBe(
            'false',
        );
        await toolbarButton(wrapper, 'color').setValue('#c00000');
        await toolbarButton(wrapper, 'backgroundColor').setValue('#ffff00');
        expect(lastHtml(wrapper)).toContain(
            '<span style="color: rgb(192, 0, 0); background-color: rgb(255, 255, 0);">',
        );
        const select = wrapper.get('select');
        await select.setValue('2');
        expect(lastHtml(wrapper)).toMatch(/^<h2 style="text-align: center;">/);
        await settle();
        expect((select.element as HTMLSelectElement).value).toBe('2');
        await select.setValue('paragraph');
        expect(lastHtml(wrapper)).toMatch(/^<p style="text-align: center;">/);
        await toolbarButton(wrapper, 'bulletList').trigger('click');
        expect(lastHtml(wrapper)).toMatch(/^<ul><li><p/);
        await toolbarButton(wrapper, 'orderedList').trigger('click');
        expect(lastHtml(wrapper)).toMatch(/^<ol><li><p/);
        await toolbarButton(wrapper, 'orderedList').trigger('click');
        await toolbarButton(wrapper, 'blockquote').trigger('click');
        expect(lastHtml(wrapper)).toMatch(/^<blockquote><p/);
        await settle();
        expect(toolbarButton(wrapper, 'redo').attributes()).toHaveProperty(
            'disabled',
        );
        await toolbarButton(wrapper, 'undo').trigger('click');
        await settle();
        expect(toolbarButton(wrapper, 'redo').attributes()).not.toHaveProperty(
            'disabled',
        );
        editor.commands.selectAll();
        await toolbarButton(wrapper, 'clear').trigger('click');
        expect(lastHtml(wrapper)).not.toMatch(
            /<strong|<em|<s>|<u>|<span|<ul|<ol|<blockquote|text-align/,
        );

        editor.commands.setContent('<p>Hello</p>');
        await toolbarButton(wrapper, 'table').trigger('click');
        await settle();
        expect(lastHtml(wrapper)).toContain('<th colspan="1" rowspan="1">');
        expect(toolbarButton(wrapper, 'table').attributes()).toHaveProperty(
            'disabled',
        );
        await toolbarButton(wrapper, 'addRow').trigger('click');
        await toolbarButton(wrapper, 'addColumn').trigger('click');
        expect(lastHtml(wrapper).match(/<tr>/g)).toHaveLength(4);
        expect(lastHtml(wrapper).match(/<col /g)).toHaveLength(4);
        await toolbarButton(wrapper, 'deleteRow').trigger('click');
        await toolbarButton(wrapper, 'deleteColumn').trigger('click');
        expect(lastHtml(wrapper).match(/<tr>/g)).toHaveLength(3);
        expect(lastHtml(wrapper).match(/<col /g)).toHaveLength(3);
        await toolbarButton(wrapper, 'deleteTable').trigger('click');
        await settle();
        expect(lastHtml(wrapper)).not.toContain('<table');
        expect(wrapper.find('[data-toolbar-item="addRow"]').exists()).toBe(
            false,
        );
    });

    it('inserts only safe links through its panel', async () => {
        const wrapper = mountField();
        await flushPromises();
        editorOf(wrapper).commands.setTextSelection({ from: 1, to: 6 });
        await toolbarButton(wrapper, 'link').trigger('click');
        expect(toolbarButton(wrapper, 'link').attributes('aria-expanded')).toBe(
            'true',
        );
        const input = wrapper.get('#description-link');
        expect(input.attributes()).toMatchObject({
            type: 'text',
            maxlength: '2048',
        });
        expect(input.attributes('name')).toBeUndefined();
        await input.setValue('java\tscript:alert(1)');
        await input.trigger('keydown', { key: 'Enter' });
        expect(wrapper.getComponent(FieldError).props('message')).toBe(
            t('store.editor.invalidLink'),
        );
        expect(input.attributes('aria-invalid')).toBe('true');
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
        await input.setValue('https://example.com/rice');
        await input.trigger('keydown', { key: 'Enter' });
        expect(lastHtml(wrapper)).toBe(
            '<p><a target="_blank" rel="noopener noreferrer nofollow" href="https://example.com/rice">Hello</a></p>',
        );
        expect(wrapper.find('#description-link').exists()).toBe(false);
        editorOf(wrapper).commands.setTextSelection(3);
        await settle();
        await toolbarButton(wrapper, 'link').trigger('click');
        expect(
            (wrapper.get('#description-link').element as HTMLInputElement)
                .value,
        ).toBe('https://example.com/rice');
        await wrapper
            .findAll('button')
            .find((b) => b.text() === t('store.editor.removeLink'))!
            .trigger('click');
        expect(lastHtml(wrapper)).toBe('<p>Hello</p>');
    });

    it('inserts images only through the canonical image picker, never base64 or unsafe URLs', async () => {
        const wrapper = mountField();
        await flushPromises();
        const dialog = wrapper.getComponent(StoreFileManagerDialog);
        expect(dialog.props()).toMatchObject({
            open: false,
            type: 'image',
            multiple: true,
            selected: [],
        });
        await toolbarButton(wrapper, 'image').trigger('click');
        expect(dialog.props('open')).toBe(true);
        expect(
            toolbarButton(wrapper, 'image').attributes('aria-haspopup'),
        ).toBe('dialog');
        editorOf(wrapper).commands.setTextSelection(6);
        dialog.vm.$emit('choose', [
            storeUpload({
                url: 'https://store.example/uploads/all/rice.webp',
                name: 'rice.webp',
            }),
            storeUpload({ id: 2, type: 'document', url: '/doc.pdf' }),
            storeUpload({ id: 3, url: 'data:image/png;base64,AAAA' }),
            storeUpload({ id: 4, url: 'javascript:alert(1)' }),
            storeUpload({ id: 5, url: null }),
        ]);
        await flushPromises();
        expect(lastHtml(wrapper)).toBe(
            '<p>Hello<img src="https://store.example/uploads/all/rice.webp" alt="rice.webp"></p>',
        );
    });

    it('offers no image picker button without the uploads permission', async () => {
        const wrapper = mountField({}, []);
        await flushPromises();
        expect(wrapper.find('[data-toolbar-item="image"]').exists()).toBe(
            false,
        );
    });

    it('drops base64 images, classes and unmanaged styles from pasted HTML', async () => {
        const wrapper = mountField();
        await flushPromises();
        editorOf(wrapper).commands.setTextSelection(6);
        editorOf(wrapper).view.pasteHTML(
            '<p><span style="color: #c00000; font-size: 40px" class="fixed">Pasted</span></p>' +
                '<p class="fixed inset-0" style="position: fixed; text-align: right">Right</p>' +
                '<p><img src="data:image/png;base64,AAAA"><img src="javascript:alert(1)"><img src="https://example.com/a.png" onerror="alert(1)"></p>',
        );
        await flushPromises();
        const html = lastHtml(wrapper);
        expect(html).toContain(
            '<p>Hello<span style="color: rgb(192, 0, 0);">Pasted</span></p>',
        );
        expect(html).toContain('<p style="text-align: right;">Right</p>');
        expect(html).toContain('<img src="https://example.com/a.png">');
        expect(html).not.toMatch(
            /data:|javascript|onerror|fixed|inset|font-size|class=/,
        );
    });

    it('validates a required field and exposes errors to assistive technology', async () => {
        const wrapper = mountField({ modelValue: '', required: true });
        await flushPromises();
        const content = wrapper.get('#description');
        expect(content.attributes()).toMatchObject({
            role: 'textbox',
            'aria-multiline': 'true',
            'aria-labelledby': 'description-label',
            'aria-required': 'true',
        });
        expect(content.attributes('aria-invalid')).toBeUndefined();
        const proxy = wrapper.get('textarea');
        expect(proxy.attributes()).toMatchObject({
            required: '',
            tabindex: '-1',
            'aria-hidden': 'true',
        });
        expect(proxy.attributes('name')).toBeUndefined();
        expect(
            (proxy.element as HTMLTextAreaElement).validity.valueMissing,
        ).toBe(true);
        await proxy.trigger('invalid');
        await flushPromises();
        expect(wrapper.get('#description-error').text()).toBe(
            t('store.editor.required'),
        );
        expect(content.attributes()).toMatchObject({
            'aria-invalid': 'true',
            'aria-describedby': 'description-error',
        });
        editorOf(wrapper).commands.insertContent('Written');
        await flushPromises();
        expect(wrapper.find('#description-error').exists()).toBe(false);
        expect(
            (proxy.element as HTMLTextAreaElement).validity.valueMissing,
        ).toBe(false);

        await wrapper.setProps({ error: 'The content is invalid.' });
        await flushPromises();
        expect(wrapper.get('#description-error').text()).toBe(
            'The content is invalid.',
        );
        expect(content.attributes('aria-invalid')).toBe('true');
        expect(content.classes()).toContain('border-red-600');
    });

    it('posts an empty table it would otherwise lose, while a required field still counts it as missing', async () => {
        const wrapper = mountField({ modelValue: '', required: true });
        await flushPromises();
        await toolbarButton(wrapper, 'table').trigger('click');
        await flushPromises();
        expect(postedValue(wrapper)).toMatch(/^<table/);
        const proxy = wrapper.get('textarea').element as HTMLTextAreaElement;
        expect(proxy.validity.valueMissing).toBe(true);
        editorOf(wrapper).commands.insertContent('Cell');
        await flushPromises();
        expect(proxy.validity.valueMissing).toBe(false);
    });

    it('has no validation proxy when the field is optional', async () => {
        const wrapper = mountField({ modelValue: '' });
        await flushPromises();
        expect(wrapper.find('textarea').exists()).toBe(false);
        expect(wrapper.get('#description').attributes()).not.toHaveProperty(
            'aria-required',
        );
    });

    it.each([
        ['ltr', 'ArrowRight', 'ArrowLeft'],
        ['rtl', 'ArrowLeft', 'ArrowRight'],
    ])(
        'is a single tab stop moved by the arrow keys in %s',
        async (dir, next, previous) => {
            const host = document.createElement('div');
            host.setAttribute('dir', dir);
            document.body.append(host);
            const wrapper = mountField({}, ['view_uploads'], host);
            await flushPromises();
            const tabStops = () =>
                wrapper
                    .findAll('[data-toolbar-item]')
                    .filter((item) => item.attributes('tabindex') === '0')
                    .map((item) => item.attributes('data-toolbar-item'));
            expect(tabStops()).toEqual(['blockStyle']);
            const select = wrapper.get('select').element as HTMLSelectElement;
            select.focus();
            await wrapper.get('select').trigger('keydown', { key: next });
            expect(document.activeElement).toBe(
                toolbarButton(wrapper, 'bold').element,
            );
            expect(tabStops()).toEqual(['bold']);
            await toolbarButton(wrapper, 'bold').trigger('keydown', {
                key: previous,
            });
            expect(document.activeElement).toBe(select);
            // Undo and redo are disabled without history, so End skips them.
            await wrapper.get('select').trigger('keydown', { key: 'End' });
            expect(
                (document.activeElement as HTMLElement).dataset.toolbarItem,
            ).toBe('clear');
            await toolbarButton(wrapper, 'clear').trigger('keydown', {
                key: 'Home',
            });
            expect(document.activeElement).toBe(select);
        },
    );

    it('keeps focus on the style select and colour inputs while their value changes', async () => {
        const wrapper = mountField({}, ['view_uploads'], document.body);
        await flushPromises();
        editorOf(wrapper).commands.setTextSelection({ from: 1, to: 6 });

        for (const [key, value] of [
            ['blockStyle', '3'],
            ['color', '#c00000'],
            ['backgroundColor', '#ffff00'],
        ]) {
            const control = toolbarButton(wrapper, key);
            (control.element as HTMLElement).focus();
            await control.setValue(value);
            expect(document.activeElement).toBe(control.element);
        }

        expect(lastHtml(wrapper)).toMatch(
            /^<h3><span style="color: rgb\(192, 0, 0\); background-color: rgb\(255, 255, 0\);">Hello<\/span><\/h3><p><\/p>$/,
        );
    });

    it('moves focus from the content to the toolbar with Alt+F10', async () => {
        const wrapper = mountField({}, ['view_uploads'], document.body);
        await flushPromises();
        const content = wrapper.get('#description');
        (content.element as HTMLElement).focus();
        // A selection the browser has not reported yet is taken along.
        const text = content.element.querySelector('p')!.firstChild!;
        document.getSelection()!.setBaseAndExtent(text, 0, text, 5);
        await content.trigger('keydown', { key: 'F10', altKey: true });
        expect(document.activeElement).toBe(wrapper.get('select').element);
        expect(editorOf(wrapper).state.selection).toMatchObject({
            from: 1,
            to: 6,
        });
    });

    it('follows a replaced model value', async () => {
        const wrapper = mountField();
        await flushPromises();
        await wrapper.setProps({ modelValue: '<p>Replaced</p>' });
        expect(wrapper.get('.ProseMirror').text()).toBe('Replaced');
    });
});

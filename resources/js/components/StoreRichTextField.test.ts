import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import { i18n, t } from '@/lib/i18n';
import StoreRichTextField from './StoreRichTextField.vue';

const wrappers: ReturnType<typeof mount>[] = [];
async function mountEditor(props: Record<string, unknown> = {}) {
    const wrapper = mount(StoreRichTextField, {
        props: {
            id: 'content',
            name: 'description',
            label: 'Content',
            ...props,
        },
        attachTo: document.body,
    });
    wrappers.push(wrapper);
    await flushPromises();
    await new Promise((resolve) => setTimeout(resolve, 10));

    return wrapper;
}
function html(wrapper: Awaited<ReturnType<typeof mountEditor>>) {
    return (
        wrapper.get('input[name="description"]').element as HTMLInputElement
    ).value;
}
afterEach(() => {
    for (const wrapper of wrappers.splice(0)) {
        wrapper.unmount();
    }

    document.body.innerHTML = '';
    i18n.global.locale.value = 'en';
});

describe('StoreRichTextField', () => {
    it('preserves safe legacy HTML in its named input and renders editable tables and images', async () => {
        const content =
            '<h2 dir="rtl" style="text-align: right">وصفات</h2><table><tbody><tr><td><p>Rice</p></td></tr></tbody></table><p><a href="/temp/blog/recipe">Recipe</a></p><img src="/uploads/food.png" alt="Food">';
        const wrapper = await mountEditor({ modelValue: content });
        expect(html(wrapper)).toContain(
            '<h2 dir="rtl" style="text-align: right">وصفات</h2>',
        );
        expect(html(wrapper)).toContain('<table>');
        expect(html(wrapper)).toContain('href="/temp/blog/recipe"');
        expect(html(wrapper)).toContain('src="/uploads/food.png"');
        expect(wrapper.find('.tiptap table').exists()).toBe(true);
        expect(wrapper.get('.tiptap').attributes()).toMatchObject({
            contenteditable: 'true',
            role: 'textbox',
            'aria-labelledby': 'content-label',
        });
        expect(
            wrapper
                .findAll('button')
                .every((button) => button.attributes('type') === 'button'),
        ).toBe(true);
    });

    it('sanitizes stored and pasted source HTML before it reaches the DOM or form', async () => {
        const wrapper = await mountEditor({
            modelValue:
                '<script>danger()</script><p onclick="danger()">Safe</p><img src="javascript:alert(1)" onerror="danger()">',
        });
        expect(html(wrapper)).not.toMatch(/script|onclick|onerror|javascript/);
        expect(wrapper.find('script').exists()).toBe(false);
        await wrapper
            .findAll('button')
            .find((button) => button.text() === t('store.richText.source'))!
            .trigger('click');
        await wrapper
            .get('#content-source')
            .setValue(
                '<svg onload="danger()"></svg><p dir="rtl" style="color: red; background-color: url(javascript:alert(1))">آمن</p><a href="java\tscript:alert(1)">Unsafe link</a><iframe src="/evil"></iframe>',
            );
        expect(html(wrapper)).toContain('dir="rtl"');
        expect(html(wrapper)).toContain('color: red');
        expect(html(wrapper)).not.toMatch(
            /svg|iframe|javascript|background-color|href=/,
        );
        await wrapper
            .findAll('button')
            .find((button) => button.text() === t('store.richText.visual'))!
            .trigger('click');
        expect(wrapper.get('.tiptap').text()).toContain('آمن');
        expect(wrapper.find('[onload]').exists()).toBe(false);
    });

    it('uses actual editor state for toolbar changes and reflects server/model updates', async () => {
        const wrapper = await mountEditor({
            modelValue: '<p>Fresh content</p>',
        });
        await wrapper
            .findAll('button')
            .find((button) => button.text() === t('store.richText.heading'))!
            .trigger('click');
        expect(html(wrapper)).toContain('<h2>Fresh content</h2>');
        await wrapper.setProps({
            modelValue: '<p>Updated <strong>content</strong></p>',
        });
        expect(wrapper.get('.tiptap').text()).toBe('Updated content');
        expect(html(wrapper)).toContain('<strong>content</strong>');
    });

    it('refuses unsafe inserted links and supports English and Arabic controls', async () => {
        i18n.global.locale.value = 'ar';
        const wrapper = await mountEditor({
            modelValue: '<p>نص</p>',
            error: 'Server error',
        });
        await wrapper.get('#content-link').setValue('javascript:alert(1)');
        await wrapper
            .findAll('button')
            .find((button) => button.text() === t('store.richText.addLink'))!
            .trigger('click');
        expect(wrapper.text()).toContain(t('store.richText.invalidUrl'));
        expect(wrapper.text()).toContain('Server error');
        expect(html(wrapper)).not.toContain('javascript');
        expect(wrapper.get('[role="toolbar"]').attributes('aria-label')).toBe(
            t('store.richText.toolbar'),
        );
    });

    it('participates in native required and length validation without duplicating payload fields', async () => {
        const wrapper = await mountEditor({ required: true, maxlength: 30 });
        const proxy = wrapper.get('textarea.sr-only')
            .element as HTMLTextAreaElement;
        expect(proxy.required).toBe(true);
        expect(proxy.maxLength).toBe(30);
        expect(proxy.checkValidity()).toBe(false);
        expect(proxy.name).toBe('');
        await wrapper.setProps({ modelValue: '<p>Valid</p>' });
        expect(proxy.checkValidity()).toBe(true);
        await wrapper.setProps({ modelValue: `<p>${'a'.repeat(31)}</p>` });
        expect(proxy.checkValidity()).toBe(false);
        expect(wrapper.findAll('[name="description"]')).toHaveLength(1);
    });
});

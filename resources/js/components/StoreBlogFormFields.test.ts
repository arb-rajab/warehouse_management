import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { i18n, t } from '@/lib/i18n';
import type { StoreBlog } from '@/types/store';
import Fields from './StoreBlogFormFields.vue';
vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => ({
        get: vi.fn().mockResolvedValue(undefined),
        errors: {},
        processing: false,
    }),
}));
const blog: StoreBlog = {
    id: 1,
    title: 'وصفات',
    slug: 'stable-url',
    category_id: 3,
    category: { id: 3, category_name: 'وصفات' },
    short_description: 'Summary',
    description: '<h2 dir="rtl">وصفة</h2><p><strong>Rice</strong></p>',
    status: true,
    public_url: '/temp/blog/stable-url',
    banner: '12',
    meta_img: '14',
    meta_title: 'Metadata',
    meta_description: 'Meta description',
    meta_keywords: 'food',
};
const wrappers: ReturnType<typeof mount>[] = [];
function mountFields(props: Record<string, unknown> = {}) {
    const wrapper = mount(Fields, {
        props: {
            categories: [{ id: 3, category_name: 'وصفات' }],
            errors: {},
            ...props,
        },
        global: { stubs: { StoreFileManagerDialog: true } },
    });
    wrappers.push(wrapper);

    return wrapper;
}
afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    i18n.global.locale.value = 'en';
});

describe('StoreBlogFormFields', () => {
    it('renders translated fields and native authoritative string constraints', () => {
        const wrapper = mountFields();

        for (const key of [
            'title',
            'category',
            'slug',
            'summary',
            'description',
            'banner',
            'metaTitle',
            'metaImage',
            'metaDescription',
            'metaKeywords',
        ]) {
            expect(wrapper.text()).toContain(
                t(`store.blogPosts.fields.${key}`),
            );
        }

        for (const id of ['title', 'slug', 'category_id']) {
            expect(wrapper.get(`#${id}`).attributes('required')).toBeDefined();
        }

        for (const id of ['title', 'slug', 'meta_title']) {
            expect(wrapper.get(`#${id}`).attributes('maxlength')).toBe('255');
        }

        for (const id of [
            'short_description',
            'meta_description',
            'meta_keywords',
        ]) {
            expect(wrapper.get(`#${id}`).attributes('maxlength')).toBe('65535');
        }

        expect(wrapper.get('#slug').attributes('pattern')).toBe(
            '[A-Za-z0-9\\-]+',
        );
        expect(
            wrapper
                .findAllComponents(StoreUploadField)
                .map((field) => field.props()),
        ).toEqual([
            expect.objectContaining({
                id: 'banner',
                name: 'banner',
                type: 'image',
            }),
            expect.objectContaining({
                id: 'meta_img',
                name: 'meta_img',
                type: 'image',
            }),
        ]);
    });
    it('generates a create slug until deliberately overridden and keeps edits canonical on title changes', async () => {
        const create = mountFields();
        await create.get('#title').setValue('Fresh Rice!');
        expect((create.get('#slug').element as HTMLInputElement).value).toBe(
            'fresh-rice',
        );
        await create.get('#slug').setValue('custom-link');
        await create.get('#title').setValue('Other title');
        expect((create.get('#slug').element as HTMLInputElement).value).toBe(
            'custom-link',
        );
        const edit = mountFields({ blog });
        await edit.get('#title').setValue('Other title');
        expect((edit.get('#slug').element as HTMLInputElement).value).toBe(
            'stable-url',
        );
    });
    it('preserves stored content, upload ids and form errors using actual rich-text state', async () => {
        i18n.global.locale.value = 'ar';
        const wrapper = mountFields({
            blog,
            errors: {
                slug: 'Already taken',
                description: 'Server content error',
                banner: 'Unavailable image',
            },
        });
        await flushPromises();
        expect(
            (wrapper.get('#category_id').element as HTMLSelectElement).value,
        ).toBe('3');
        expect(
            (wrapper.get('input[name="banner"]').element as HTMLInputElement)
                .value,
        ).toBe('12');
        expect(
            (wrapper.get('input[name="meta_img"]').element as HTMLInputElement)
                .value,
        ).toBe('14');
        expect(wrapper.getComponent(StoreRichTextField).props()).toMatchObject({
            modelValue: blog.description,
            name: 'description',
            error: 'Server content error',
        });
        await wrapper.get('#title').setValue('Updated title');
        await wrapper.setProps({ errors: { slug: 'Still taken' } });
        expect((wrapper.get('#title').element as HTMLInputElement).value).toBe(
            'Updated title',
        );
        expect(wrapper.text()).toContain('Still taken');
        expect(wrapper.get('#title').element.getAttribute('value')).toBe(
            'وصفات',
        );
    });
});

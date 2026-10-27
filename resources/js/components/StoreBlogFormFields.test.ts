import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import FieldError from '@/components/FieldError.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { i18n, t } from '@/lib/i18n';
import {
    paginated,
    storeBlog,
    storeBlogCategory,
    storeSharedProps,
    storeUpload,
} from '@/testing/factories';
import { createUseHttpStub } from '@/testing/inertiaStubs';
import Fields from './StoreBlogFormFields.vue';

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

const categories = [
    storeBlogCategory(),
    storeBlogCategory({ id: 9, category_name: 'وصفات' }),
];
const banner = storeUpload({ id: 5, name: 'Banner' });
const metaImage = storeUpload({ id: 6, name: 'Meta' });
const wrappers: VueWrapper[] = [];

function mountFields(props: Record<string, unknown> = {}) {
    const wrapper = mount(Fields, {
        props: { categories, errors: {}, ...props },
    });
    wrappers.push(wrapper);

    return wrapper;
}

function value(wrapper: VueWrapper, selector: string): string {
    return (wrapper.get(selector).element as HTMLInputElement).value;
}

const labelKeys = [
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
];

beforeEach(() => {
    vi.clearAllMocks();
    usePageMock.mockReturnValue({
        props: { store: storeSharedProps({ permissions: ['view_uploads'] }) },
    });
    useHttpMock.mockImplementation((data) =>
        createUseHttpStub(data, paginated([])),
    );
});
afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    i18n.global.locale.value = 'en';
});

describe('StoreBlogFormFields', () => {
    it.each(['en', 'ar'] as const)(
        'labels every field in %s',
        async (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountFields();
            await flushPromises();

            for (const key of labelKeys) {
                expect(wrapper.text()).toContain(
                    t(`store.blogPosts.fields.${key}`),
                );
            }

            expect(wrapper.get('#category_id option').text()).toBe(
                t('store.blogPosts.fields.selectCategory'),
            );
            expect(wrapper.get('#description-label').text()).toBe(
                t('store.blogPosts.fields.description'),
            );
        },
    );

    it('mirrors the request rules with native constraints', () => {
        const wrapper = mountFields();

        for (const id of ['title', 'slug', 'category_id']) {
            expect(wrapper.get(`#${id}`).attributes()).toHaveProperty(
                'required',
            );
        }

        for (const id of ['title', 'slug', 'meta_title']) {
            expect(wrapper.get(`#${id}`).attributes('maxlength')).toBe('255');
        }

        for (const id of [
            'short_description',
            'meta_description',
            'meta_keywords',
        ]) {
            expect(wrapper.get(`#${id}`).attributes()).toMatchObject({
                name: id,
                maxlength: '65535',
            });
            expect(wrapper.get(`#${id}`).attributes()).not.toHaveProperty(
                'required',
            );
        }

        expect(wrapper.get('#slug').attributes('pattern')).toBe(
            '[A-Za-z0-9\\-]+',
        );
        expect(
            wrapper
                .findAll('#category_id option')
                .map((option) => [option.attributes('value'), option.text()]),
        ).toEqual([
            ['', t('store.blogPosts.fields.selectCategory')],
            ['4', 'Recipes'],
            ['9', 'وصفات'],
        ]);
    });

    it('passes a new post its empty values and the shared editor and upload fields', () => {
        const wrapper = mountFields();
        const uploads = wrapper.findAllComponents(StoreUploadField);
        expect(uploads.map((upload) => upload.props())).toEqual([
            expect.objectContaining({
                id: 'banner',
                name: 'banner',
                type: 'image',
                label: t('store.blogPosts.fields.banner'),
                modelValue: '',
                initialFiles: [],
                multiple: false,
                required: false,
            }),
            expect.objectContaining({
                id: 'meta_img',
                name: 'meta_img',
                type: 'image',
                label: t('store.blogPosts.fields.metaImage'),
                modelValue: '',
                initialFiles: [],
            }),
        ]);
        expect(wrapper.getComponent(StoreRichTextField).props()).toEqual({
            id: 'description',
            name: 'description',
            label: t('store.blogPosts.fields.description'),
            modelValue: '',
            required: false,
            error: undefined,
        });
        expect(value(wrapper, '#category_id')).toBe('');
        expect(value(wrapper, '#title')).toBe('');
    });

    it('follows the title with a URL-safe slug until the slug is edited', async () => {
        const wrapper = mountFields();
        await wrapper.get('#title').setValue('  Fresh Rice & Beans 2026 ');
        expect(value(wrapper, '#slug')).toBe('fresh-rice--beans-2026');
        await wrapper.get('#slug').setValue('custom-slug');
        await wrapper.get('#title').setValue('Another title');
        expect(value(wrapper, '#slug')).toBe('custom-slug');
    });

    it('fills an existing post, keeps its slug when the title changes and previews its images', async () => {
        const blog = storeBlog();
        const wrapper = mountFields({
            blog,
            initialFiles: { banner: [banner], meta_img: [metaImage] },
        });
        await flushPromises();
        expect(value(wrapper, '#title')).toBe(blog.title);
        expect(value(wrapper, '#slug')).toBe(blog.slug);
        expect(value(wrapper, '#category_id')).toBe('4');
        expect(value(wrapper, '#short_description')).toBe(
            blog.short_description,
        );
        expect(value(wrapper, '#meta_title')).toBe(blog.meta_title);
        expect(value(wrapper, '#meta_description')).toBe(blog.meta_description);
        expect(value(wrapper, '#meta_keywords')).toBe(blog.meta_keywords);
        const [bannerField, metaField] =
            wrapper.findAllComponents(StoreUploadField);
        expect(bannerField.props()).toMatchObject({
            modelValue: '5',
            initialFiles: [banner],
        });
        expect(metaField.props()).toMatchObject({
            modelValue: '6',
            initialFiles: [metaImage],
        });
        expect(
            wrapper.getComponent(StoreRichTextField).props('modelValue'),
        ).toBe(blog.description);
        expect(
            (
                wrapper.get('input[type="hidden"][name="description"]')
                    .element as HTMLInputElement
            ).value,
        ).toBe(blog.description);
        await wrapper.get('#title').setValue('Renamed post');
        expect(value(wrapper, '#slug')).toBe(blog.slug);
    });

    it('shows every server error beside its field', () => {
        const errors = {
            title: 'Bad title',
            category_id: 'Bad category',
            slug: 'Bad slug',
            banner: 'Bad banner',
            short_description: 'Bad summary',
            description: 'Bad content',
            meta_title: 'Bad meta title',
            meta_img: 'Bad meta image',
            meta_description: 'Bad meta description',
            meta_keywords: 'Bad keywords',
        };
        const wrapper = mountFields({ errors });
        expect(
            wrapper
                .findAllComponents(FieldError)
                .map((error) => error.props('message'))
                .filter(Boolean),
        ).toEqual(Object.values(errors));
        expect(wrapper.getComponent(StoreRichTextField).props('error')).toBe(
            'Bad content',
        );
    });
});

it.each([undefined, storeBlog()])(
    'marks required blog fields and leaves metadata optional on create/edit',
    (blog) => {
        const wrapper = mountFields({ blog });

        for (const id of ['title', 'slug', 'category_id']) {
            expect(wrapper.get(`#${id}`).attributes('required')).toBeDefined();
            expect(
                wrapper
                    .get(`label[for="${id}"]`)
                    .findComponent(RequiredMark)
                    .exists(),
            ).toBe(true);
        }

        for (const id of [
            'short_description',
            'meta_title',
            'meta_description',
            'meta_keywords',
        ]) {
            expect(
                wrapper.get(`#${id}`).attributes('required'),
            ).toBeUndefined();
            expect(
                wrapper
                    .get(`label[for="${id}"]`)
                    .findComponent(RequiredMark)
                    .exists(),
            ).toBe(false);
        }

        expect(wrapper.getComponent(StoreRichTextField).props('required')).toBe(
            false,
        );
        expect(
            wrapper
                .findAllComponents(StoreUploadField)
                .every((field) => !field.props('required')),
        ).toBe(true);
    },
);

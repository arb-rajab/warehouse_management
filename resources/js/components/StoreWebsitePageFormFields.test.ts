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
    storeSharedProps,
    storeUpload,
    storeWebsitePageForm,
} from '@/testing/factories';
import { createUseHttpStub } from '@/testing/inertiaStubs';
import Fields from './StoreWebsitePageFormFields.vue';

const { usePageMock, useHttpMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    useHttpMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub } = await import('@/testing/inertiaStubs');

    return {
        usePage: usePageMock,
        useHttp: useHttpMock,
        Link: createLinkStub(),
        router: { post: vi.fn() },
    };
});

const metaImage = storeUpload({ id: 31, name: 'Meta' });
const wrappers: VueWrapper[] = [];

function mountFields(props: Record<string, unknown> = {}) {
    const wrapper = mount(Fields, {
        props: { homeUrl: 'http://localhost/temp', errors: {}, ...props },
    });
    wrappers.push(wrapper);

    return wrapper;
}

function value(wrapper: VueWrapper, selector: string): string {
    return (wrapper.get(selector).element as HTMLInputElement).value;
}

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

describe('StoreWebsitePageFormFields', () => {
    it.each(['en', 'ar'] as const)(
        'labels every section and field in %s',
        async (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountFields();
            await flushPromises();

            for (const key of ['content', 'seo', 'slugHint', 'keywordsHint']) {
                expect(wrapper.text()).toContain(
                    t(`store.website.pages.${key}`),
                );
            }

            for (const key of [
                'title',
                'slug',
                'content',
                'meta_title',
                'meta_description',
                'keywords',
                'meta_image',
            ]) {
                expect(wrapper.text()).toContain(
                    t(`store.website.fields.${key}`),
                );
            }

            expect(wrapper.text()).toContain('http://localhost/temp/');
        },
    );

    it('starts a new page empty with the shared editor and canonical uploader', () => {
        const wrapper = mountFields();
        expect(wrapper.getComponent(StoreRichTextField).props()).toEqual({
            id: 'content',
            name: 'content',
            label: t('store.website.fields.content'),
            modelValue: '',
            required: true,
            error: undefined,
        });
        expect(wrapper.getComponent(StoreUploadField).props()).toMatchObject({
            id: 'meta_image',
            name: 'meta_image',
            type: 'image',
            label: t('store.website.fields.meta_image'),
            modelValue: '',
            initialFiles: [],
            multiple: false,
        });
        expect(value(wrapper, '#title')).toBe('');
        expect(value(wrapper, '#slug')).toBe('');
    });

    it('mirrors the request rules with native constraints and normalises the slug as typed', async () => {
        const wrapper = mountFields();
        expect(wrapper.get('#title').attributes()).toMatchObject({
            name: 'title',
            maxlength: '255',
        });
        expect(wrapper.get('#title').attributes()).toHaveProperty('required');
        expect(wrapper.get('#slug').attributes()).toMatchObject({
            name: 'slug',
            maxlength: '255',
            pattern: '[A-Za-z0-9\\-]+',
        });
        expect(wrapper.get('#slug').attributes()).toHaveProperty('required');
        expect(wrapper.get('#meta_title').attributes('maxlength')).toBe(
            '65535',
        );

        for (const id of ['meta_description', 'keywords']) {
            expect(wrapper.get(`#${id}`).attributes()).toMatchObject({
                name: id,
                maxlength: '1000',
            });
        }

        await wrapper.get('#slug').setValue('Our Story! 2026');
        expect(value(wrapper, '#slug')).toBe('Our-Story-2026');
    });

    it('fills a custom page for editing with its language fields, shared fields and preview', async () => {
        const page = storeWebsitePageForm({ meta_image: '31' });
        const wrapper = mountFields({
            page,
            title: 'من نحن',
            content: '<p>عنا</p>',
            initialFiles: { meta_image: [metaImage] },
        });
        await flushPromises();
        expect(value(wrapper, '#title')).toBe('من نحن');
        expect(value(wrapper, '#slug')).toBe('about');
        expect(value(wrapper, '#meta_title')).toBe('Meta');
        expect(value(wrapper, '#meta_description')).toBe('Description');
        expect(value(wrapper, '#keywords')).toBe('store');
        expect(
            wrapper.getComponent(StoreRichTextField).props('modelValue'),
        ).toBe('<p>عنا</p>');
        expect(wrapper.getComponent(StoreUploadField).props()).toMatchObject({
            modelValue: '31',
            initialFiles: [metaImage],
        });
    });

    it('shows a policy page its fixed address and posts no slug', () => {
        const wrapper = mountFields({
            page: storeWebsitePageForm({
                type: 'return_policy_page',
                slug: 'return-policy',
                public_url: 'http://localhost/temp/return-policy',
            }),
            title: 'Return Policy',
            content: '<p>Returns</p>',
        });
        expect(wrapper.get('#slug').attributes()).toHaveProperty('readonly');
        expect(wrapper.get('#slug').attributes('name')).toBeUndefined();
        expect(value(wrapper, '#slug')).toBe(
            'http://localhost/temp/return-policy',
        );
        expect(wrapper.find('[name="slug"]').exists()).toBe(false);
        expect(wrapper.text()).toContain(t('store.website.pages.fixedSlug'));
    });

    it('shows every server error beside its field', () => {
        const errors = {
            title: 'Bad title',
            slug: 'Bad slug',
            content: 'Bad content',
            meta_title: 'Bad meta title',
            meta_description: 'Bad meta description',
            keywords: 'Bad keywords',
            meta_image: 'Bad meta image',
        };
        const wrapper = mountFields({ errors });
        expect(
            wrapper
                .findAllComponents(FieldError)
                .map((error) => error.props('message'))
                .filter(Boolean),
        ).toEqual(Object.values(errors));
    });
    it.each([undefined, 'custom_page', 'return_policy_page'])(
        'marks the slug only for editable pages (%s)',
        (type) => {
            const wrapper = mountFields(
                type === undefined
                    ? {}
                    : { page: storeWebsitePageForm({ type }) },
            );
            const editable = type === undefined || type === 'custom_page';
            expect(
                wrapper.get('#slug').attributes('required') !== undefined,
            ).toBe(editable);
            expect(
                wrapper
                    .get('label[for=slug]')
                    .findComponent(RequiredMark)
                    .exists(),
            ).toBe(editable);
            expect(
                wrapper
                    .get('label[for=title]')
                    .findComponent(RequiredMark)
                    .exists(),
            ).toBe(true);
            expect(
                wrapper.getComponent(StoreRichTextField).props('required'),
            ).toBe(true);

            for (const id of ['meta_title', 'meta_description', 'keywords']) {
                expect(
                    wrapper.get(`#${id}`).attributes('required'),
                ).toBeUndefined();
                expect(
                    wrapper
                        .get(`label[for=${id}]`)
                        .findComponent(RequiredMark)
                        .exists(),
                ).toBe(false);
            }

            expect(
                wrapper.getComponent(StoreUploadField).props('required'),
            ).toBe(false);
        },
    );
});

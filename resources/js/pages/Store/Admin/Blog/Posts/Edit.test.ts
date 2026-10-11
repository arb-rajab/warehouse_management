import { Form } from '@inertiajs/vue3';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    index as blogsIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/BlogController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreBlogFormFields from '@/components/StoreBlogFormFields.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Edit from './Edit.vue';
const { usePageMock, formStateMock, getMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    formStateMock: vi.fn(),
    getMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Form: createFormStub(formStateMock),
        Link: createLinkStub(),
        Head: headStub,
        usePage: usePageMock,
        router: { post: vi.fn() },
        useHttp: () => ({ get: getMock, processing: false, errors: {} }),
    };
});
const categories = [{ id: 3, category_name: 'وصفات' }];
const blog = {
    id: 1,
    title: 'وصفات',
    slug: 'stable-url',
    category_id: 3,
    category: categories[0],
    short_description: 'Summary',
    description: '<p><strong>Recipe</strong></p>',
    banner: '',
    meta_img: '',
    meta_title: 'Metadata',
    meta_description: 'Description',
    meta_keywords: 'food',
    status: true,
    public_url: '/temp/blog/stable-url',
};
const wrappers: ReturnType<typeof mount>[] = [];
function mountPage() {
    const wrapper = mount(Edit, {
        props: { blog, categories },
        global: { stubs: { StoreFileManagerDialog: true } },
    });
    wrappers.push(wrapper);

    return wrapper;
}
describe('Store blog Edit', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        getMock.mockResolvedValue(undefined);
        usePageMock.mockReturnValue({
            url: blogsIndex.url(),
            props: defaultAuthProps({ store: storeSharedProps() }),
        });
        formStateMock.mockReturnValue({
            errors: { slug: 'Already taken' },
            processing: false,
        });
    });
    afterEach(() => wrappers.splice(0).forEach((wrapper) => wrapper.unmount()));
    it('renders the actual store shell, form fields and rich-text editor with the correct Wayfinder action', async () => {
        const wrapper = mountPage();
        await flushPromises();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            title: t('store.blogPosts.edit.title'),
            submitLabel: t('store.blogPosts.edit.submit'),
            submittingLabel: t('store.blogPosts.edit.submitting'),
            layout: StoreAdminLayout,
            wide: true,
            cancelHref: blogsIndex(),
        });
        expect(wrapper.getComponent(Form).props('action')).toEqual(
            update(blog),
        );
        expect(wrapper.getComponent(StoreBlogFormFields).props()).toMatchObject(
            { categories, errors: { slug: 'Already taken' } },
        );
        expect(wrapper.getComponent(StoreRichTextField).props('name')).toBe(
            'description',
        );
        expect(wrapper.find('[contenteditable="true"]').exists()).toBe(true);
        expect(wrapper.get('#slug').attributes('required')).toBeDefined();
        expect((wrapper.get('#slug').element as HTMLInputElement).value).toBe(
            'stable-url',
        );
        expect(
            (
                wrapper.get('input[name="description"]')
                    .element as HTMLInputElement
            ).value,
        ).toContain('<strong>Recipe</strong>');
    });
    it('disables the shared submit control while processing and renders authoritative server errors', () => {
        formStateMock.mockReturnValue({
            errors: {
                title: 'A title is required',
                description: 'Invalid content',
            },
            processing: true,
        });
        const wrapper = mountPage();
        expect(
            wrapper.get('button[type="submit"]').attributes('disabled'),
        ).toBeDefined();
        expect(wrapper.text()).toContain('A title is required');
        expect(wrapper.text()).toContain('Invalid content');
    });
});

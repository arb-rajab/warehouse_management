import { Form } from '@inertiajs/vue3';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    index as blogsIndex,
    store,
} from '@/actions/App/Http/Controllers/Store/Admin/BlogController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreBlogFormFields from '@/components/StoreBlogFormFields.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Create from './Create.vue';
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

const wrappers: ReturnType<typeof mount>[] = [];
function mountPage() {
    const wrapper = mount(Create, {
        props: { categories },
        global: { stubs: { StoreFileManagerDialog: true } },
    });
    wrappers.push(wrapper);

    return wrapper;
}
describe('Store blog Create', () => {
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
            title: t('store.blogPosts.create.title'),
            submitLabel: t('store.blogPosts.create.submit'),
            submittingLabel: t('store.blogPosts.create.submitting'),
            layout: StoreAdminLayout,
            wide: true,
            cancelHref: blogsIndex(),
        });
        expect(wrapper.getComponent(Form).props('action')).toEqual(store());
        expect(wrapper.getComponent(StoreBlogFormFields).props()).toMatchObject(
            { categories, errors: { slug: 'Already taken' } },
        );
        expect(wrapper.getComponent(StoreRichTextField).props('name')).toBe(
            'description',
        );
        expect(wrapper.find('[contenteditable="true"]').exists()).toBe(true);
        expect(wrapper.get('#slug').attributes('required')).toBeDefined();
        expect((wrapper.get('#title').element as HTMLInputElement).value).toBe(
            '',
        );
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

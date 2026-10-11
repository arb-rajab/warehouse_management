import { Form, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    changeStatus,
    create,
    destroy,
    edit,
    index as blogsIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/BlogController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { paginated, storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type { StoreBlogSummary } from '@/types/store';
import Index from './Index.vue';
const { usePageMock, getMock, formStateMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    getMock: vi.fn(),
    formStateMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Form: createFormStub(formStateMock),
        Link: createLinkStub(),
        Head: headStub,
        usePage: usePageMock,
        router: { get: getMock, post: vi.fn() },
    };
});
const blog: StoreBlogSummary = {
    id: 1,
    title: 'وصفات',
    category: { id: 3, category_name: 'Recipes' },
    status: true,
    public_url: '/temp/blog/stable-url',
};
const allPermissions = [
    'view_blogs',
    'add_blog',
    'edit_blog',
    'delete_blog',
    'publish_blog',
];
const wrappers: ReturnType<typeof mount>[] = [];
function mountPage(blogs = [blog], permissions = allPermissions) {
    usePageMock.mockReturnValue({
        url: blogsIndex.url({ query: { search: 'Rice' } }),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });
    const wrapper = mount(Index, {
        props: {
            blogs: paginated(blogs),
            filters: { search: 'Rice', per_page: 20 },
        },
    });
    wrappers.push(wrapper);

    return wrapper;
}
beforeEach(() => {
    vi.clearAllMocks();
    formStateMock.mockReturnValue({ errors: {}, processing: false });
});
afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    vi.restoreAllMocks();
});
describe('Store blog posts Index', () => {
    it('renders the store shell, translated headers and the database content as text', () => {
        const wrapper = mountPage([
            { ...blog, title: '<img src=x onerror=alert(1)>', category: null },
        ]);
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.get('h1').text()).toBe(t('store.blogPosts.index.title'));
        expect(wrapper.findAll('thead th').map((cell) => cell.text())).toEqual(
            [
                'columnTitle',
                'columnCategory',
                'columnStatus',
                'columnActions',
            ].map((key) => t(`store.blogPosts.index.${key}`)),
        );
        expect(wrapper.get('tbody').text()).toContain(
            '<img src=x onerror=alert(1)>',
        );
        expect(wrapper.find('tbody img').exists()).toBe(false);
        expect(wrapper.get('tbody').text()).toContain('—');
    });
    it('shows a translated empty state and uses Wayfinder add/edit/delete actions and a plain public link', () => {
        expect(mountPage([]).text()).toContain(
            t('store.blogPosts.index.empty'),
        );
        const wrapper = mountPage();
        expect(wrapper.getComponent(AddResourceLink).props('href')).toEqual(
            create(),
        );
        const actions = wrapper.findAllComponents(TableActionLink);
        expect(actions[0].props('href')).toEqual(edit(blog));
        expect(actions[1].props()).toMatchObject({
            href: destroy(blog, { mergeQuery: {} }),
            variant: 'danger',
        });
        expect(
            wrapper.get('tbody a[href="/temp/blog/stable-url"]').text(),
        ).toContain(t('store.blogPosts.index.view'));
        expect(actions[1].getComponent(Link).props('as')).toBe('button');
        expect(actions[1].attributes()).toMatchObject({
            method: 'delete',
        });
    });
    it('uses a publication Form with authoritative boolean payload, pending state and server errors', () => {
        formStateMock.mockReturnValue({
            processing: true,
            errors: { status: 'Invalid publication status' },
        });
        const wrapper = mountPage();
        const statusForm = wrapper.findAllComponents(Form)[1];
        expect(statusForm.props('action')).toEqual(
            changeStatus(blog, { mergeQuery: {} }),
        );
        expect(statusForm.props('options')).toEqual({ preserveScroll: true });
        expect(
            (statusForm.get('input[name="status"]').element as HTMLInputElement)
                .value,
        ).toBe('0');
        expect(statusForm.get('button').attributes()).toMatchObject({
            type: 'submit',
            disabled: '',
            'aria-pressed': 'true',
        });
        expect(statusForm.text()).toContain('Invalid publication status');
        const unpublished = mountPage([{ ...blog, status: false }]);
        expect(
            (
                unpublished.get('input[name="status"]')
                    .element as HTMLInputElement
            ).value,
        ).toBe('1');
    });
    it('hides every mutation affordance without its matching permission', () => {
        const wrapper = mountPage([blog], ['view_blogs']);
        expect(wrapper.findComponent(AddResourceLink).exists()).toBe(false);
        expect(wrapper.findAllComponents(TableActionLink)).toHaveLength(0);
        expect(wrapper.find('input[name="status"]').exists()).toBe(false);
        expect(wrapper.findAllComponents(Form)).toHaveLength(1);
    });
    it('confirms deletion using the stored title and honors cancellation', () => {
        const wrapper = mountPage();
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const onBefore = wrapper
            .findAllComponents(TableActionLink)[1]
            .getComponent(Link)
            .props('onBefore') as () => boolean;
        expect(onBefore()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: blog.title }),
        );
    });
    it('uses a constrained GET search form and preserves its filters when changing page size', () => {
        const wrapper = mountPage();
        expect(wrapper.findAllComponents(Form)[0].props('action')).toEqual(
            blogsIndex(),
        );
        expect(wrapper.get('#search').attributes('maxlength')).toBe('255');
        wrapper.getComponent(Pagination).vm.$emit('update:perPage', 50);
        expect(getMock).toHaveBeenCalledWith(
            blogsIndex.url(),
            { per_page: 50, search: 'Rice' },
            { preserveState: true, replace: true },
        );
    });
});

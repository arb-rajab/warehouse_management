import { Form, Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    create as categoriesCreate,
    destroy as categoriesDestroy,
    edit as categoriesEdit,
    index as categoriesIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/BlogCategoryController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import FormField from '@/components/FormField.vue';
import Pagination from '@/components/Pagination.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { rowCells } from '@/testing/dom';
import {
    paginated,
    storeBlogCategory,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type { StoreBlogCategory } from '@/types/store';
import Index from './Index.vue';

const { usePageMock, routerGetMock, routerPostMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
    routerPostMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(() => ({ errors: {}, processing: false })),
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { get: routerGetMock, post: routerPostMock },
    };
});

const everyCategoryPermission = [
    'view_blog_categories',
    'add_blog_category',
    'edit_blog_category',
    'delete_blog_category',
];

function mountPage(
    categories: StoreBlogCategory[],
    { permissions = everyCategoryPermission, perPage = 20 } = {},
) {
    usePageMock.mockReturnValue({
        url: categoriesIndex.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, {
        props: {
            categories: paginated(categories),
            filters: { per_page: perPage, search: null },
        },
    });
}

function actionLink(wrapper: ReturnType<typeof mountPage>, label: string) {
    return wrapper
        .findAllComponents(TableActionLink)
        .find((link) => link.text().includes(label));
}

describe('Store blog categories Index', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, routerGetMock, routerPostMock });
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('renders inside the store layout, titled in the Head and the header', () => {
        const wrapper = mountPage([]);

        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('store.blogCategories.index.title'),
        );
        expect(wrapper.get('h1').text()).toBe(
            t('store.blogCategories.index.title'),
        );
        expect(t('store.blogCategories.index.title')).toBe('Blog categories');
    });

    it('renders every column header and the empty message', () => {
        const wrapper = mountPage([]);

        expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual([
            t('store.blogCategories.index.columnName'),
            t('store.blogCategories.index.columnActions'),
        ]);
        expect(wrapper.text()).toContain(t('store.blogCategories.index.empty'));
    });

    it('links the add button to the create page for a user who may add categories', () => {
        expect(
            mountPage([]).getComponent(AddResourceLink).props(),
        ).toMatchObject({
            href: categoriesCreate(),
            label: t('store.blogCategories.index.add'),
        });
        expect(
            mountPage([], { permissions: ['view_blog_categories'] })
                .findComponent(AddResourceLink)
                .exists(),
        ).toBe(false);
    });

    it("renders a category's name with its edit and delete actions", () => {
        const role = storeBlogCategory();
        const wrapper = mountPage([role]);

        expect(rowCells(wrapper)[0].text()).toBe('Recipes');
        expect(
            actionLink(wrapper, t('store.blogCategories.index.edit'))?.props(
                'href',
            ),
        ).toEqual(categoriesEdit(role));
        expect(
            actionLink(
                wrapper,
                t('store.blogCategories.index.delete'),
            )?.props(),
        ).toMatchObject({
            href: categoriesDestroy(role, { mergeQuery: {} }),
            variant: 'danger',
        });
    });

    it('asks before deleting a category', () => {
        const wrapper = mountPage([storeBlogCategory()]);
        const onBefore = actionLink(
            wrapper,
            t('store.blogCategories.index.delete'),
        )
            ?.getComponent(Link)
            .props('onBefore') as () => boolean;
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);

        expect(onBefore()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: 'Recipes' }),
        );
    });

    it('renders the GET search form with native constraints and a translated submit label', () => {
        const wrapper = mountPage([]);
        expect(wrapper.getComponent(Form).props('action')).toEqual(
            categoriesIndex(),
        );
        expect(wrapper.getComponent(FormField).props('label')).toBe(
            t('store.blogCategories.index.search'),
        );
        expect(wrapper.get('#search').attributes('maxlength')).toBe('255');
        expect(wrapper.getComponent(SubmitButton).props()).toMatchObject({
            label: t('store.blogCategories.index.search'),
            processingLabel: t('store.blogCategories.index.searching'),
        });
    });

    it('displays stored markup as text', () => {
        const wrapper = mountPage([
            storeBlogCategory({
                category_name: '<img src=x onerror=alert(1)>',
            }),
        ]);
        expect(wrapper.get('tbody').text()).toContain(
            '<img src=x onerror=alert(1)>',
        );
        expect(wrapper.find('tbody img').exists()).toBe(false);
    });

    it('offers neither edit nor delete without the permissions', () => {
        const wrapper = mountPage([storeBlogCategory()], {
            permissions: ['view_blog_categories'],
        });

        expect(wrapper.findAllComponents(TableActionLink)).toHaveLength(0);
    });

    it('pages the list, and reloads it at a new page size', () => {
        const wrapper = mountPage([storeBlogCategory()], { perPage: 25 });
        const pagination = wrapper.getComponent(Pagination);

        expect(pagination.props('perPage')).toBe(25);

        pagination.vm.$emit('update:perPage', 50);

        expect(routerGetMock).toHaveBeenCalledWith(
            categoriesIndex.url(),
            { per_page: 50, search: null },
            { preserveState: true, replace: true },
        );
    });
});

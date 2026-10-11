import { Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AddResourceLink from '@/components/AddResourceLink.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import {
    paginated,
    storeWebsitePage,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import Index from './Index.vue';
const { usePageMock, routerGetMock, routerPostMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
    routerPostMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, createFormStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        Form: createFormStub(() => ({ errors: {}, processing: false })),
        usePage: usePageMock,
        router: { get: routerGetMock, post: routerPostMock },
    };
});
function grant(permissions: string[]) {
    usePageMock.mockReturnValue({
        url: '/temp/admin/website/pages',
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });
}
beforeEach(() => {
    resetMocks({ usePageMock, routerGetMock, routerPostMock });
    grant([
        'view_all_website_pages',
        'add_website_page',
        'edit_website_page',
        'delete_website_page',
    ]);
});
describe('Website page list', () => {
    function mountPage(rows = [storeWebsitePage()]) {
        return mount(Index, {
            props: {
                pages: paginated(rows),
                filters: { per_page: 20 },
                defaultLanguage: 'ar',
            },
        });
    }
    it('uses the store layout, all table labels and native public links and Inertia editing actions', () => {
        const wrapper = mountPage();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('store.website.pages.title'),
        );
        expect(wrapper.getComponent(PageHeader).props('title')).toBe(
            t('store.website.pages.title'),
        );
        expect(wrapper.findAll('th').map((th) => th.text())).toEqual([
            t('store.website.pages.name'),
            t('store.website.pages.url'),
            t('store.support.actions'),
        ]);
        const add = wrapper.getComponent(AddResourceLink);
        expect(add.props('inertia')).toBe(true);
        expect(add.get('a').attributes('href')).toBe(
            '/temp/admin/website/custom-pages/create',
        );
        expect(add.text()).toContain(t('store.website.pages.add'));
        const edit = wrapper.findAllComponents(TableActionLink)[0];
        expect(edit.props('inertia')).toBe(true);
        expect(edit.get('a').attributes('href')).toBe(
            '/temp/admin/website/custom-pages/44/edit?lang=ar',
        );
        expect(edit.text()).toContain(t('store.website.pages.edit'));
        expect(wrapper.get('td a').attributes('href')).toBe(
            'http://localhost/temp/about',
        );
    });
    it('deletes only custom pages through a bound DELETE action with native confirmation', () => {
        const wrapper = mountPage([
            storeWebsitePage(),
            storeWebsitePage({ id: 46, slug: 'home', type: 'home_page' }),
        ]);
        const links = wrapper.findAllComponents(TableActionLink);
        expect(links).toHaveLength(3);
        expect(links[1].props('href')).toEqual({
            url: '/temp/admin/website/custom-pages/44',
            method: 'delete',
        });
        expect(links[1].text()).toContain(t('store.support.delete'));
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const onBefore = links[1]
            .getComponent(Link)
            .props('onBefore') as () => boolean;
        expect(onBefore()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: 'About us' }),
        );
        confirm.mockRestore();
        expect(links[2].props('inertia')).toBe(true);
        expect(links[2].props('href')).toEqual({
            url: '/temp/admin/website/custom-pages/edit/home',
            method: 'get',
        });
    });
    it('hides ungranted writes and escapes stored titles', () => {
        grant(['view_all_website_pages']);
        const wrapper = mountPage([
            storeWebsitePage({ title: '<img src=x onerror=alert(1)>' }),
        ]);
        expect(wrapper.findComponent(AddResourceLink).exists()).toBe(false);
        expect(wrapper.findComponent(TableActionLink).exists()).toBe(false);
        expect(wrapper.text()).toContain('<img src=x onerror=alert(1)>');
        expect(wrapper.find('td img').exists()).toBe(false);
    });
    it('renders empty results, pagination links and changes the page size', () => {
        const wrapper = mountPage([]);
        expect(wrapper.text()).toContain(t('store.website.pages.empty'));
        expect(wrapper.getComponent(Pagination).props()).toMatchObject({
            links: [],
            perPage: 20,
        });
        wrapper.getComponent(Pagination).vm.$emit('update:perPage', 10);
        expect(routerGetMock).toHaveBeenCalledWith(
            '/temp/admin/website/pages',
            { per_page: 10 },
            { preserveState: true, replace: true },
        );
    });
});

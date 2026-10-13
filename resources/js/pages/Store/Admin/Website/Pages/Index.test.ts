import { Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { edit as homeEdit } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteHomeController';
import {
    create,
    destroy,
    edit,
    index as pagesIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/WebsitePageController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import {
    paginated,
    storeSharedProps,
    storeWebsitePage,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Index from './Index.vue';

const { usePageMock, getMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    getMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Form: createFormStub(() => ({ errors: {}, processing: false })),
        Link: createLinkStub(),
        Head: headStub,
        usePage: usePageMock,
        router: { get: getMock, post: vi.fn() },
    };
});

const custom = storeWebsitePage();
const policy = storeWebsitePage({
    id: 45,
    title: 'Return Policy',
    slug: 'return-policy',
    type: 'return_policy_page',
    public_url: 'http://localhost/temp/return-policy',
});
const home = storeWebsitePage({
    id: 46,
    title: 'Home',
    slug: 'home',
    type: 'home_page',
    public_url: 'http://localhost/temp',
});
const allPermissions = [
    'view_all_website_pages',
    'add_website_page',
    'edit_website_page',
    'delete_website_page',
];
const wrappers: ReturnType<typeof mount>[] = [];

function mountPage(
    pages = [custom, policy, home],
    permissions = allPermissions,
) {
    usePageMock.mockReturnValue({
        url: pagesIndex.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });
    const wrapper = mount(Index, {
        props: { pages: paginated(pages), filters: { per_page: 20 } },
    });
    wrappers.push(wrapper);

    return wrapper;
}

beforeEach(() => {
    vi.clearAllMocks();
});
afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    i18n.global.locale.value = 'en';
    vi.restoreAllMocks();
});

describe('Website pages Index', () => {
    it.each(['en', 'ar'] as const)(
        'renders the store shell and every label in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountPage();
            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
            expect(wrapper.get('h1').text()).toBe(
                t('store.website.pages.title'),
            );
            expect(
                wrapper.findAll('thead th').map((cell) => cell.text()),
            ).toEqual(
                ['name', 'url', 'actions'].map((key) =>
                    t(`store.website.pages.${key}`),
                ),
            );
            expect(wrapper.getComponent(AddResourceLink).props('label')).toBe(
                t('store.website.pages.add'),
            );
            expect(wrapper.text()).toContain(t('store.website.pages.edit'));
            expect(wrapper.text()).toContain(t('store.website.pages.delete'));
            expect(mountPage([]).text()).toContain(
                t('store.website.pages.empty'),
            );
        },
    );

    it('links each page to the storefront and prints titles as text', () => {
        const wrapper = mountPage([
            { ...custom, title: '<img src=x onerror=alert(1)>' },
            { ...policy, slug: null, public_url: null },
        ]);
        expect(wrapper.findAll('tbody tr')).toHaveLength(2);
        const link = wrapper.get('tbody a[href="http://localhost/temp/about"]');
        expect(link.text()).toContain('<img src=x onerror=alert(1)>');
        expect(link.attributes()).toMatchObject({
            target: '_blank',
            rel: 'noopener',
        });
        expect(wrapper.find('tbody img').exists()).toBe(false);
        expect(wrapper.get('tbody').text()).toContain('—');
    });

    it('visits the Inertia forms, the home settings for the home row and deletes custom pages only', () => {
        const wrapper = mountPage();
        expect(wrapper.getComponent(AddResourceLink).props()).toMatchObject({
            href: create(),
            inertia: true,
        });
        const actions = wrapper.findAllComponents(TableActionLink);
        expect(actions.map((action) => action.props('href'))).toEqual([
            edit('about'),
            destroy(custom, { mergeQuery: {} }),
            edit('return-policy'),
            homeEdit(),
        ]);
        expect(actions[0].getComponent(Link).props('href')).toEqual(
            edit('about'),
        );
        expect(actions[1].props('variant')).toBe('danger');
        expect(actions[1].getComponent(Link).props('as')).toBe('button');
        expect(actions[1].attributes()).toMatchObject({ method: 'delete' });
    });

    it('confirms deletion with the page title and honours cancellation', () => {
        const wrapper = mountPage();
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const onBefore = wrapper
            .findAllComponents(TableActionLink)[1]
            .getComponent(Link)
            .props('onBefore') as () => boolean;
        expect(onBefore()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: custom.title }),
        );
    });

    it('hides each action without its permission', () => {
        expect(
            mountPage(undefined, ['view_all_website_pages']).findAllComponents(
                TableActionLink,
            ),
        ).toHaveLength(0);
        expect(
            mountPage(undefined, ['view_all_website_pages'])
                .findComponent(AddResourceLink)
                .exists(),
        ).toBe(false);
        const editOnly = mountPage(undefined, [
            'view_all_website_pages',
            'edit_website_page',
        ]).findAllComponents(TableActionLink);
        expect(editOnly).toHaveLength(3);
        const deleteOnly = mountPage(undefined, [
            'view_all_website_pages',
            'delete_website_page',
        ]).findAllComponents(TableActionLink);
        expect(deleteOnly.map((action) => action.props('href'))).toEqual([
            destroy(custom, { mergeQuery: {} }),
        ]);
    });

    it('changes the page size through the list route', () => {
        const wrapper = mountPage();
        expect(wrapper.getComponent(Pagination).props('perPage')).toBe(20);
        wrapper.getComponent(Pagination).vm.$emit('update:perPage', 50);
        expect(getMock).toHaveBeenCalledWith(
            pagesIndex.url(),
            { per_page: 50 },
            { preserveState: true, replace: true },
        );
    });
});

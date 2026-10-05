import { Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    create as rolesCreate,
    destroy as rolesDestroy,
    edit as rolesEdit,
    index as rolesIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/RoleController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { rowCells } from '@/testing/dom';
import { paginated, storeRole, storeSharedProps } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type { StoreRole } from '@/types/store';
import Index from './Index.vue';

const { usePageMock, routerGetMock, routerPostMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
    routerPostMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { get: routerGetMock, post: routerPostMock },
    };
});

const everyRolePermission = [
    'view_staff_roles',
    'add_staff_role',
    'edit_staff_role',
    'delete_staff_role',
];

function mountPage(
    roles: StoreRole[],
    { permissions = everyRolePermission, perPage = 20 } = {},
) {
    usePageMock.mockReturnValue({
        url: rolesIndex.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, {
        props: { roles: paginated(roles), filters: { per_page: perPage } },
    });
}

function actionLink(wrapper: ReturnType<typeof mountPage>, label: string) {
    return wrapper
        .findAllComponents(TableActionLink)
        .find((link) => link.text().includes(label));
}

describe('Store roles Index', () => {
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
            t('store.roles.index.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('store.roles.index.title'));
        expect(t('store.roles.index.title')).toBe('Roles');
    });

    it('renders every column header and the empty message', () => {
        const wrapper = mountPage([]);

        expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual([
            t('store.roles.index.columnName'),
            t('store.roles.index.columnActions'),
        ]);
        expect(wrapper.text()).toContain(t('store.roles.index.empty'));
    });

    it('links the add button to the create page for a user who may add roles', () => {
        expect(mountPage([]).getComponent(AddResourceLink).props()).toEqual({
            href: rolesCreate(),
            label: t('store.roles.index.addRole'),
        });
        expect(
            mountPage([], { permissions: ['view_staff_roles'] })
                .findComponent(AddResourceLink)
                .exists(),
        ).toBe(false);
    });

    it("renders a role's name with its edit and delete actions", () => {
        const role = storeRole();
        const wrapper = mountPage([role]);

        expect(rowCells(wrapper)[0].text()).toBe('Clerk');
        expect(
            actionLink(wrapper, t('store.roles.index.edit'))?.props('href'),
        ).toEqual(rolesEdit(role));
        expect(
            actionLink(wrapper, t('store.roles.index.delete'))?.props(),
        ).toMatchObject({
            href: rolesDestroy(role, { mergeQuery: {} }),
            variant: 'danger',
        });
    });

    it('asks before deleting a role', () => {
        const wrapper = mountPage([storeRole()]);
        const onBefore = actionLink(wrapper, t('store.roles.index.delete'))
            ?.getComponent(Link)
            .props('onBefore') as () => boolean;
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);

        expect(onBefore()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: 'Clerk' }),
        );
    });

    it('offers neither edit nor delete without the permissions', () => {
        const wrapper = mountPage([storeRole()], {
            permissions: ['view_staff_roles'],
        });

        expect(wrapper.findAllComponents(TableActionLink)).toHaveLength(0);
    });

    it('pages the list, and reloads it at a new page size', () => {
        const wrapper = mountPage([storeRole()], { perPage: 25 });
        const pagination = wrapper.getComponent(Pagination);

        expect(pagination.props('perPage')).toBe(25);

        pagination.vm.$emit('update:perPage', 50);

        expect(routerGetMock).toHaveBeenCalledWith(
            rolesIndex.url(),
            { per_page: 50 },
            { preserveState: true, replace: true },
        );
    });
});

import { Head, Link } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    create as staffCreate,
    destroy as staffDestroy,
    edit as staffEdit,
    index as staffIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/StaffController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { rowCells } from '@/testing/dom';
import { paginated, staffMember, storeSharedProps } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type { StaffMember } from '@/types/store';
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

const everyStaffPermission = [
    'view_all_staffs',
    'add_staff',
    'edit_staff',
    'delete_staff',
];

function mountPage(
    staff: StaffMember[],
    {
        permissions = everyStaffPermission,
        currentUserId = 7,
        perPage = 20,
    } = {},
) {
    usePageMock.mockReturnValue({
        url: staffIndex.url(),
        props: defaultAuthProps({
            auth: { user: { name: 'Current User', id: currentUserId } },
            store: storeSharedProps({ permissions }),
        }),
    });

    return mount(Index, {
        props: { staff: paginated(staff), filters: { per_page: perPage } },
    });
}

function actionLink(wrapper: ReturnType<typeof mountPage>, label: string) {
    return wrapper
        .findAllComponents(TableActionLink)
        .find((link) => link.text().includes(label));
}

describe('Store staff Index', () => {
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
            t('store.staff.index.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('store.staff.index.title'));
        expect(t('store.staff.index.title')).toBe('Staff');
    });

    it('renders every column header', () => {
        const wrapper = mountPage([]);

        expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual([
            t('store.staff.index.columnName'),
            t('store.staff.index.columnEmail'),
            t('store.staff.index.columnPhone'),
            t('store.staff.index.columnRole'),
            t('store.staff.index.columnActions'),
        ]);
    });

    it('shows the empty message when there is no staff', () => {
        const wrapper = mountPage([]);

        expect(wrapper.text()).toContain(t('store.staff.index.empty'));
    });

    it('links the add button to the create page for a user who may add staff', () => {
        const wrapper = mountPage([]);

        expect(wrapper.getComponent(AddResourceLink).props()).toEqual({
            href: staffCreate(),
            inertia: true,
            label: t('store.staff.index.addStaff'),
        });
    });

    it('offers no add button without the permission', () => {
        const wrapper = mountPage([], { permissions: ['view_all_staffs'] });

        expect(wrapper.findComponent(AddResourceLink).exists()).toBe(false);
    });

    it("renders a staff member's details and role", () => {
        const wrapper = mountPage([staffMember()]);

        expect(
            rowCells(wrapper)
                .map((cell) => cell.text())
                .slice(0, 4),
        ).toEqual(['Sam Staff', 'sam@example.com', '0612345678', 'Clerk']);
    });

    it('says when a staff member has no role', () => {
        const wrapper = mountPage([staffMember({ role: null })]);

        expect(rowCells(wrapper)[3].text()).toBe(t('store.staff.index.noRole'));
    });

    it('marks a Super Admin and offers no edit or delete for them', () => {
        const wrapper = mountPage([staffMember({ is_super_admin: true })]);

        expect(rowCells(wrapper)[3].text()).toBe(
            t('store.staff.index.superAdmin'),
        );
        expect(rowCells(wrapper)[3].findComponent(ShieldCheck).exists()).toBe(
            true,
        );
        expect(wrapper.findAllComponents(TableActionLink)).toHaveLength(0);
    });

    it('links edit to the staff member’s edit page', () => {
        const member = staffMember();
        const wrapper = mountPage([member]);

        expect(
            actionLink(wrapper, t('store.staff.index.edit'))?.props('href'),
        ).toEqual(staffEdit(member));
    });

    it('deletes through a confirmed DELETE that keeps the page and filters', () => {
        const member = staffMember();
        const wrapper = mountPage([member]);
        const deleteLink = actionLink(wrapper, t('store.staff.index.delete'));

        expect(deleteLink?.props()).toMatchObject({
            href: staffDestroy(member, { mergeQuery: {} }),
            variant: 'danger',
        });
        expect(deleteLink?.attributes('method')).toBe('delete');

        const onBefore = deleteLink
            ?.getComponent(Link)
            .props('onBefore') as () => boolean;
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        expect(onBefore()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: 'Sam Staff' }),
        );
    });

    it('offers no delete for the signed-in user’s own account', () => {
        const wrapper = mountPage([staffMember({ user_id: 7 })], {
            currentUserId: 7,
        });

        expect(
            actionLink(wrapper, t('store.staff.index.delete')),
        ).toBeUndefined();
        expect(actionLink(wrapper, t('store.staff.index.edit'))).toBeDefined();
    });

    it('offers neither edit nor delete without the permissions', () => {
        const wrapper = mountPage([staffMember()], {
            permissions: ['view_all_staffs'],
        });

        expect(wrapper.findAllComponents(TableActionLink)).toHaveLength(0);
    });

    it('pages the list, and reloads it at a new page size', async () => {
        const wrapper = mountPage([staffMember()], { perPage: 50 });
        const pagination = wrapper.getComponent(Pagination);

        expect(pagination.props('perPage')).toBe(50);
        expect(pagination.props('links')).toEqual([]);

        pagination.vm.$emit('update:perPage', 10);

        expect(routerGetMock).toHaveBeenCalledWith(
            staffIndex.url(),
            { per_page: 10 },
            { preserveState: true, replace: true },
        );
    });
});

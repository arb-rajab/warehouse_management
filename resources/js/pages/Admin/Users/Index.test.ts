import { Head } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { t } from '@/lib/i18n';
import { rowCells } from '@/testing/dom';
import { paginated, user } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type { User } from '@/types/admin';
import Index from './Index.vue';

const { usePageMock, routerPostMock, routerGetMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerPostMock: vi.fn(),
    routerGetMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { post: routerPostMock, get: routerGetMock },
    };
});

function mountPage(users: User[], currentUserId = 7, perPage = 20) {
    usePageMock.mockReturnValue({
        url: '/admin/users',
        props: defaultAuthProps({
            auth: { user: { name: 'Current User', id: currentUserId } },
        }),
    });

    return mount(Index, {
        props: { users: paginated(users), filters: { per_page: perPage } },
    });
}

describe('Users Index', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, routerPostMock, routerGetMock });
    });

    it('renders the page title in the Head and the PageHeader', () => {
        const wrapper = mountPage([]);

        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('users.index.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('users.index.title'));
    });

    it('renders every column header', () => {
        const wrapper = mountPage([]);

        const headers = wrapper.findAll('thead th').map((th) => th.text());
        expect(headers).toEqual([
            t('users.index.columnName'),
            t('users.index.columnEmail'),
            t('users.index.columnRole'),
            t('users.index.columnActions'),
        ]);
    });

    it('shows the empty message when there are no users', () => {
        const wrapper = mountPage([]);

        expect(wrapper.text()).toContain(t('users.index.empty'));
    });

    it('links the "Add user" button to the create page', () => {
        const wrapper = mountPage([]);

        const addLink = wrapper
            .findAll('a')
            .find((a) => a.text() === t('users.index.addUser'));
        expect(addLink?.attributes('href')).toBe('/admin/users/create');
    });

    it('links the help icon to the users help page', () => {
        const wrapper = mountPage([]);

        const helpLink = wrapper
            .findAll('a')
            .find((a) => a.attributes('aria-label') === t('help.viewHelp'));
        expect(helpLink?.attributes('href')).toBe('/admin/help/users');
    });

    it('renders the name and email for a user', () => {
        const wrapper = mountPage([
            user({ id: 2, name: 'John Smith', email: 'john@example.com' }),
        ]);

        const cells = rowCells(wrapper);
        expect(cells[0].text()).toBe('John Smith');
        expect(cells[1].text()).toBe('john@example.com');
    });

    it('shows the admin badge with a shield icon for an admin user', () => {
        const wrapper = mountPage([user({ id: 2, is_admin: true })]);

        const roleCell = rowCells(wrapper)[2];
        expect(roleCell.text()).toBe(t('users.index.roleAdmin'));
        expect(roleCell.findComponent(ShieldCheck).exists()).toBe(true);
    });

    it('shows the mobile-user badge with no shield icon for a non-admin user', () => {
        const wrapper = mountPage([user({ id: 2, is_admin: false })]);

        const roleCell = rowCells(wrapper)[2];
        expect(roleCell.text()).toBe(t('users.index.roleMobile'));
        expect(roleCell.findComponent(ShieldCheck).exists()).toBe(false);
    });

    it("links a user's view action to their actions page", () => {
        const wrapper = mountPage([user({ id: 5 })]);

        const viewLink = wrapper
            .findAll('a')
            .find((a) => a.text().includes(t('users.index.view')));
        expect(viewLink?.attributes('href')).toBe('/admin/users/5');
    });

    it("links a user's edit action to their edit page", () => {
        const wrapper = mountPage([user({ id: 5 })]);

        const editLink = wrapper
            .findAll('a')
            .find((a) => a.text().includes(t('users.index.edit')));
        expect(editLink?.attributes('href')).toBe('/admin/users/5/edit');
    });

    it('shows a delete action for a user that is not the current user', () => {
        const wrapper = mountPage([user({ id: 5 })], 7);

        const deleteButton = wrapper
            .findAll('button')
            .find((b) => b.text().includes(t('users.index.delete')));
        expect(deleteButton?.attributes('href')).toBe('/admin/users/5');
    });

    it('hides the delete action for the currently signed-in user', () => {
        const wrapper = mountPage([user({ id: 7 })], 7);

        const deleteButton = wrapper
            .findAll('button')
            .find((b) => b.text().includes(t('users.index.delete')));
        expect(deleteButton).toBeUndefined();
    });

    it('preselects the current per-page value in the page-size selector', () => {
        const wrapper = mountPage([], 7, 50);

        expect((wrapper.get('select').element as HTMLSelectElement).value).toBe(
            '50',
        );
    });

    it('requests the new page size when the selector changes', async () => {
        const wrapper = mountPage([], 7, 20);

        await wrapper.get('select').setValue('50');

        expect(routerGetMock).toHaveBeenCalledWith(
            '/admin/users',
            { per_page: 50 },
            { preserveState: true, replace: true },
        );
    });
});

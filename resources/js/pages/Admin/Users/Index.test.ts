import { ShieldCheck } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { t } from '@/lib/i18n';
import { rowCells } from '@/testing/dom';
import { paginated } from '@/testing/factories';
import type { User } from '@/types/admin';
import Index from './Index.vue';

const { usePageMock, routerPostMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerPostMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { post: routerPostMock },
    };
});

function user(overrides: Partial<User> = {}): User {
    return {
        id: 1,
        name: 'Jane Doe',
        email: 'jane@example.com',
        is_admin: false,
        ...overrides,
    };
}

function mountPage(users: User[], currentUserId = 7) {
    usePageMock.mockReturnValue({
        url: '/admin/users',
        props: {
            locale: 'en',
            auth: { user: { name: 'Current User', id: currentUserId } },
        },
    });

    return mount(Index, { props: { users: paginated(users) } });
}

describe('Users Index', () => {
    beforeEach(() => {
        usePageMock.mockReset();
        routerPostMock.mockReset();
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
});

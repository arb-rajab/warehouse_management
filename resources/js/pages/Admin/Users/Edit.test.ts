import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SubmitButton from '@/components/SubmitButton.vue';
import UserFormFields from '@/components/UserFormFields.vue';
import { t } from '@/lib/i18n';
import type { User } from '@/types/admin';
import Edit from './Edit.vue';

const { formSlotPropsMock, usePageMock, routerPostMock } = vi.hoisted(() => ({
    formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
    usePageMock: vi.fn(),
    routerPostMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');

    const FormStub = defineComponent({
        props: ['action'],
        setup(props, { slots }) {
            return () =>
                h(
                    'form',
                    {
                        'data-action-url':
                            typeof props.action === 'string'
                                ? props.action
                                : props.action?.url,
                    },
                    slots.default?.(formSlotPropsMock()),
                );
        },
    });

    const LinkStub = defineComponent({
        props: ['href', 'as'],
        setup(props, { slots }) {
            return () =>
                h(
                    props.as ?? 'a',
                    {
                        href:
                            typeof props.href === 'string'
                                ? props.href
                                : props.href?.url,
                    },
                    slots.default?.(),
                );
        },
    });

    return {
        Head: defineComponent({ render: () => null }),
        Form: FormStub,
        Link: LinkStub,
        usePage: usePageMock,
        router: { post: routerPostMock },
    };
});

function user(overrides: Partial<User> = {}): User {
    return {
        id: 5,
        name: 'John Smith',
        email: 'john@example.com',
        is_admin: false,
        ...overrides,
    };
}

function mountPage(userOverrides: Partial<User> = {}, currentUserId = 7) {
    usePageMock.mockReturnValue({
        url: '/admin/users/5/edit',
        props: {
            locale: 'en',
            auth: { user: { name: 'Current User', id: currentUserId } },
        },
    });

    return mount(Edit, { props: { user: user(userOverrides) } });
}

describe('Users Edit', () => {
    beforeEach(() => {
        formSlotPropsMock.mockReset();
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
        usePageMock.mockReset();
        routerPostMock.mockReset();
    });

    it("renders the page title with the user's name", () => {
        const wrapper = mountPage({ name: 'Ada Lovelace' });

        expect(wrapper.text()).toContain(
            t('users.edit.title', { name: 'Ada Lovelace' }),
        );
    });

    it("submits the form to that user's update route", () => {
        const wrapper = mountPage({ id: 9 });

        expect(wrapper.get('form').attributes('data-action-url')).toBe(
            '/admin/users/9',
        );
    });

    it('passes the current name, email, and admin flag to the user form fields', () => {
        const wrapper = mountPage({
            name: 'Ada Lovelace',
            email: 'ada@example.com',
            is_admin: true,
        });

        expect(wrapper.findComponent(UserFormFields).props()).toMatchObject({
            name: 'Ada Lovelace',
            email: 'ada@example.com',
            isAdmin: true,
        });
    });

    it('does not require a password since the user already has one', () => {
        const wrapper = mountPage();

        expect(
            wrapper.findComponent(UserFormFields).props('passwordRequired'),
        ).toBeFalsy();
    });

    it('disables the admin toggle when editing your own account', () => {
        const wrapper = mountPage({ id: 7 }, 7);

        expect(
            wrapper.findComponent(UserFormFields).props('disableAdminToggle'),
        ).toBe(true);
    });

    it("does not disable the admin toggle when editing someone else's account", () => {
        const wrapper = mountPage({ id: 5 }, 7);

        expect(
            wrapper.findComponent(UserFormFields).props('disableAdminToggle'),
        ).toBe(false);
    });

    it('forwards validation errors to the user form fields', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { name: 'The name field is required.' },
            processing: false,
        });

        const wrapper = mountPage();

        expect(wrapper.findComponent(UserFormFields).props('errors')).toEqual({
            name: 'The name field is required.',
        });
    });

    it('shows the submitting label and disables the button while processing', () => {
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: true });

        const wrapper = mountPage();

        expect(wrapper.findComponent(SubmitButton).props('processing')).toBe(
            true,
        );
        expect(wrapper.get('form button').text()).toBe(
            t('users.edit.submitting'),
        );
        expect(wrapper.get('form button').attributes('disabled')).toBeDefined();
    });

    it('shows the default submit label while not processing', () => {
        const wrapper = mountPage();

        expect(wrapper.get('form button').text()).toBe(t('users.edit.submit'));
    });
});

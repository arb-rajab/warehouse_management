import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SubmitButton from '@/components/SubmitButton.vue';
import UserFormFields from '@/components/UserFormFields.vue';
import { t } from '@/lib/i18n';
import Create from './Create.vue';

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

function mountPage() {
    usePageMock.mockReturnValue({
        url: '/admin/users/create',
        props: { locale: 'en', auth: { user: { name: 'Jane Doe', id: 7 } } },
    });

    return mount(Create);
}

describe('Users Create', () => {
    beforeEach(() => {
        formSlotPropsMock.mockReset();
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
        usePageMock.mockReset();
        routerPostMock.mockReset();
    });

    it('renders the page title', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('users.create.title'));
    });

    it('submits the form to the user-creation route', () => {
        const wrapper = mountPage();

        expect(wrapper.get('form').attributes('data-action-url')).toBe(
            '/admin/users',
        );
    });

    it('requires a password since the user is being created', () => {
        const wrapper = mountPage();

        expect(
            wrapper.findComponent(UserFormFields).props('passwordRequired'),
        ).toBe(true);
    });

    it('forwards validation errors to the user form fields', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { email: 'The email has already been taken.' },
            processing: false,
        });

        const wrapper = mountPage();

        expect(wrapper.findComponent(UserFormFields).props('errors')).toEqual({
            email: 'The email has already been taken.',
        });
    });

    it('shows the submitting label and disables the button while processing', () => {
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: true });

        const wrapper = mountPage();

        expect(wrapper.findComponent(SubmitButton).props('processing')).toBe(
            true,
        );
        expect(wrapper.get('form button').text()).toBe(
            t('users.create.submitting'),
        );
        expect(wrapper.get('form button').attributes('disabled')).toBeDefined();
    });

    it('shows the default submit label while not processing', () => {
        const wrapper = mountPage();

        expect(wrapper.get('form button').text()).toBe(
            t('users.create.submit'),
        );
    });

    it('links the cancel action back to the user list', () => {
        const wrapper = mountPage();

        expect(wrapper.get('form a').attributes('href')).toBe('/admin/users');
    });
});

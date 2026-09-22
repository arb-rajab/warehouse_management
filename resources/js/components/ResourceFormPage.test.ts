import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ResourceFormPage from './ResourceFormPage.vue';
import SubmitButton from './SubmitButton.vue';

const { formSlotPropsMock, usePageMock, routerPostMock } = vi.hoisted(() => ({
    formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
    usePageMock: vi.fn(),
    routerPostMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { post: routerPostMock },
    };
});

function mountPage() {
    usePageMock.mockReturnValue({
        url: '/admin/rows',
        props: { locale: 'en', auth: { user: { name: 'Jane Doe', id: 7 } } },
    });

    return mount(ResourceFormPage, {
        props: {
            title: 'Add row',
            action: '/admin/rows',
            submitLabel: 'Create',
            submittingLabel: 'Creating...',
        },
        slots: { default: '<p>form fields go here</p>' },
    });
}

describe('ResourceFormPage', () => {
    beforeEach(() => {
        formSlotPropsMock.mockReset();
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
        usePageMock.mockReset();
        routerPostMock.mockReset();
    });

    it('renders the title as the page heading', () => {
        const wrapper = mountPage();

        expect(wrapper.get('h1').text()).toBe('Add row');
    });

    it('submits the form to the given action', () => {
        const wrapper = mountPage();

        expect(wrapper.get('form').attributes('data-action-url')).toBe(
            '/admin/rows',
        );
    });

    it('renders the default slot inside the form', () => {
        const wrapper = mountPage();

        expect(wrapper.get('form').text()).toContain('form fields go here');
    });

    it('shows the submitting label and disables the button while processing', () => {
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: true });

        const wrapper = mountPage();

        expect(wrapper.findComponent(SubmitButton).props('processing')).toBe(
            true,
        );
        expect(wrapper.get('form button').text()).toBe('Creating...');
        expect(wrapper.get('form button').attributes('disabled')).toBeDefined();
    });

    it('shows the submit label while not processing', () => {
        const wrapper = mountPage();

        expect(wrapper.get('form button').text()).toBe('Create');
    });

    it('renders the header-actions slot next to the title', () => {
        usePageMock.mockReturnValue({
            url: '/admin/rows',
            props: {
                locale: 'en',
                auth: { user: { name: 'Jane Doe', id: 7 } },
            },
        });

        const wrapper = mount(ResourceFormPage, {
            props: {
                title: 'Add row',
                action: '/admin/rows',
                submitLabel: 'Create',
                submittingLabel: 'Creating...',
            },
            slots: {
                default: '<p>form fields go here</p>',
                'header-actions': '<a href="/admin/help/rows">Help</a>',
            },
        });

        const headerActionsLink = wrapper.get('h1').element
            .nextElementSibling as HTMLElement;
        expect(headerActionsLink.textContent).toBe('Help');
    });

    it('renders no cancel link when cancelHref is not given', () => {
        const wrapper = mountPage();

        expect(wrapper.find('form a').exists()).toBe(false);
    });

    it('renders a cancel link to the given href when provided', () => {
        usePageMock.mockReturnValue({
            url: '/admin/rows',
            props: {
                locale: 'en',
                auth: { user: { name: 'Jane Doe', id: 7 } },
            },
        });

        const wrapper = mount(ResourceFormPage, {
            props: {
                title: 'Add row',
                action: '/admin/rows',
                submitLabel: 'Create',
                submittingLabel: 'Creating...',
                cancelHref: '/admin/rows',
            },
            slots: { default: '<p>form fields go here</p>' },
        });

        const cancelLink = wrapper.get('form a');
        expect(cancelLink.attributes('href')).toBe('/admin/rows');
        expect(cancelLink.text()).toBe('Cancel');
    });
});

import type { FormDataConvertible } from '@inertiajs/core';
import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import { index as rowsIndex } from '@/actions/App/Http/Controllers/Admin/RowController';
import PageHeader from '@/components/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
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

function mountPage(extraProps: Record<string, unknown> = {}) {
    usePageMock.mockReturnValue({
        url: rowsIndex.url(),
        props: { locale: 'en', auth: { user: { name: 'Jane Doe', id: 7 } } },
    });

    return mount(ResourceFormPage, {
        props: {
            title: 'Add row',
            action: rowsIndex.url(),
            submitLabel: 'Create',
            submittingLabel: 'Creating...',
            ...extraProps,
        },
        slots: {
            default: '<p>form fields go here</p>',
            'after-form':
                '<section data-test="after-form">Separate form slot</section>',
        },
    });
}

describe('ResourceFormPage', () => {
    beforeEach(() => {
        formSlotPropsMock.mockReset();
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
        usePageMock.mockReset();
        routerPostMock.mockReset();
    });

    it('renders inside AdminLayout by default', () => {
        const wrapper = mountPage();

        expect(wrapper.findComponent(AdminLayout).exists()).toBe(true);
    });

    it('renders inside the given layout instead', () => {
        const OtherLayout = defineComponent({
            name: 'OtherLayout',
            setup:
                (_, { slots }) =>
                () =>
                    h('div', { class: 'other-layout' }, slots.default?.()),
        });

        const wrapper = mountPage({ layout: OtherLayout });

        expect(wrapper.findComponent(AdminLayout).exists()).toBe(false);
        expect(wrapper.get('.other-layout').text()).toContain(
            'form fields go here',
        );
    });

    it('keeps the form narrow unless told to use the full width', () => {
        expect(mountPage().find('.max-w-sm').exists()).toBe(true);
        expect(mountPage({ wide: true }).find('.max-w-sm').exists()).toBe(
            false,
        );
        expect(mountPage({ wide: true }).find('.max-w-none').exists()).toBe(
            true,
        );
    });

    it('renders the title as the page heading', () => {
        const wrapper = mountPage();

        expect(wrapper.findComponent(PageHeader).props('title')).toBe(
            'Add row',
        );
        expect(wrapper.get('h1').text()).toBe('Add row');
    });

    it('submits the form to the given action', () => {
        const wrapper = mountPage();

        expect(wrapper.get('form').attributes('data-action-url')).toBe(
            rowsIndex.url(),
        );
    });

    it('renders the default slot inside the form', () => {
        const wrapper = mountPage();

        expect(wrapper.get('form').text()).toContain('form fields go here');
    });

    it('places a separate action form slot outside the resource form', () => {
        const wrapper = mountPage();
        expect(wrapper.get('[data-test="after-form"]').text()).toBe(
            'Separate form slot',
        );
        expect(
            wrapper.get('form').find('[data-test="after-form"]').exists(),
        ).toBe(false);
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
            url: rowsIndex.url(),
            props: {
                locale: 'en',
                auth: { user: { name: 'Jane Doe', id: 7 } },
            },
        });

        const wrapper = mount(ResourceFormPage, {
            props: {
                title: 'Add row',
                action: rowsIndex.url(),
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

    it('forwards a given transform function to the underlying Form component', () => {
        usePageMock.mockReturnValue({
            url: rowsIndex.url(),
            props: {
                locale: 'en',
                auth: { user: { name: 'Jane Doe', id: 7 } },
            },
        });
        const transform = (data: Record<string, FormDataConvertible>) => data;

        const wrapper = mount(ResourceFormPage, {
            props: {
                title: 'Add row',
                action: rowsIndex.url(),
                submitLabel: 'Create',
                submittingLabel: 'Creating...',
                transform,
            },
            slots: {
                default: '<p>form fields go here</p>',
                'after-form':
                    '<section data-test="after-form">Separate form slot</section>',
            },
        });

        expect(wrapper.findComponent(Form).props('transform')).toBe(transform);
    });

    it('renders no cancel link when cancelHref is not given', () => {
        const wrapper = mountPage();

        expect(wrapper.find('form a').exists()).toBe(false);
    });

    it('renders a cancel link to the given href when provided', () => {
        usePageMock.mockReturnValue({
            url: rowsIndex.url(),
            props: {
                locale: 'en',
                auth: { user: { name: 'Jane Doe', id: 7 } },
            },
        });

        const wrapper = mount(ResourceFormPage, {
            props: {
                title: 'Add row',
                action: rowsIndex.url(),
                submitLabel: 'Create',
                submittingLabel: 'Creating...',
                cancelHref: rowsIndex.url(),
            },
            slots: {
                default: '<p>form fields go here</p>',
                'after-form':
                    '<section data-test="after-form">Separate form slot</section>',
            },
        });

        const cancelLink = wrapper.get('form a');
        expect(cancelLink.attributes('href')).toBe(rowsIndex.url());
        expect(cancelLink.text()).toBe('Cancel');
    });
});

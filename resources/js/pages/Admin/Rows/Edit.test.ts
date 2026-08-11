import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import RowFormFields from '@/components/RowFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { t } from '@/lib/i18n';
import type { Row } from '@/types/admin';
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

function row(overrides: Partial<Row> = {}): Row {
    return {
        id: 1,
        letter: 'A',
        cells_count: 5,
        flats_count: 7,
        has_pallets: false,
        ...overrides,
    };
}

function mountPage(rowOverrides: Partial<Row> = {}) {
    usePageMock.mockReturnValue({
        url: '/admin/rows/A/edit',
        props: { locale: 'en', auth: { user: { name: 'Jane Doe', id: 7 } } },
    });

    return mount(Edit, { props: { row: row(rowOverrides) } });
}

describe('Rows Edit', () => {
    beforeEach(() => {
        formSlotPropsMock.mockReset();
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
        usePageMock.mockReset();
        routerPostMock.mockReset();
    });

    it('renders the page title with the row letter', () => {
        const wrapper = mountPage({ letter: 'B' });

        expect(wrapper.text()).toContain(t('rows.edit.title', { letter: 'B' }));
    });

    it("submits the form to that row's update route", () => {
        const wrapper = mountPage({ letter: 'B' });

        expect(wrapper.get('form').attributes('data-action-url')).toBe(
            '/admin/rows/B',
        );
    });

    it('passes the row values and dimension lock to the row form fields', () => {
        const wrapper = mountPage({
            letter: 'C',
            cells_count: 3,
            flats_count: 4,
            has_pallets: true,
        });

        expect(wrapper.findComponent(RowFormFields).props()).toMatchObject({
            letter: 'C',
            cellsCount: 3,
            flatsCount: 4,
            disableDimensions: true,
        });
    });

    it('forwards validation errors to the row form fields', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { cells_count: 'The cells count field is required.' },
            processing: false,
        });

        const wrapper = mountPage();

        expect(wrapper.findComponent(RowFormFields).props('errors')).toEqual({
            cells_count: 'The cells count field is required.',
        });
    });

    it('shows the submitting label and disables the button while processing', () => {
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: true });

        const wrapper = mountPage();

        expect(wrapper.findComponent(SubmitButton).props('processing')).toBe(
            true,
        );
        expect(wrapper.get('form button').text()).toBe(
            t('rows.edit.submitting'),
        );
        expect(wrapper.get('form button').attributes('disabled')).toBeDefined();
    });

    it('shows the default submit label while not processing', () => {
        const wrapper = mountPage();

        expect(wrapper.get('form button').text()).toBe(t('rows.edit.submit'));
    });
});

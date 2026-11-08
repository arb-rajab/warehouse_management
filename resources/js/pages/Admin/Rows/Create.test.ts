import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    create as rowsCreate,
    index as rowsIndex,
} from '@/actions/App/Http/Controllers/Admin/RowController';
import RowFormFields from '@/components/RowFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { t } from '@/lib/i18n';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import Create from './Create.vue';

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
        url: rowsCreate.url(),
        props: defaultAuthProps(),
    });

    return mount(Create, {
        props: {
            warehouses: [
                { id: 1, name: 'Main' },
                { id: 5, name: 'Annex' },
            ],
        },
    });
}

describe('Rows Create', () => {
    beforeEach(() => {
        resetMocks({ formSlotPropsMock, usePageMock, routerPostMock });
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });

    it('renders the page title', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('rows.create.title'));
    });

    it('submits the form to the row-creation route', () => {
        const wrapper = mountPage();

        expect(wrapper.get('form').attributes('data-action-url')).toBe(
            rowsIndex.url(),
        );
    });

    it('forwards validation errors to the row form fields', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { letter: 'The letter has already been taken.' },
            processing: false,
        });

        const wrapper = mountPage();

        expect(wrapper.findComponent(RowFormFields).props('errors')).toEqual({
            letter: 'The letter has already been taken.',
        });
    });

    it('offers every warehouse in a required warehouse select that posts warehouse_id', () => {
        const wrapper = mountPage();

        const select = wrapper.get<HTMLSelectElement>('#warehouse_id');
        expect(select.attributes('name')).toBe('warehouse_id');
        expect(select.element.required).toBe(true);
        expect(select.findAll('option').map((option) => option.text())).toEqual(
            [t('store.reference.select'), 'Main', 'Annex'],
        );
        expect(wrapper.get('label[for="warehouse_id"]').text()).toContain(
            t('rows.fields.warehouse'),
        );
    });

    it('forwards a warehouse validation error to the warehouse select', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { warehouse_id: 'The warehouse id field is required.' },
            processing: false,
        });

        const wrapper = mountPage();

        expect(wrapper.text()).toContain('The warehouse id field is required.');
    });

    it('shows the submitting label and disables the button while processing', () => {
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: true });

        const wrapper = mountPage();

        expect(wrapper.findComponent(SubmitButton).props('processing')).toBe(
            true,
        );
        expect(wrapper.get('form button').text()).toBe(
            t('rows.create.submitting'),
        );
        expect(wrapper.get('form button').attributes('disabled')).toBeDefined();
    });

    it('shows the default submit label while not processing', () => {
        const wrapper = mountPage();

        expect(wrapper.get('form button').text()).toBe(t('rows.create.submit'));
    });

    it('links the cancel action back to the row list', () => {
        const wrapper = mountPage();

        expect(wrapper.get('form a').attributes('href')).toBe(rowsIndex.url());
    });
});

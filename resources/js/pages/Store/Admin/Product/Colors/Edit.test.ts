import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    edit,
    index as catalogueIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/ColorController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreColorFormFields from '@/components/StoreColorFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeColor, storeSharedProps } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import Edit from './Edit.vue';

const { usePageMock, routerPostMock, formSlotPropsMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerPostMock: vi.fn(),
    formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, createFormStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        Form: createFormStub(formSlotPropsMock),
        usePage: usePageMock,
        router: { post: routerPostMock },
    };
});
const record = storeColor();
function mountPage() {
    usePageMock.mockReturnValue({
        url: edit.url(record),
        props: defaultAuthProps({ store: storeSharedProps() }),
    });

    return mount(Edit, { props: { color: record } });
}
describe('Store Colors Edit', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, routerPostMock, formSlotPropsMock });
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });
    it('passes the layout, labels, routes and cancel target to the resource form', () => {
        const wrapper = mountPage();
        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            title: t('store.colors.edit.title'),
            action: update(record),
            submitLabel: t('store.colors.fields.save'),
            submittingLabel: t('store.colors.fields.saving'),
            cancelHref: catalogueIndex(),
            layout: StoreAdminLayout,
        });
        expect(wrapper.get('h1').text()).toBe(t('store.colors.edit.title'));
        expect(wrapper.text()).toContain(t('common.cancel'));
        expect(wrapper.getComponent(Form).props('action')).toEqual(
            update(record),
        );
        expect(
            wrapper.getComponent(StoreColorFormFields).props(),
        ).toMatchObject({ name: record.name, code: record.code });
    });
    it('forwards validation errors and processing to the fields and submit button', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { name: 'Invalid.' },
            processing: true,
        });
        const wrapper = mountPage();
        expect(
            wrapper.getComponent(StoreColorFormFields).props('errors'),
        ).toEqual({ name: 'Invalid.' });
        expect(wrapper.getComponent(SubmitButton).props()).toMatchObject({
            processing: true,
            processingLabel: t('store.colors.fields.saving'),
        });
    });
});

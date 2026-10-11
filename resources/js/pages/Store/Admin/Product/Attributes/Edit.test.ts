import { Form, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    edit,
    index as catalogueIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/AttributeController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreAttributeFormFields from '@/components/StoreAttributeFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeAttribute, storeSharedProps } from '@/testing/factories';
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
const record = storeAttribute();
function mountPage() {
    usePageMock.mockReturnValue({
        url: edit.url(record),
        props: defaultAuthProps({ store: storeSharedProps() }),
    });

    return mount(Edit, {
        props: {
            attribute: record,
            lang: 'ar',
            name: 'المقاس',
            languages: [
                { code: 'en', name: 'English' },
                { code: 'ar', name: 'Arabic' },
            ],
        },
    });
}
describe('Store Attributes Edit', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, routerPostMock, formSlotPropsMock });
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });
    it('passes the layout, labels, routes and cancel target to the resource form', () => {
        const wrapper = mountPage();
        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            title: t('store.attributes.edit.title', { name: record.name }),
            action: update(record),
            submitLabel: t('store.attributes.fields.save'),
            submittingLabel: t('store.attributes.fields.saving'),
            cancelHref: catalogueIndex(),
            layout: StoreAdminLayout,
        });
        expect(wrapper.get('h1').text()).toBe(
            t('store.attributes.edit.title', { name: record.name }),
        );
        expect(wrapper.text()).toContain(t('common.cancel'));
        expect(wrapper.getComponent(Form).props('action')).toEqual(
            update(record),
        );
        expect(
            wrapper.getComponent(StoreAttributeFormFields).props(),
        ).toMatchObject({ name: 'المقاس' });
    });
    it('forwards validation errors and processing to the fields and submit button', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { name: 'Invalid.' },
            processing: true,
        });
        const wrapper = mountPage();
        expect(
            wrapper.getComponent(StoreAttributeFormFields).props('errors'),
        ).toEqual({ name: 'Invalid.' });
        expect(wrapper.getComponent(SubmitButton).props()).toMatchObject({
            processing: true,
            processingLabel: t('store.attributes.fields.saving'),
        });
    });

    it('preserves the selected database translation and language navigation', () => {
        const wrapper = mountPage();
        expect(wrapper.get('input[name=lang]').attributes('value')).toBe('ar');
        expect(
            wrapper
                .get('nav[aria-label="Translation language"]')
                .attributes('aria-label'),
        ).toBe(t('store.attributes.edit.languages'));
        expect(wrapper.text()).toContain(
            t('store.attributes.edit.translationHint'),
        );
        const links = wrapper
            .findAllComponents(Link)
            .filter((link) => ['English', 'Arabic'].includes(link.text()));
        expect(links.map((link) => link.props('href'))).toEqual([
            edit(record, { query: { lang: 'en' } }),
            edit(record, { query: { lang: 'ar' } }),
        ]);
        expect(links[1].attributes('aria-current')).toBe('page');
    });
});

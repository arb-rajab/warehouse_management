import { Form, Link } from '@inertiajs/vue3';
import { Languages } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    edit as rolesEdit,
    index as rolesIndex,
    update as rolesUpdate,
} from '@/actions/App/Http/Controllers/Store/Admin/RoleController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreRoleFormFields from '@/components/StoreRoleFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import {
    permissionGroup,
    storeRole,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type { StoreLanguage } from '@/types/store';
import Edit from './Edit.vue';

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

const role = storeRole({ id: 3, name: 'Clerk' });
const groups = [permissionGroup()];
const languages: StoreLanguage[] = [
    { code: 'en', name: 'English' },
    { code: 'ar', name: 'Arabic' },
];

function mountPage(
    props: Partial<{
        lang: string;
        name: string;
        languages: StoreLanguage[];
    }> = {},
) {
    usePageMock.mockReturnValue({
        url: rolesEdit.url(role),
        props: defaultAuthProps({ store: storeSharedProps() }),
    });

    return mount(Edit, {
        props: {
            role,
            lang: 'en',
            name: 'Clerk',
            languages,
            permissionGroups: groups,
            permissionIds: [2],
            ...props,
        },
    });
}

function languageLinks(wrapper: ReturnType<typeof mountPage>) {
    return wrapper
        .findAllComponents(Link)
        .filter((link) => languages.some((l) => l.name === link.text()));
}

describe('Store roles Edit', () => {
    beforeEach(() => {
        resetMocks({ formSlotPropsMock, usePageMock, routerPostMock });
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });

    it("renders a full-width form page in the store layout, titled with the role's name", () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            title: t('store.roles.edit.title', { name: 'Clerk' }),
            submitLabel: t('store.roles.edit.submit'),
            submittingLabel: t('store.roles.edit.submitting'),
            layout: StoreAdminLayout,
            wide: true,
        });
        expect(wrapper.get('h1').text()).toBe('Edit Clerk');
        expect(wrapper.getComponent(SubmitButton).text()).toBe(
            t('store.roles.edit.submit'),
        );
    });

    it("submits the edited language with the role's update and cancels back to the list", () => {
        const wrapper = mountPage({ lang: 'ar' });

        expect(wrapper.getComponent(Form).props('action')).toEqual(
            rolesUpdate(role),
        );
        expect(
            wrapper
                .get('input[type="hidden"][name="lang"]')
                .attributes('value'),
        ).toBe('ar');
        expect(
            wrapper
                .findAllComponents(Link)
                .find((link) => link.text() === t('common.cancel'))
                ?.props('href'),
        ).toEqual(rolesIndex());
    });

    it("fills the fields in with the language's name and the role's permissions", () => {
        expect(
            mountPage({ lang: 'ar', name: 'كاتب' })
                .getComponent(StoreRoleFormFields)
                .props(),
        ).toEqual({
            permissionGroups: groups,
            name: 'كاتب',
            checkedPermissionIds: [2],
            errors: {},
        });
    });

    it('links each language to its own edit page and marks the current one', () => {
        const wrapper = mountPage({ lang: 'ar' });
        const links = languageLinks(wrapper);

        expect(links.map((link) => link.props('href'))).toEqual([
            rolesEdit(role, { query: { lang: 'en' } }),
            rolesEdit(role, { query: { lang: 'ar' } }),
        ]);
        expect(links.map((link) => link.attributes('aria-current'))).toEqual([
            undefined,
            'page',
        ]);
        expect(
            wrapper.findAll('nav').map((nav) => nav.attributes('aria-label')),
        ).toContain(t('store.roles.edit.languages'));
        expect(wrapper.findComponent(Languages).exists()).toBe(true);
        expect(wrapper.text()).toContain(
            t('store.roles.fields.nameTranslationHint'),
        );
    });

    it('shows no language switch for a store with a single language', () => {
        const wrapper = mountPage({
            languages: [{ code: 'en', name: 'English' }],
        });

        expect(languageLinks(wrapper)).toHaveLength(0);
        expect(wrapper.text()).not.toContain(
            t('store.roles.fields.nameTranslationHint'),
        );
    });

    it('forwards validation errors to the fields', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { name: 'The name field is required.' },
            processing: false,
        });

        expect(
            mountPage().getComponent(StoreRoleFormFields).props('errors'),
        ).toEqual({ name: 'The name field is required.' });
    });
});

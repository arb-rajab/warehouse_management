import { Form, Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    bulkDestroy,
    create,
    destroy,
    edit,
    index as popupsIndex,
    updateStatus,
} from '@/actions/App/Http/Controllers/Store/Admin/DynamicPopupController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import { t } from '@/lib/i18n';
import { paginated, storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Page from './Index.vue';
const { usePageMock, routerGetMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Form: createFormStub(() => ({ errors: {}, processing: false })),
        Link: createLinkStub(),
        Head: headStub,
        usePage: usePageMock,
        router: { post: vi.fn(), get: routerGetMock },
    };
});
const popup = {
    id: 1,
    title: 'Welcome',
    banner_url: '/image.webp',
    btn_link: '/temp/product/example',
    status: false,
};
function mountPage(
    permissions = [
        'view_all_dynamic_popups',
        'add_dynamic_popups',
        'edit_dynamic_popups',
        'delete_dynamic_popups',
        'publish_dynamic_popups',
    ],
) {
    usePageMock.mockReturnValue({
        url: popupsIndex.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Page, {
        props: {
            popups: paginated([popup]),
            filters: { per_page: 20, search: 'Welcome' },
        },
    });
}
afterEach(() => vi.restoreAllMocks());
describe('Store popup Index', () => {
    it('renders labels links publication and selection controls for authorized staff', () => {
        const wrapper = mountPage();
        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('store.dynamicPopups.index.title'),
        );
        expect(wrapper.getComponent(AddResourceLink).props('href')).toEqual(
            create(),
        );

        for (const key of [
            'select',
            'image',
            'link',
            'status',
            'actions',
            'search',
            'deleteSelection',
            'hidden',
            'publish',
            'edit',
            'delete',
            'selectAll',
        ]) {
            expect(wrapper.text()).toContain(
                t(`store.dynamicPopups.index.${key}`),
            );
        }

        const links = wrapper.findAllComponents(TableActionLink);
        expect(links.map((link) => link.props('href'))).toEqual([
            edit(popup),
            destroy(popup, { mergeQuery: {} }),
        ]);
        expect(wrapper.get('tbody img').attributes('src')).toBe('/image.webp');
        expect(
            wrapper.findAllComponents(Form).map((form) => form.props('action')),
        ).toEqual([
            popupsIndex(),
            bulkDestroy({ mergeQuery: {} }),
            updateStatus({ mergeQuery: {} }),
        ]);
    });
    it('asks before publishing or deleting and sends authoritative next status', () => {
        const wrapper = mountPage();
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const publication = wrapper.findAllComponents(Form).at(-1)!;
        const before = publication.props('onBefore') as () => boolean;
        expect(before()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('store.dynamicPopups.index.confirmPublish'),
        );
        expect(
            publication.get('input[name="status"]').attributes('value'),
        ).toBe('1');
        const deleteLink = wrapper
            .findAllComponents(TableActionLink)[1]
            .getComponent(Link);
        expect((deleteLink.props('onBefore') as () => boolean)()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: 'Welcome' }),
        );
    });
    it('tracks checkbox selection in Vue and clears it after bulk success', async () => {
        const wrapper = mountPage();
        await wrapper.get('tbody input[type="checkbox"]').setValue(true);
        const bulk = wrapper.findAllComponents(Form)[1];
        expect(bulk.get('input[name="id[]"]').attributes('value')).toBe('1');
        bulk.vm.$emit('success');
        await wrapper.vm.$nextTick();
        expect(bulk.find('input[name="id[]"]').exists()).toBe(false);
    });
    it('hides writes without their permissions and preserves search during pagination', () => {
        const wrapper = mountPage(['view_all_dynamic_popups']);
        expect(wrapper.findComponent(AddResourceLink).exists()).toBe(false);
        expect(wrapper.findAllComponents(TableActionLink)).toHaveLength(0);
        expect(wrapper.findAllComponents(Form)).toHaveLength(1);
        wrapper.getComponent(Pagination).vm.$emit('update:perPage', 50);
        expect(routerGetMock).toHaveBeenCalledWith(
            popupsIndex.url(),
            { per_page: 50, search: 'Welcome' },
            { preserveState: true, replace: true },
        );
    });
    it('clears bulk selection when a different list page loads so hidden rows are not deleted', async () => {
        const wrapper = mountPage();
        await wrapper.get('tbody input[type="checkbox"]').setValue(true);
        expect(
            wrapper
                .findAllComponents(Form)[1]
                .find('input[name="id[]"]')
                .exists(),
        ).toBe(true);
        await wrapper.setProps({
            popups: paginated([{ ...popup, id: 2, title: 'Next page' }]),
        });
        expect(
            wrapper
                .findAllComponents(Form)[1]
                .find('input[name="id[]"]')
                .exists(),
        ).toBe(false);
    });
});

import { Form, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import {
    index as campaignIndex,
    edit,
    destroy,
} from '@/actions/App/Http/Controllers/Store/Admin/FlashDealController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import { t } from '@/lib/i18n';
import {
    storeFlashDeal,
    paginated,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Index from './Index.vue';
const { usePageMock, getMock, confirmMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    getMock: vi.fn(),
    confirmMock: vi.fn(),
}));
vi.mock('@/lib/confirm', () => ({
    confirmDelete: confirmMock,
    confirmLogout: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Form: createFormStub(() => ({ errors: {}, processing: false })),
        Link: createLinkStub(),
        Head: headStub,
        usePage: usePageMock,
        router: { get: getMock, post: vi.fn() },
    };
});
const record = storeFlashDeal();
function mountPage(
    permissions = [
        'add_flash_deal',
        'edit_flash_deal',
        'delete_flash_deal',
        'publish_flash_deal',
    ],
    empty = false,
) {
    usePageMock.mockReturnValue({
        url: campaignIndex.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, {
        props: {
            deals: paginated(empty ? [] : [record]),
            filters: { search: 'Test', per_page: 10 },
        },
    });
}
beforeEach(() => vi.clearAllMocks());
it('renders every label, gates each write action and passes full route objects with confirmation', () => {
    const wrapper = mountPage();
    expect(wrapper.get('h1').text()).toBe(t('store.flashDeals.index.title'));
    expect(wrapper.findAll('thead th').map((h) => h.text())).toEqual(
        ['title', 'startDate', 'endDate', 'active', 'featured', 'actions'].map(
            (k) => t(`store.campaigns.fields.${k}`),
        ),
    );
    expect(wrapper.text()).toContain(record.title);
    expect(wrapper.findComponent(AddResourceLink).exists()).toBe(true);
    const links = wrapper.findAllComponents(TableActionLink);
    expect(links[0].props('href')).toEqual(edit(record));
    expect(links[1].props('href')).toEqual(destroy(record, { mergeQuery: {} }));
    expect(links[1].attributes('method')).toBe('delete');
    (links[1].getComponent(Link).props('onBefore') as () => boolean)();
    expect(confirmMock).toHaveBeenCalledWith(record.title);
    expect(wrapper.findAllComponents(Form)).toHaveLength(3);
    const refused = mountPage([]);
    expect(refused.findComponent(AddResourceLink).exists()).toBe(false);
    expect(refused.findAllComponents(TableActionLink)).toHaveLength(0);
    expect(refused.findAllComponents(Form)).toHaveLength(1);
    expect(mountPage([], true).text()).toContain(
        t('store.campaigns.fields.empty'),
    );
});
it('preserves search when paging and keeps public navigation a plain link', () => {
    const wrapper = mountPage();
    const pager = wrapper.getComponent(Pagination);
    expect(pager.props('perPage')).toBe(10);
    pager.vm.$emit('update:per-page', 50);
    expect(getMock).toHaveBeenCalledWith(
        campaignIndex.url(),
        { per_page: 50, search: 'Test' },
        { preserveState: true, replace: true },
    );
    expect(wrapper.find('a[href="/temp/flash-deal/flash-deal"]').exists()).toBe(
        true,
    );
});

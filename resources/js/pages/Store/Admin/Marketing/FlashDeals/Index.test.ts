import { Form, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import {
    index as campaignIndex,
    edit,
    destroy,
    update_featured,
    update_status,
} from '@/actions/App/Http/Controllers/Store/Admin/FlashDealController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import Pagination from '@/components/Pagination.vue';
import StoreStatusSwitch from '@/components/StoreStatusSwitch.vue';
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
it('shows the active and featured flags as switches posting the inverse value', () => {
    const wrapper = mountPage();
    const switches = wrapper.findAllComponents(StoreStatusSwitch);
    expect(switches.map((item) => item.props())).toMatchObject([
        {
            action: update_status(record, { mergeQuery: {} }),
            checked: record.status,
            inputName: 'value',
            label: t('store.campaigns.toggle', {
                field: t('store.campaigns.fields.active'),
                title: record.title,
            }),
        },
        {
            action: update_featured(record, { mergeQuery: {} }),
            checked: record.featured,
            inputName: 'value',
            label: t('store.campaigns.toggle', {
                field: t('store.campaigns.fields.featured'),
                title: record.title,
            }),
        },
    ]);
    expect(switches[0].get('[role="switch"]').attributes('aria-checked')).toBe(
        String(record.status),
    );
    expect(switches[0].get('input[name=value]').attributes('value')).toBe(
        record.status ? '0' : '1',
    );
    const editOnly = mountPage(['edit_flash_deal']);
    expect(editOnly.findAllComponents(StoreStatusSwitch)).toHaveLength(1);
});
it('shows each flag as translated text without its permission', () => {
    const wrapper = mountPage([]);
    const cells = wrapper.findAll('tbody td');
    expect(wrapper.findComponent(StoreStatusSwitch).exists()).toBe(false);
    expect(cells[3].text()).toBe(
        t(
            record.status
                ? 'store.campaigns.enabled'
                : 'store.campaigns.disabled',
        ),
    );
    expect(cells[4].text()).toBe(
        t(
            record.featured
                ? 'store.campaigns.enabled'
                : 'store.campaigns.disabled',
        ),
    );
    expect(wrapper.text()).not.toContain('{t(');
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

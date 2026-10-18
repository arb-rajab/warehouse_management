import { Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import {
    index as visitsIndex,
    show_all_visits_in_a_day,
} from '@/actions/App/Http/Controllers/Store/Admin/VisitController';
import FormField from '@/components/FormField.vue';
import StoreVisitMap from '@/components/StoreVisitMap.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import {
    representativeVisitMarker,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import RepShow from './RepShow.vue';
const { usePageMock, getMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    getMock: vi.fn(),
}));
vi.mock(
    'leaflet',
    async () => (await import('@/testing/leafletMock')).leafletMock,
);
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { get: getMock },
    };
});
const rep = { id: 18, name: 'Aya' };
beforeEach(() => {
    vi.clearAllMocks();
    usePageMock.mockReturnValue({
        url: '/temp/admin/representatives/visits/rep-show/18',
        props: defaultAuthProps({
            store: storeSharedProps(),
            errors: { date: 'Invalid day' },
        }),
    });
});
it('full mounts the day view, passes the ranked markers and filters through Wayfinder', async () => {
    const markers = [representativeVisitMarker()];
    const wrapper = mount(RepShow, {
        props: { rep, date: '2026-10-09', visitCount: 2, mapMarkers: markers },
    });
    expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
    expect(wrapper.getComponent(Head).props('title')).toBe(
        t('store.representatives.visits.dayTitle', { name: 'Aya' }),
    );
    expect(wrapper.text()).toContain(
        t('store.representatives.visits.filterDate'),
    );
    expect(wrapper.text()).toContain(t('common.filters.apply'));
    expect(wrapper.text()).toContain(t('store.representatives.visits.back'));
    expect(wrapper.getComponent(TableActionLink).props('href')).toEqual(
        visitsIndex(),
    );
    expect(wrapper.getComponent(StoreVisitMap).props('markers')).toEqual(
        markers,
    );
    expect(
        wrapper
            .findAllComponents(FormField)
            .find((field) => field.props('id') === 'date')
            ?.props('error'),
    ).toBe('Invalid day');
    expect(wrapper.get('#date').attributes()).toMatchObject({
        type: 'date',
        name: 'date',
        value: '2026-10-09',
    });
    expect(wrapper.get('#date').classes()).toContain(
        'dark:[color-scheme:dark]',
    );
    await wrapper.get('#date').setValue('2026-10-10');
    await wrapper.get('form').trigger('submit');
    expect(getMock).toHaveBeenCalledWith(
        show_all_visits_in_a_day.url(rep),
        { date: '2026-10-10' },
        { preserveState: true, preserveScroll: true, replace: true },
    );
    wrapper.unmount();
});
it('shows both empty-day labels without initializing Leaflet, and retains the map for unlocated visits', async () => {
    const wrapper = mount(RepShow, {
        props: { rep, date: '2026-10-09', visitCount: 0, mapMarkers: [] },
    });
    expect(wrapper.text()).toContain(
        t('store.representatives.visits.emptyDay'),
    );
    expect(wrapper.text()).toContain(t('store.representatives.visits.tryDate'));
    expect(wrapper.findComponent(StoreVisitMap).exists()).toBe(false);
    await wrapper.setProps({ visitCount: 1 });
    expect(wrapper.getComponent(StoreVisitMap).props('markers')).toEqual([]);
    wrapper.unmount();
});

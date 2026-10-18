import { Form, Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import { edit as customerEdit } from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import { show as orderShow } from '@/actions/App/Http/Controllers/Store/Admin/OrderController';
import {
    index as visitsIndex,
    show_all_visits_in_a_day,
    update_note,
} from '@/actions/App/Http/Controllers/Store/Admin/VisitController';
import FieldError from '@/components/FieldError.vue';
import StoreVisitMap from '@/components/StoreVisitMap.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableLink from '@/components/TableLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import {
    representativeVisitDetail,
    representativeVisitMarker,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Show from './Show.vue';

const { usePageMock, formState } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    formState: vi.fn(() => ({
        errors: {} as Record<string, string>,
        processing: false,
        recentlySuccessful: false,
    })),
}));
vi.mock(
    'leaflet',
    async () => (await import('@/testing/leafletMock')).leafletMock,
);
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, createFormStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        Form: createFormStub(formState),
        usePage: usePageMock,
    };
});
function access(
    permissions = [
        'view_representative_visits',
        'edit_customer',
        'view_order_details',
    ],
) {
    usePageMock.mockReturnValue({
        url: '/temp/admin/representatives/visits/show/81',
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });
}
beforeEach(() => {
    vi.clearAllMocks();
    access();
    formState.mockReturnValue({
        errors: {},
        processing: false,
        recentlySuccessful: false,
    });
});
it('full mounts the detail with every label, related Inertia links, the map and the note action', () => {
    const visit = representativeVisitDetail();
    const markers = [representativeVisitMarker()];
    const wrapper = mount(Show, {
        props: { visit, mapMarkers: markers, canUpdateNote: true },
    });
    expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
    expect(wrapper.getComponent(Head).props('title')).toBe(
        t('store.representatives.visits.details'),
    );

    for (const key of [
        'details',
        'back',
        'customer',
        'company',
        'email',
        'phone',
        'serial',
        'accountId',
        'date',
        'order',
        'status',
        'orderDate',
        'amount',
        'paymentMethod',
        'customerNotes',
        'customerOtajerId',
        'deliveryDate',
        'note',
        'saveNote',
    ]) {
        expect(wrapper.text()).toContain(
            t(`store.representatives.visits.${key}`),
        );
    }

    expect(wrapper.getComponent(StoreVisitMap).props('markers')).toEqual(
        markers,
    );
    expect(wrapper.getComponent(TableActionLink).props('href')).toEqual(
        visitsIndex(),
    );
    expect(
        wrapper.findAllComponents(TableLink).map((link) => link.props('href')),
    ).toEqual([
        show_all_visits_in_a_day(visit.representative!, {
            query: { date: '2026-10-09' },
        }),
        customerEdit(visit.customer!),
        orderShow['/temp/admin/orders/{order}/show'](visit.order!.id),
    ]);
    expect(wrapper.getComponent(Form).props()).toMatchObject({
        action: update_note['/temp/admin/representatives/visits/{visit}/note'](
            visit,
        ),
        options: { preserveScroll: true },
    });
    expect(wrapper.get('#note').attributes()).toMatchObject({
        name: 'note',
        rows: '4',
    });
    expect(wrapper.get('#note').element).toHaveProperty('value', 'Visit note');
    expect(wrapper.get('#note').attributes('required')).toBeUndefined();
    expect(wrapper.getComponent(SubmitButton).props('processingLabel')).toBe(
        t('store.representatives.visits.saving'),
    );
    wrapper.unmount();
});
it.each([
    [false, true],
    [true, false],
    [false, false],
])('gates the editor by ownership %s and permission %s', (own, allowed) => {
    access(allowed ? ['view_representative_visits'] : []);
    const wrapper = mount(Show, {
        props: {
            visit: representativeVisitDetail({
                note: '<script>alert(1)</script>',
                customer: null,
                order: null,
            }),
            mapMarkers: [],
            canUpdateNote: own,
        },
    });
    expect(wrapper.findComponent(Form).exists()).toBe(false);
    expect(wrapper.find('script').exists()).toBe(false);
    expect(wrapper.text()).toContain('<script>alert(1)</script>');
    expect(wrapper.text()).toContain(t('store.representatives.visits.noOrder'));
    expect(wrapper.text()).toContain(
        t('store.representatives.visits.missingCustomer'),
    );
    expect(wrapper.findAllComponents(TableLink)).toHaveLength(allowed ? 1 : 0);
    wrapper.unmount();
});
it('renders escaped customer notes, field errors, progress and the saved status', () => {
    formState.mockReturnValue({
        errors: { note: 'Invalid note' },
        processing: true,
        recentlySuccessful: true,
    });
    const visit = representativeVisitDetail();
    visit.order!.customer_notes = '<img src=x onerror=alert(1)>';
    const wrapper = mount(Show, {
        props: { visit, mapMarkers: [], canUpdateNote: true },
    });
    expect(
        wrapper
            .findAllComponents(FieldError)
            .map((field) => field.props('message'))
            .find(Boolean),
    ).toBe('Invalid note');
    expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(true);
    expect(wrapper.text()).toContain(t('store.representatives.visits.saved'));
    expect(wrapper.get('[data-testid="visit-order"]').text()).toContain(
        visit.order!.customer_notes,
    );
    expect(wrapper.find('[data-testid="visit-order"] img').exists()).toBe(
        false,
    );
    wrapper.unmount();
});

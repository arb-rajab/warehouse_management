import { Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { edit as customerEdit } from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import { show as tripsShow } from '@/actions/App/Http/Controllers/Store/Admin/TripCheckpointController';
import { destroy } from '@/actions/App/Http/Controllers/Store/Admin/TripController';
import {
    create as createFromMap,
    store as storeFromMap,
} from '@/actions/App/Http/Controllers/Store/Admin/TripMapController';
import { markDelivered } from '@/actions/App/Http/Controllers/Store/Admin/TripOrderController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import StoreTripMap from '@/components/StoreTripMap.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableLink from '@/components/TableLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import {
    storeSharedProps,
    storeTripMapOrder,
    storeTripMapTruck,
    truckPhoto,
} from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type { StoreTripMapOrder, StoreTripMapTruck } from '@/types/store';
import CreateFromMap from './CreateFromMap.vue';

const { usePageMock, submitMock, useFormStateMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    submitMock: vi.fn(),
    useFormStateMock: vi.fn(() => ({
        errors: {} as Record<string, string>,
        processing: false,
    })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, createUseFormStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
        useForm: createUseFormStub(submitMock, useFormStateMock),
    };
});
// The page mounts the real StoreTripMap; Leaflet itself is a stand-in, as in
// StoreTripMap.test.ts (jsdom has no layout for it).
vi.mock('leaflet', () => {
    const layer = () => {
        const self: Record<string, unknown> = {
            getElement: () => undefined,
        };

        for (const name of [
            'addTo',
            'on',
            'remove',
            'setIcon',
            'fitBounds',
            'setView',
        ]) {
            self[name] = () => self;
        }

        return self;
    };

    return {
        map: vi.fn(layer),
        tileLayer: vi.fn(layer),
        marker: vi.fn(layer),
        icon: vi.fn(() => ({})),
        latLngBounds: vi.fn(() => ({})),
    };
});

const allPermissions = [
    'add_trip',
    'edit_trip',
    'delete_trip',
    'edit_customer',
];
const alma = storeTripMapOrder();
const bram = storeTripMapOrder({
    id: 78,
    code: '20261017-0002',
    latitude: 51.9,
    longitude: 4.48,
    postal_code: null,
    address: null,
    customer: { id: 22, name: null, phone: null, accounting_id: null },
});
const freeTruck = storeTripMapTruck();
const plainTruck = storeTripMapTruck({
    id: 12,
    name: 'Plain truck',
    max_pallets_number: null,
    photo: null,
});
const busyTruck = storeTripMapTruck({
    id: 13,
    name: 'Busy truck',
    max_pallets_number: '7',
    photo: 603,
    trip: {
        id: 41,
        code: 'LATEST',
        driver: { id: 31, name: 'Sam Driver' },
    },
    open_trips: [
        {
            id: 41,
            code: 'LATEST',
            stops: [
                { id: 501, customer: { id: 9, name: 'Depot' } },
                { id: 502, customer: null },
            ],
        },
        {
            id: 40,
            code: 'OLDER',
            stops: [{ id: 503, customer: { id: 23, name: 'Cas Customer' } }],
        },
    ],
});
const idleTruck = storeTripMapTruck({
    id: 14,
    name: 'Idle busy truck',
    photo: null,
    trip: { id: 42, code: 'EMPTY', driver: null },
    open_trips: [{ id: 42, code: 'EMPTY', stops: [] }],
});

function mountPage({
    orders = [alma, bram],
    orderTrips = { 77: 41 },
    availableTrucks = [freeTruck, plainTruck],
    busyTrucks = [busyTruck, idleTruck],
    permissions = allPermissions,
}: {
    orders?: StoreTripMapOrder[];
    orderTrips?: Record<number, number>;
    availableTrucks?: StoreTripMapTruck[];
    busyTrucks?: StoreTripMapTruck[];
    permissions?: string[];
} = {}) {
    usePageMock.mockReturnValue({
        url: createFromMap.url(),
        props: defaultAuthProps({
            store: storeSharedProps({ permissions }),
        }),
    });

    return mount(CreateFromMap, {
        props: {
            orders,
            orderTrips,
            availableTrucks,
            busyTrucks,
            photos: [
                truckPhoto({
                    id: 601,
                    url: 'https://cdn.example.com/cargo.jpg',
                }),
                truckPhoto({ id: 603, url: null }),
            ],
        },
    });
}

function card(wrapper: VueWrapper, name: string) {
    return wrapper
        .findAll('li')
        .find(
            (item) =>
                item.find('h3').exists() && item.get('h3').text() === name,
        )!;
}
function stopItems(wrapper: VueWrapper) {
    return wrapper.findAll('section[aria-labelledby="trip-map-stops"] ol > li');
}
async function select(wrapper: VueWrapper, ...ids: number[]) {
    for (const id of ids) {
        wrapper.getComponent(StoreTripMap).vm.$emit('toggle', id);
        await nextTick();
    }
}
function assignButton(wrapper: VueWrapper, name: string) {
    return card(wrapper, name).find('button');
}

describe('Store trip map picker', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, submitMock });
        useFormStateMock.mockReturnValue({ errors: {}, processing: false });
    });
    afterEach(() => {
        vi.restoreAllMocks();
        i18n.global.locale.value = 'en';
    });

    it.each(['en', 'ar'] as const)(
        'renders the layout, title, help, sections and their empty labels in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountPage();

            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
            expect(wrapper.getComponent(Head).props('title')).toBe(
                t('store.trips.map.title'),
            );
            expect(wrapper.get('h1').text()).toBe(t('store.trips.map.title'));
            expect(wrapper.text()).toContain(t('store.trips.map.help'));
            expect(wrapper.findAll('h2').map((h2) => h2.text())).toEqual([
                t('store.trips.map.stops', { count: 0 }),
                t('store.trips.map.freeTrucks'),
                t('store.trips.map.busyTrucks'),
            ]);
            expect(wrapper.text()).toContain(t('store.trips.map.noStops'));
            expect(wrapper.text()).toContain(t('store.trips.map.selectFirst'));
            expect(wrapper.text()).not.toContain(t('store.trips.map.noOrders'));
            expect(
                wrapper.getComponent(ActionErrorBanner).props('message'),
            ).toBe(undefined);
        },
    );

    it('says so when there are no orders and no trucks', () => {
        const wrapper = mountPage({
            orders: [],
            availableTrucks: [],
            busyTrucks: [],
        });

        expect(wrapper.text()).toContain(t('store.trips.map.noOrders'));
        expect(wrapper.text()).toContain(t('store.trips.map.noFreeTrucks'));
        expect(wrapper.text()).toContain(t('store.trips.map.allFree'));
        expect(wrapper.getComponent(StoreTripMap).props('points')).toEqual([]);
    });

    it('passes every order to the map, labelled with its code and customer, with nothing selected', () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(StoreTripMap).props()).toEqual({
            points: [
                {
                    id: 77,
                    latitude: 52.37,
                    longitude: 4.89,
                    label: t('store.trips.form.orderOption', {
                        code: '20261017-0001',
                        customer: 'Alma Customer',
                    }),
                },
                {
                    id: 78,
                    latitude: 51.9,
                    longitude: 4.48,
                    label: t('store.trips.form.orderOption', {
                        code: '20261017-0002',
                        customer: t('store.trips.form.unknownCustomer'),
                    }),
                },
            ],
            selected: [],
        });
    });

    it('lists a selected order as a stop with every detail, and a second click drops it', async () => {
        const wrapper = mountPage();

        await select(wrapper, 77, 78);

        expect(wrapper.getComponent(StoreTripMap).props('selected')).toEqual([
            77, 78,
        ]);
        expect(wrapper.get('#trip-map-stops').text()).toBe(
            t('store.trips.map.stops', { count: 2 }),
        );
        const [first, second] = stopItems(wrapper);
        expect(first.get('p').text()).toBe(
            `${t('store.trips.form.position', { position: 1 })} · ${t('store.trips.form.orderOption', { code: '20261017-0001', customer: 'Alma Customer' })}`,
        );
        expect(
            first
                .findAll('dt')
                .map((dt, index) => [
                    dt.text(),
                    first.findAll('dd')[index].text(),
                ]),
        ).toEqual([
            [t('store.trips.map.customerId'), '21'],
            [t('store.trips.map.postalCode'), '1011AB'],
            [t('store.trips.map.phone'), '0611111111'],
            [t('store.trips.map.address'), 'Dock road 5'],
            [t('store.trips.map.accountingId'), 'ACC-7'],
        ]);
        expect(second.findAll('dd').map((dd) => dd.text())).toEqual([
            '22',
            '-',
            '-',
            '-',
            '-',
        ]);
        expect(wrapper.text()).not.toContain(t('store.trips.map.selectFirst'));

        await select(wrapper, 77);

        expect(wrapper.getComponent(StoreTripMap).props('selected')).toEqual([
            78,
        ]);
        expect(stopItems(wrapper)).toHaveLength(1);
    });

    it("moves and removes stops with the trip form's controls", async () => {
        const wrapper = mountPage();
        await select(wrapper, 77, 78);
        const buttons = (index: number) =>
            stopItems(wrapper)[index].get('.shrink-0').findAll('button');

        expect(
            buttons(0).map((button) => button.attributes('aria-label')),
        ).toEqual([
            t('store.trips.form.moveUp', { position: 1 }),
            t('store.trips.form.moveDown', { position: 1 }),
            t('store.trips.form.remove', { position: 1 }),
        ]);
        expect(buttons(0)[0].attributes('disabled')).toBeDefined();
        expect(buttons(1)[1].attributes('disabled')).toBeDefined();

        await buttons(1)[0].trigger('click');
        expect(wrapper.getComponent(StoreTripMap).props('selected')).toEqual([
            78, 77,
        ]);

        await buttons(1)[0].trigger('click');
        expect(wrapper.getComponent(StoreTripMap).props('selected')).toEqual([
            77, 78,
        ]);

        await buttons(0)[2].trigger('click');
        expect(wrapper.getComponent(StoreTripMap).props('selected')).toEqual([
            78,
        ]);
    });

    it('drops a selected order that left the map after a reload', async () => {
        const wrapper = mountPage();
        await select(wrapper, 77, 78);

        await wrapper.setProps({ orders: [bram] });

        expect(wrapper.getComponent(StoreTripMap).props('selected')).toEqual([
            78,
        ]);
    });

    it('renders the free trucks with their photo or icon and capacity, assigning only once stops are selected', async () => {
        const wrapper = mountPage();
        const cargo = card(wrapper, 'Cargo truck');
        const plain = card(wrapper, 'Plain truck');

        expect(cargo.get('img').attributes()).toMatchObject({
            src: 'https://cdn.example.com/cargo.jpg',
            alt: t('store.trips.map.photoOf', { name: 'Cargo truck' }),
        });
        expect(plain.find('img').exists()).toBe(false);
        expect(cargo.text()).toContain(
            t('store.trips.map.maxPallets', { count: '18' }),
        );
        expect(plain.text()).toContain(
            t('store.trips.map.maxPallets', { count: '-' }),
        );
        expect(assignButton(wrapper, 'Cargo truck').text()).toBe(
            t('store.trips.map.assign', { name: 'Cargo truck' }),
        );
        expect(
            assignButton(wrapper, 'Cargo truck').attributes('disabled'),
        ).toBeDefined();

        await select(wrapper, 78, 77);

        expect(
            assignButton(wrapper, 'Cargo truck').attributes('disabled'),
        ).toBeUndefined();
    });

    it('posts the truck and the stops in their order to a new trip, and clears the selection on success', async () => {
        const wrapper = mountPage();
        await select(wrapper, 78, 77);

        await assignButton(wrapper, 'Cargo truck').trigger('click');

        expect(submitMock).toHaveBeenCalledOnce();
        const [action, payload, options] = submitMock.mock.calls[0] as [
            unknown,
            Record<string, unknown>,
            { preserveScroll: boolean; onSuccess: () => void },
        ];
        expect(action).toEqual(storeFromMap());
        expect({
            truck_id: payload.truck_id,
            trip_id: payload.trip_id,
            markers: payload.markers,
        }).toEqual({
            truck_id: 11,
            trip_id: null,
            markers: [{ order_id: 78 }, { order_id: 77 }],
        });
        expect(options.preserveScroll).toBe(true);

        options.onSuccess();
        await nextTick();
        expect(wrapper.getComponent(StoreTripMap).props('selected')).toEqual(
            [],
        );
    });

    it("adds the stops to a busy truck's latest trip", async () => {
        const wrapper = mountPage();
        await select(wrapper, 77);
        const button = card(wrapper, 'Busy truck').get('button');

        expect(button.text()).toBe(
            t('store.trips.map.addToTrip', { name: 'Busy truck' }),
        );
        await button.trigger('click');

        const payload = submitMock.mock.calls[0][1] as Record<string, unknown>;
        expect([payload.truck_id, payload.trip_id, payload.markers]).toEqual([
            13,
            41,
            [{ order_id: 77 }],
        ]);
    });

    it('shows the first error of the write in the banner', () => {
        useFormStateMock.mockReturnValue({
            errors: { 'markers.0.order_id': 'The order is taken.' },
            processing: false,
        });
        const wrapper = mountPage();

        expect(wrapper.getComponent(ActionErrorBanner).props('message')).toBe(
            'The order is taken.',
        );
    });

    it("renders a busy truck with its trip link, driver, every open trip's stops and counts", () => {
        const wrapper = mountPage();
        const busy = card(wrapper, 'Busy truck');
        const links = busy.findAllComponents(TableLink);

        expect(busy.find('img').exists()).toBe(false);
        expect(
            links.map((link: VueWrapper) => [
                link.text(),
                (link.props() as { href: unknown }).href,
            ]),
        ).toEqual([
            ['Busy truck', tripsShow({ id: 41 })],
            ['Depot', customerEdit({ id: 9 })],
            ['Cas Customer', customerEdit({ id: 23 })],
        ]);
        expect(busy.text()).toContain('Sam Driver');
        expect(busy.findAll('li').map((item) => item.text())).toEqual([
            `${t('store.trips.map.nextStop')}Depot`,
            `${t('store.trips.map.nextStop')}${t('store.trips.form.unknownCustomer')}`,
            `${t('store.trips.map.nextStop')}Cas Customer`,
        ]);
        expect(busy.text()).toContain(
            t('store.trips.map.checkpointsCount', { code: 'LATEST', count: 2 }),
        );
        expect(busy.text()).toContain(
            t('store.trips.map.checkpointsCount', { code: 'OLDER', count: 1 }),
        );

        const idle = card(wrapper, 'Idle busy truck');
        expect(idle.text()).toContain(t('store.trips.map.noTripInformation'));
        expect(idle.text()).not.toContain(t('store.trips.map.nextStop'));
    });

    it("deletes a busy truck's latest trip back to the picker after a native confirmation", () => {
        const wrapper = mountPage();
        const action = card(wrapper, 'Busy truck').getComponent(
            TableActionLink,
        );

        expect(action.text()).toBe(t('store.trips.map.deleteTrip'));
        expect(action.props()).toMatchObject({
            href: destroy({ id: 41 }, { query: { list: 'map' } }),
            variant: 'danger',
        });
        expect(action.attributes('method')).toBe('delete');
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const before = action
            .getComponent(Link)
            .props('onBefore') as () => boolean;
        expect(before()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', {
                label: t('store.trips.index.trip', { code: 'LATEST' }),
            }),
        );
        confirm.mockReturnValue(true);
        expect(before()).toBe(true);
        expect(
            card(wrapper, 'Idle busy truck')
                .findComponent(TableActionLink)
                .exists(),
        ).toBe(true);
    });

    it('marks a stop delivered back to the picker after a native confirmation', async () => {
        const wrapper = mountPage();
        await select(wrapper, 77);
        const action = stopItems(wrapper)[0].getComponent(TableActionLink);

        expect(action.text()).toBe(t('store.trips.orders.markDelivered'));
        expect(action.props('href')).toEqual(
            markDelivered(
                { id: 77 },
                { query: { return_to: 'map', trip_id: 41 } },
            ),
        );
        expect(action.attributes('method')).toBe('put');
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const before = action
            .getComponent(Link)
            .props('onBefore') as () => boolean;
        expect(before()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('store.trips.orders.confirmStatus', {
                label: t('store.trips.orders.order', { code: '20261017-0001' }),
                status: t('store.orders.status.delivered'),
            }),
        );
    });

    it('does not offer delivery for an order with no trip attachment', async () => {
        const wrapper = mountPage({ orderTrips: {} });
        await select(wrapper, 77);
        expect(
            stopItems(wrapper)[0].findComponent(TableActionLink).exists(),
        ).toBe(false);
    });

    it("gates the row actions and the busy-truck assignment by their routes' permissions", async () => {
        const wrapper = mountPage({ permissions: ['add_trip'] });
        await select(wrapper, 77);

        expect(
            stopItems(wrapper)[0].findComponent(TableActionLink).exists(),
        ).toBe(false);
        expect(card(wrapper, 'Busy truck').find('button').exists()).toBe(false);
        expect(
            card(wrapper, 'Busy truck').findComponent(TableActionLink).exists(),
        ).toBe(false);
        expect(
            card(wrapper, 'Busy truck')
                .findAllComponents(TableLink)
                .map((link: VueWrapper) => link.text()),
        ).toEqual(['Busy truck']);
        expect(card(wrapper, 'Busy truck').text()).toContain('Depot');
        expect(assignButton(wrapper, 'Cargo truck').exists()).toBe(true);
    });
});

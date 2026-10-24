import { Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { edit as customerEdit } from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import { edit as driverEdit } from '@/actions/App/Http/Controllers/Store/Admin/DriverController';
import { show as orderShow } from '@/actions/App/Http/Controllers/Store/Admin/OrderController';
import {
    printCmr,
    show as tripsShow,
    updateNotes,
    updateStatus,
} from '@/actions/App/Http/Controllers/Store/Admin/TripCheckpointController';
import { edit as tripsEdit } from '@/actions/App/Http/Controllers/Store/Admin/TripController';
import { edit as truckEdit } from '@/actions/App/Http/Controllers/Store/Admin/TruckController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableLink from '@/components/TableLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { formatDateTime, formatDuration } from '@/lib/date';
import { i18n, t } from '@/lib/i18n';
import {
    storeSharedProps,
    storeTrip,
    storeTripCheckpointRow,
    truckPhoto,
} from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type { StoreTripCheckpointRow } from '@/types/store';
import Show from './Show.vue';

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

const allPermissions = [
    'view_all_trips',
    'edit_trip',
    'edit_driver',
    'edit_truck',
    'edit_customer',
    'view_order_details',
];
const columns = [
    'position',
    'checkpoint',
    'customer',
    'representative',
    'address',
    'status',
    'arrivedAt',
    'eta',
    'notes',
    'phone',
    'products',
    'company',
    'amount',
    'invoiceNumber',
    'photos',
    'cmr',
];
const visit = storeTripCheckpointRow({
    id: 502,
    position: 2,
    type: 'other',
    status: 'pending',
    arrived_at: null,
    eta: null,
    notes: null,
    order: null,
    customer: {
        id: 23,
        name: 'Visit Customer',
        phone: null,
        company_name: null,
        address: null,
    },
    representative: null,
    has_cmr: false,
    photos: [],
});

function mountPage(
    checkpoints: StoreTripCheckpointRow[] = [],
    permissions = allPermissions,
    errors: Record<string, string> = {},
) {
    usePageMock.mockReturnValue({
        url: tripsShow.url(storeTrip()),
        props: defaultAuthProps({
            store: storeSharedProps({ permissions }),
            errors,
        }),
    });

    return mount(Show, {
        props: {
            trip: storeTrip(),
            checkpoints,
            photos: [
                truckPhoto({
                    id: 601,
                    url: 'https://cdn.example.com/door.jpg',
                }),
                truckPhoto({ id: 602, url: null }),
            ],
            statuses: ['pending', 'delivered', 'has_issue'],
        },
    });
}

describe('Store trip detail', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, submitMock });
        useFormStateMock.mockReturnValue({ errors: {}, processing: false });
    });
    afterEach(() => {
        vi.restoreAllMocks();
        i18n.global.locale.value = 'en';
    });

    it.each(['en', 'ar'] as const)(
        'renders the layout, title, trip fields, every header and the empty label in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountPage();
            const trip = storeTrip();
            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
            expect(wrapper.getComponent(Head).props('title')).toBe(
                t('store.trips.show.title', { code: trip.code }),
            );
            expect(wrapper.get('h1').text()).toBe(
                t('store.trips.show.title', { code: trip.code }),
            );
            expect(wrapper.get('h2').text()).toBe(
                t('store.trips.show.checkpoints'),
            );
            expect(
                wrapper
                    .findAll('dl > div')
                    .map((field) => [
                        field.get('dt').text(),
                        field.get('dd').text(),
                    ]),
            ).toEqual([
                [
                    t('store.trips.index.status'),
                    t('store.trips.status.started'),
                ],
                [t('store.trips.index.dueDate'), '2026-10-14'],
                [t('store.trips.index.driver'), 'Sam Driver'],
                [
                    t('store.trips.index.truck'),
                    t('store.trips.index.truckLabel', {
                        id: 11,
                        name: 'Cargo truck',
                    }),
                ],
                [
                    t('store.trips.index.startedAt'),
                    formatDateTime('2026-10-14T08:30:00+00:00'),
                ],
                [
                    t('store.trips.index.closedAt'),
                    t('store.trips.index.notAvailable'),
                ],
                [
                    t('store.trips.index.distance'),
                    t('store.trips.index.km', {
                        distance:
                            locale === 'ar'
                                ? new Intl.NumberFormat('ar', {
                                      minimumFractionDigits: 2,
                                      maximumFractionDigits: 2,
                                  }).format(12.34567)
                                : '12.35',
                    }),
                ],
                [t('store.trips.index.duration'), formatDuration(3720)],
                [t('store.trips.index.notes'), 'Ring at the back'],
            ]);
            expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual(
                columns.map((key) => t(`store.trips.show.${key}`)),
            );
            expect(wrapper.text()).toContain(t('store.trips.show.empty'));
            expect(wrapper.text()).toContain(t('store.trips.show.print'));
        },
    );

    it('renders every checkpoint field with links for permitted viewers', () => {
        const delivery = storeTripCheckpointRow({ photos: [601, 602, 603] });
        const wrapper = mountPage([delivery, visit]);
        const rows = wrapper.findAll('tbody tr');
        const cells = rows[0].findAll('td');
        expect(cells.map((td) => td.text())).toEqual([
            '1',
            t('store.trips.form.orderOption', {
                code: '20261015-0001',
                customer: 'Alma Customer',
            }),
            'Alma Customer',
            'Rita Rep',
            'Main street 5',
            cells[5].text(),
            formatDateTime('2026-10-14T10:30:00+00:00'),
            formatDuration(1830),
            '',
            '0611111111',
            '3',
            'Alma BV',
            '€42.50',
            'INV-1',
            '',
            t('store.trips.show.printCmr'),
        ]);
        expect(
            rows[0]
                .findAllComponents(TableLink)
                .map((link: VueWrapper) => link.props()),
        ).toEqual([
            {
                href: orderShow['/temp/admin/orders/{order}/show'](77),
            },
            { href: customerEdit({ id: 21 }) },
        ]);
        expect(rows[0].findAll('img').map((img) => img.attributes())).toEqual([
            expect.objectContaining({
                src: 'https://cdn.example.com/door.jpg',
                alt: t('store.trips.show.photoOf', {
                    stop: t('store.trips.show.stop', { position: 1 }),
                }),
                width: '100',
                height: '100',
            }),
        ]);
        const cmr = rows[0].getComponent(TableActionLink);
        expect(cmr.props()).toMatchObject({
            href: printCmr(delivery),
            inertia: false,
        });
        expect(rows[1].findAll('td').map((td) => td.text())).toEqual([
            '2',
            'Visit Customer',
            'Visit Customer',
            '-',
            '-',
            rows[1].findAll('td')[5].text(),
            '',
            '-',
            '',
            '-',
            '-',
            '-',
            '-',
            '-',
            '-',
            '-',
        ]);
        expect(rows[1].findComponent(TableActionLink).exists()).toBe(false);
        expect(
            wrapper.findAllComponents(TableLink).map((link) => link.props()),
        ).toEqual([
            { href: driverEdit({ id: 31 }) },
            { href: truckEdit({ id: 11 }) },
            { href: orderShow['/temp/admin/orders/{order}/show'](77) },
            { href: customerEdit({ id: 21 }) },
            { href: customerEdit({ id: 23 }) },
        ]);
        expect(wrapper.getComponent(TableActionLink).props('href')).toEqual(
            tripsEdit(storeTrip()),
        );
    });

    it('shows read-only text and no links or controls without the permissions', () => {
        const wrapper = mountPage(
            [storeTripCheckpointRow(), visit],
            ['view_all_trips'],
        );
        expect(wrapper.findAllComponents(TableLink)).toHaveLength(0);
        expect(wrapper.find('select').exists()).toBe(false);
        expect(wrapper.find('textarea').exists()).toBe(false);
        const rows = wrapper.findAll('tbody tr');
        expect(rows[0].findAll('td')[5].text()).toBe(
            t('store.trips.show.statuses.delivered'),
        );
        expect(rows[0].findAll('td')[8].text()).toBe('Back door');
        expect(rows[0].findAll('td')[1].text()).toBe(
            t('store.trips.form.orderOption', {
                code: '20261015-0001',
                customer: 'Alma Customer',
            }),
        );
        expect(
            wrapper
                .findAllComponents(TableActionLink)
                .map((link) => link.props('href')),
        ).toEqual([printCmr(storeTripCheckpointRow())]);
        expect(wrapper.text()).toContain('Sam Driver');
        expect(wrapper.text()).toContain(
            t('store.trips.index.truckLabel', { id: 11, name: 'Cargo truck' }),
        );
    });

    it("saves a row's notes on change to this page and reports the save", async () => {
        const wrapper = mountPage([storeTripCheckpointRow(), visit]);
        const notes = wrapper.get<HTMLTextAreaElement>('#checkpoint-notes-501');
        expect(notes.element.value).toBe('Back door');
        expect(notes.attributes()).toMatchObject({
            maxlength: '1000',
            'aria-label': t('store.trips.show.notesFor', {
                stop: t('store.trips.show.stop', { position: 1 }),
            }),
        });

        await notes.setValue('Front door');
        expect(submitMock).toHaveBeenCalledWith(
            updateNotes(storeTripCheckpointRow(), { mergeQuery: {} }),
            expect.objectContaining({ notes: 'Front door' }),
            expect.objectContaining({
                preserveScroll: true,
                preserveState: true,
            }),
        );
        expect(wrapper.find('[role="status"]').exists()).toBe(false);
        (submitMock.mock.calls[0][2] as { onSuccess: () => void }).onSuccess();
        await wrapper.vm.$nextTick();
        expect(wrapper.get('[role="status"]').text()).toBe(
            t('store.trips.show.notesSaved'),
        );
    });

    it('shows a refused note under the row that sent it', async () => {
        useFormStateMock.mockReturnValue({
            errors: {
                notes: 'The notes may not be greater than 1000 characters.',
            },
            processing: false,
        });
        const wrapper = mountPage([storeTripCheckpointRow(), visit]);
        await wrapper.get('#checkpoint-notes-502').setValue('x');
        const rows = wrapper.findAll('tbody tr');
        expect(rows[1].text()).toContain(
            'The notes may not be greater than 1000 characters.',
        );
        expect(rows[0].text()).not.toContain(
            'The notes may not be greater than 1000 characters.',
        );
    });

    it('confirms a status change natively before saving it and restores the select when refused', async () => {
        const wrapper = mountPage([storeTripCheckpointRow(), visit]);
        const select = wrapper.get<HTMLSelectElement>('#checkpoint-status-502');
        expect(select.element.value).toBe('pending');
        expect(wrapper.get('label[for="checkpoint-status-502"]').text()).toBe(
            `${t('store.trips.show.statusFor', {
                stop: t('store.trips.show.stop', { position: 2 }),
            })}*${t('common.required')}`,
        );
        expect(
            select
                .findAll('option')
                .map((option) => [option.attributes('value'), option.text()]),
        ).toEqual(
            ['pending', 'delivered', 'has_issue'].map((status) => [
                status,
                t(`store.trips.show.statuses.${status}`),
            ]),
        );
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);

        await select.setValue('delivered');
        expect(confirm).toHaveBeenCalledWith(
            t('store.trips.orders.confirmStatus', {
                label: t('store.trips.show.stop', { position: 2 }),
                status: t('store.trips.show.statuses.delivered'),
            }),
        );
        expect(submitMock).not.toHaveBeenCalled();
        expect(select.element.value).toBe('pending');

        confirm.mockReturnValue(true);
        await select.setValue('has_issue');
        expect(submitMock).toHaveBeenCalledWith(
            updateStatus(visit, { mergeQuery: {} }),
            expect.objectContaining({ status: 'has_issue' }),
            expect.objectContaining({ preserveScroll: true }),
        );
        (submitMock.mock.calls[0][2] as { onError: () => void }).onError();
        expect(select.element.value).toBe('pending');
    });

    it('shows a refused status under the row that sent it', async () => {
        useFormStateMock.mockReturnValue({
            errors: { status: 'The selected status is invalid.' },
            processing: false,
        });
        vi.spyOn(window, 'confirm').mockReturnValue(true);
        const wrapper = mountPage([storeTripCheckpointRow(), visit]);
        await wrapper.get('#checkpoint-status-501').setValue('pending');
        const rows = wrapper.findAll('tbody tr');
        expect(rows[0].text()).toContain('The selected status is invalid.');
        expect(rows[1].text()).not.toContain('The selected status is invalid.');
    });

    it('shows a missing CMR file as a banner and prints the page', async () => {
        const wrapper = mountPage([storeTripCheckpointRow()], allPermissions, {
            cmr: 'This checkpoint has no CMR file to open.',
        });
        expect(wrapper.getComponent(ActionErrorBanner).props('message')).toBe(
            'This checkpoint has no CMR file to open.',
        );
        const print = vi.spyOn(window, 'print').mockImplementation(() => {});
        await wrapper
            .findAll('button')
            .find((button) => button.text() === t('store.trips.show.print'))!
            .trigger('click');
        expect(print).toHaveBeenCalledOnce();
    });

    it('escapes names and notes rather than rendering supplied HTML', () => {
        const wrapper = mountPage(
            [
                storeTripCheckpointRow({
                    notes: '<b>bold</b>',
                    customer: {
                        id: 21,
                        name: '<img src=x onerror=alert(1)>',
                        phone: null,
                        company_name: null,
                        address: null,
                    },
                }),
            ],
            ['view_all_trips'],
        );
        expect(wrapper.find('tbody img[src="x"]').exists()).toBe(false);
        expect(wrapper.find('tbody b').exists()).toBe(false);
        expect(wrapper.text()).toContain('<b>bold</b>');
    });
    it('marks every editable checkpoint status and leaves notes optional', async () => {
        const wrapper = mountPage([storeTripCheckpointRow(), visit]);
        await wrapper.vm.$nextTick();
        expect(wrapper.findAll('[data-required-legend]')).toHaveLength(1);

        for (const select of wrapper.findAll('select[required]')) {
            expect(
                wrapper
                    .find(
                        `label[for="${select.attributes('id')}"] [data-required-mark]`,
                    )
                    .exists(),
            ).toBe(true);
        }

        expect(wrapper.findAll('[data-required-mark]')).toHaveLength(2);

        for (const notes of wrapper.findAll('textarea')) {
            expect(notes.attributes('required')).toBeUndefined();
        }

        wrapper.unmount();
        const viewer = mountPage([visit], ['view_all_trips']);
        expect(viewer.find('[data-required-mark]').exists()).toBe(false);
        expect(viewer.find('[data-required-legend]').exists()).toBe(false);
        viewer.unmount();
    });
});

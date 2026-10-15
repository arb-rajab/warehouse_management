import { Form, Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { DefineComponent } from 'vue';
import { edit as driverEdit } from '@/actions/App/Http/Controllers/Store/Admin/DriverController';
import { shippingCreate } from '@/actions/App/Http/Controllers/Store/Admin/OrderEntryController';
import {
    bulkDestroy,
    create as tripsCreate,
    destroy,
    edit as tripsEdit,
    index as allTrips,
    today as todayTrips,
    updateNotes,
} from '@/actions/App/Http/Controllers/Store/Admin/TripController';
import { edit as truckEdit } from '@/actions/App/Http/Controllers/Store/Admin/TruckController';
import { show as tripsShow } from '@/actions/App/Http/Controllers/Trip/TripController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FieldError from '@/components/FieldError.vue';
import Pagination from '@/components/Pagination.vue';
import StoreTripStatusDialog from '@/components/StoreTripStatusDialog.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableLink from '@/components/TableLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { formatDateTime, formatDuration } from '@/lib/date';
import { i18n, t } from '@/lib/i18n';
import { paginated, storeSharedProps, storeTrip } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type {
    StoreTrip,
    StoreTripFilters,
    StoreTripListType,
} from '@/types/store';
import Index from './Index.vue';

const {
    usePageMock,
    routerGetMock,
    routerPostMock,
    formStateMock,
    submitMock,
    useFormStateMock,
} = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
    routerPostMock: vi.fn(),
    formStateMock: vi.fn(() => ({
        errors: {} as Record<string, string>,
        processing: false,
    })),
    submitMock: vi.fn(),
    useFormStateMock: vi.fn(() => ({
        errors: {} as Record<string, string>,
        processing: false,
    })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, createUseFormStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Form: createFormStub(formStateMock),
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
        useForm: createUseFormStub(submitMock, useFormStateMock),
        router: { get: routerGetMock, post: routerPostMock },
    };
});

const allPermissions = [
    'view_all_trips',
    'add_order',
    'add_trip',
    'edit_trip',
    'delete_trip',
];
const columns = [
    'code',
    'driver',
    'truck',
    'dueDate',
    'status',
    'startedAt',
    'closedAt',
    'distance',
    'duration',
    'notes',
    'actions',
];

function mountPage(
    trips: StoreTrip[] = [],
    permissions = allPermissions,
    listType: StoreTripListType = 'all',
    filters: Partial<StoreTripFilters> = {},
) {
    usePageMock.mockReturnValue({
        url: allTrips.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, {
        props: {
            listType,
            trips: paginated(trips),
            filters: { per_page: 20, search: '', status: [], ...filters },
            statuses: ['pending', 'started', 'closed'],
        },
    });
}
function actionLink(wrapper: ReturnType<typeof mountPage>, key: string) {
    return wrapper
        .findAllComponents(TableActionLink)
        .find((link) => link.text().includes(t(`store.trips.index.${key}`)));
}
function forms(wrapper: ReturnType<typeof mountPage>) {
    return wrapper.findAllComponents(Form);
}

describe('Store trip lists', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, routerGetMock, routerPostMock, submitMock });
        formStateMock.mockReturnValue({ errors: {}, processing: false });
        useFormStateMock.mockReturnValue({ errors: {}, processing: false });
    });
    afterEach(() => {
        vi.restoreAllMocks();
        i18n.global.locale.value = 'en';
    });

    it.each(['en', 'ar'] as const)(
        'renders the layout, title, filters, every header and the empty label in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountPage();
            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
            expect(wrapper.getComponent(Head).props('title')).toBe(
                t('store.trips.index.allTitle'),
            );
            expect(wrapper.get('h1').text()).toBe(
                t('store.trips.index.allTitle'),
            );
            expect(wrapper.get('label[for="search"]').text()).toBe(
                t('store.trips.index.search'),
            );
            expect(wrapper.get('legend').text()).toBe(
                t('store.trips.index.statusFilter'),
            );
            expect(
                wrapper
                    .findAll('input[name="status[]"]')
                    .map((input) => input.element.parentElement?.textContent),
            ).toEqual(
                ['pending', 'started', 'closed'].map((status) =>
                    t(`store.trips.status.${status}`),
                ),
            );
            expect(
                wrapper
                    .findAllComponents(SubmitButton)
                    .map((button) => [
                        button.props('label'),
                        button.props('processingLabel'),
                    ]),
            ).toEqual([
                [t('store.trips.index.apply'), t('store.trips.index.applying')],
                [
                    t('store.trips.index.deleteSelection'),
                    t('store.trips.index.deleting'),
                ],
            ]);
            expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual(
                ['select', ...columns].map((key) =>
                    t(`store.trips.index.${key}`),
                ),
            );
            expect(wrapper.text()).toContain(t('store.trips.index.empty'));
        },
    );

    it("titles and filters today's list against its own route", () => {
        const wrapper = mountPage([], allPermissions, 'today');
        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('store.trips.index.todayTitle'),
        );
        expect(wrapper.get('h1').text()).toBe(
            t('store.trips.index.todayTitle'),
        );
        expect(forms(wrapper)[0].props('action')).toEqual(todayTrips());
        expect(forms(wrapper)[1].props('action')).toEqual(
            bulkDestroy({ mergeQuery: { list: 'today' } }),
        );
    });

    it('links order and trip creation, both as Inertia visits', () => {
        const wrapper = mountPage();
        expect(
            wrapper
                .findAllComponents(AddResourceLink)
                .map((link) => link.props()),
        ).toEqual([
            {
                href: shippingCreate(),
                label: t('store.trips.index.createOrder'),
                inertia: true,
            },
            {
                href: tripsCreate(),
                label: t('store.trips.index.createTrip'),
                inertia: true,
            },
        ]);
    });

    it('renders every row field in the list formats and passes the rows to the table', () => {
        const trip = storeTrip({ closed_at: '2026-10-14T17:45:00+00:00' });
        const bare = storeTrip({
            id: 42,
            code: '20261014-090000',
            status: 'pending',
            due_date: null,
            started_at: null,
            driver: null,
            truck: null,
            notes: null,
            distance: null,
            duration: null,
        });
        const wrapper = mountPage([trip, bare], ['view_all_trips']);
        expect(
            wrapper
                .getComponent(
                    DataTable as unknown as DefineComponent<{
                        rows: StoreTrip[];
                    }>,
                )
                .props('rows'),
        ).toEqual([trip, bare]);
        const rows = wrapper.findAll('tbody tr');
        expect(rows[0].findAll('td').map((td) => td.text())).toEqual([
            '20261014-080000',
            'Sam Driver',
            '#11 Cargo truck',
            '2026-10-14',
            t('store.trips.status.started'),
            formatDateTime('2026-10-14T08:30:00+00:00'),
            formatDateTime('2026-10-14T17:45:00+00:00'),
            '12.35 km',
            '1h 2m',
            'Ring at the back',
            '',
        ]);
        expect(formatDuration(3720)).toBe('1h 2m');
        expect(rows[0].get('td a').attributes('href')).toBe(
            tripsShow.url(trip),
        );
        expect(rows[1].findAll('td').map((td) => td.text())).toEqual([
            '20261014-090000',
            t('store.trips.index.notAvailable'),
            t('store.trips.index.notAvailable'),
            '',
            t('store.trips.status.pending'),
            '',
            '',
            '0.00 km',
            formatDuration(0),
            '',
            '',
        ]);
        expect(wrapper.findAllComponents(TableLink)).toHaveLength(0);
    });

    it('links the driver and truck to their edit pages for a viewer who may edit them', () => {
        const driver = { id: 31, name: 'Sam Driver' };
        const truck = { id: 11, name: 'Cargo truck' };
        const trip = storeTrip({ distance: 1234567, driver, truck });
        const wrapper = mountPage(
            [trip],
            ['view_all_trips', 'edit_driver', 'edit_truck'],
        );
        expect(
            wrapper
                .findAllComponents(TableLink)
                .map((link) => [link.props('href'), link.text()]),
        ).toEqual([
            [driverEdit(driver), 'Sam Driver'],
            [truckEdit(truck), '#11 Cargo truck'],
        ]);
        expect(wrapper.text()).toContain('1,234.57 km');

        const driverOnly = mountPage([trip], ['view_all_trips', 'edit_driver']);
        expect(
            driverOnly.findAllComponents(TableLink).map((link) => link.text()),
        ).toEqual(['Sam Driver']);
        expect(driverOnly.text()).toContain('#11 Cargo truck');
    });

    it('links the edit form as an Inertia visit and wires native confirmation to DELETE on the list', () => {
        const trip = storeTrip();
        const wrapper = mountPage([trip], allPermissions, 'today');
        expect(actionLink(wrapper, 'edit')?.props()).toMatchObject({
            href: tripsEdit(trip),
            inertia: true,
        });
        const action = actionLink(wrapper, 'delete');
        expect(action?.props()).toMatchObject({
            href: destroy(trip, { mergeQuery: { list: 'today' } }),
            variant: 'danger',
        });
        expect(action?.attributes('method')).toBe('delete');
        const onBefore = action
            ?.getComponent(Link)
            .props('onBefore') as () => boolean;
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        expect(onBefore()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', {
                label: t('store.trips.index.trip', { code: trip.code }),
            }),
        );
        confirm.mockReturnValue(true);
        expect(onBefore()).toBe(true);
    });

    it("shows a reader no write control, even with another module's delete permission", () => {
        const wrapper = mountPage(
            [storeTrip()],
            ['view_all_trips', 'delete_customer'],
        );
        expect(wrapper.findComponent(AddResourceLink).exists()).toBe(false);
        expect(wrapper.findAllComponents(TableActionLink)).toHaveLength(0);
        expect(wrapper.find('textarea').exists()).toBe(false);
        expect(wrapper.find('tbody input[type="checkbox"]').exists()).toBe(
            false,
        );
        expect(forms(wrapper)).toHaveLength(1);
        expect(wrapper.findComponent(StoreTripStatusDialog).exists()).toBe(
            false,
        );
        expect(
            wrapper
                .find(
                    `button[aria-label="${t('store.trips.index.updateStatusFor', { code: '20261014-080000' })}"]`,
                )
                .exists(),
        ).toBe(false);
        expect(wrapper.text()).toContain('Ring at the back');
        expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual(
            columns.map((key) => t(`store.trips.index.${key}`)),
        );
    });

    it('gates each control by its own permission', () => {
        const editor = mountPage(
            [storeTrip()],
            ['view_all_trips', 'edit_trip'],
        );
        expect(
            editor
                .findAllComponents(TableActionLink)
                .map((link) => link.text()),
        ).toEqual([t('store.trips.index.edit')]);
        expect(editor.find('textarea').exists()).toBe(true);
        expect(editor.findComponent(StoreTripStatusDialog).exists()).toBe(true);
        expect(forms(editor)).toHaveLength(1);

        const deleter = mountPage(
            [storeTrip()],
            ['view_all_trips', 'delete_trip'],
        );
        expect(
            deleter
                .findAllComponents(TableActionLink)
                .map((link) => link.text()),
        ).toEqual([t('store.trips.index.delete')]);
        expect(deleter.find('textarea').exists()).toBe(false);
        expect(deleter.findComponent(StoreTripStatusDialog).exists()).toBe(
            false,
        );
        expect(forms(deleter)).toHaveLength(2);

        const orderer = mountPage([], ['view_all_trips', 'add_order']);
        expect(
            orderer
                .findAllComponents(AddResourceLink)
                .map((link) => link.props('label')),
        ).toEqual([t('store.trips.index.createOrder')]);
        const planner = mountPage([], ['view_all_trips', 'add_trip']);
        expect(
            planner
                .findAllComponents(AddResourceLink)
                .map((link) => link.props('label')),
        ).toEqual([t('store.trips.index.createTrip')]);
    });

    it('opens the status dialog for the chosen trip on its list', async () => {
        const first = storeTrip();
        const second = storeTrip({ id: 42, code: '20261014-090000' });
        const wrapper = mountPage([first, second], allPermissions, 'today');
        const dialog = wrapper.getComponent(StoreTripStatusDialog);
        expect(dialog.props()).toMatchObject({
            open: false,
            trip: null,
            statuses: ['pending', 'started', 'closed'],
            list: 'today',
        });
        await wrapper
            .get(
                `button[aria-label="${t('store.trips.index.updateStatusFor', { code: second.code })}"]`,
            )
            .trigger('click');
        expect(dialog.props()).toMatchObject({ open: true, trip: second });
    });

    it("saves a row's notes on change to its list and reports the save", async () => {
        const trip = storeTrip();
        const wrapper = mountPage([trip], allPermissions, 'today');
        const notes = wrapper.get<HTMLTextAreaElement>(
            `#trip-notes-${trip.id}`,
        );
        expect(notes.element.value).toBe('Ring at the back');
        expect(notes.attributes()).toMatchObject({
            maxlength: '255',
            'aria-label': t('store.trips.index.notesFor', { code: trip.code }),
        });

        await notes.setValue('Front door');
        expect(submitMock).toHaveBeenCalledWith(
            updateNotes(trip, { mergeQuery: { list: 'today' } }),
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
            t('store.trips.index.notesSaved'),
        );
    });

    it('shows a refused note under the row that sent it', async () => {
        useFormStateMock.mockReturnValue({
            errors: {
                notes: 'The notes may not be greater than 255 characters.',
            },
            processing: false,
        });
        const first = storeTrip();
        const second = storeTrip({ id: 42, code: '20261014-090000' });
        const wrapper = mountPage([first, second]);
        expect(
            wrapper
                .findAllComponents(FieldError)
                .filter((error) => error.props('message')),
        ).toHaveLength(0);

        await wrapper.get('#trip-notes-42').setValue('x'.repeat(300));
        const rows = wrapper.findAll('tbody tr');
        expect(rows[1].text()).toContain(
            'The notes may not be greater than 255 characters.',
        );
        expect(rows[0].text()).not.toContain(
            'The notes may not be greater than 255 characters.',
        );
    });

    it('bulk deletes the selection after confirmation and selects the whole page', async () => {
        const first = storeTrip();
        const second = storeTrip({ id: 42, code: '20261014-090000' });
        const wrapper = mountPage([first, second]);
        const bulk = forms(wrapper)[1];
        expect(bulk.props('action')).toEqual(
            bulkDestroy({ mergeQuery: { list: 'all' } }),
        );
        const onBefore = bulk.props('onBefore') as () => boolean;
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(true);
        expect(onBefore()).toBe(false);
        expect(confirm).not.toHaveBeenCalled();
        expect(
            bulk.getComponent(SubmitButton).attributes('disabled'),
        ).toBeDefined();

        await wrapper
            .get(
                `input[aria-label="${t('store.trips.index.selectTrip', { code: second.code })}"]`,
            )
            .setValue(true);
        expect(
            bulk
                .findAll('input[name="id[]"]')
                .map((input) => input.attributes('value')),
        ).toEqual(['42']);
        expect(onBefore()).toBe(true);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', {
                label: t('store.trips.index.selection', { count: 1 }),
            }),
        );

        const selectAllLabel = wrapper
            .findAll('label')
            .find((label) => label.text() === t('store.trips.index.selectAll'));
        expect(selectAllLabel?.exists()).toBe(true);
        await selectAllLabel?.get('input[type="checkbox"]').setValue(true);
        expect(
            bulk
                .findAll('input[name="id[]"]')
                .map((input) => input.attributes('value')),
        ).toEqual(['41', '42']);
        bulk.vm.$emit('success');
        await wrapper.vm.$nextTick();
        expect(bulk.findAll('input[name="id[]"]')).toHaveLength(0);
    });

    it('shows a refused bulk delete', () => {
        formStateMock.mockReturnValue({
            errors: { 'id.0': 'The selected trip is invalid.' },
            processing: false,
        });
        const wrapper = mountPage([storeTrip()]);
        expect(forms(wrapper)[1].text()).toContain(
            'The selected trip is invalid.',
        );
    });

    it('submits the filters to the list and keeps them when the page size changes', async () => {
        const wrapper = mountPage([storeTrip()], allPermissions, 'all', {
            per_page: 50,
            search: 'Sam',
            status: ['pending', 'closed'],
        });
        const filter = forms(wrapper)[0];
        expect(filter.props('action')).toEqual(allTrips());
        expect(filter.get('input[name="per_page"]').attributes('value')).toBe(
            '50',
        );
        expect(filter.get<HTMLInputElement>('#search').element.value).toBe(
            'Sam',
        );
        expect(filter.get('#search').attributes('maxlength')).toBe('191');
        expect(
            filter
                .findAll<HTMLInputElement>('input[name="status[]"]')
                .map((input) => [input.element.value, input.element.checked]),
        ).toEqual([
            ['pending', true],
            ['started', false],
            ['closed', true],
        ]);

        const pagination = wrapper.getComponent(Pagination);
        expect(pagination.props()).toMatchObject({ links: [], perPage: 50 });
        pagination.vm.$emit('update:perPage', 10);
        expect(routerGetMock).toHaveBeenCalledWith(
            allTrips.url(),
            { per_page: 10, search: 'Sam', status: ['pending', 'closed'] },
            { preserveState: true, replace: true },
        );

        const plain = mountPage([], allPermissions, 'today');
        plain.getComponent(Pagination).vm.$emit('update:perPage', 100);
        expect(routerGetMock).toHaveBeenLastCalledWith(
            todayTrips.url(),
            { per_page: 100 },
            { preserveState: true, replace: true },
        );
    });

    it("shows the server's filter errors", () => {
        formStateMock.mockReturnValue({
            errors: {
                search: 'The search must be a string.',
                'status.0': 'The selected status is invalid.',
            },
            processing: false,
        });
        const filter = forms(mountPage())[0];
        expect(filter.text()).toContain('The search must be a string.');
        expect(filter.text()).toContain('The selected status is invalid.');
    });

    it('escapes trip codes, names and notes rather than rendering supplied HTML', () => {
        const wrapper = mountPage(
            [
                storeTrip({
                    code: '<img src=x onerror=alert(1)>',
                    driver: { id: 1, name: '<script>alert(1)</script>' },
                    notes: '<b>bold</b>',
                }),
            ],
            ['view_all_trips'],
        );
        expect(wrapper.find('tbody script').exists()).toBe(false);
        expect(wrapper.find('tbody img').exists()).toBe(false);
        expect(wrapper.find('tbody b').exists()).toBe(false);
        expect(wrapper.get('tbody').text()).toContain(
            '<script>alert(1)</script>',
        );
    });
});

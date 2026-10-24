import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { getAvailableVehicles } from '@/actions/App/Http/Controllers/Api/V2/Trip/DriverController';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import { i18n, t } from '@/lib/i18n';
import { storeTripForm } from '@/testing/factories';
import type { StoreTripForm, StoreTripVehicles } from '@/types/store';
import StoreTripFormFields from './StoreTripFormFields.vue';

const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => ({ processing: false, get: getMock }),
}));

const orders = [
    { id: 77, code: '20261013-0042', customer: 'Alma Customer' },
    { id: 78, code: '20261013-0041', customer: null },
];
const customers = [
    { id: 9, name: 'Warehouse' },
    { id: 12, name: 'Bram Customer' },
];
const vehicles: StoreTripVehicles = {
    drivers: [
        { id: 31, name: 'Sam Driver' },
        { id: 32, name: 'Noor Driver' },
    ],
    trucks: [
        { id: 11, name: 'Cargo truck', model: 'Volvo' },
        { id: 12, name: 'Van', model: null },
    ],
};

function mountFields(
    trip?: StoreTripForm,
    errors: Record<string, string> = {},
) {
    return mount(StoreTripFormFields, {
        props: { trip, orders, customers, errors },
        attachTo: document.body,
    });
}
function vehiclesUrl(date: string, excludedTripId: number): string {
    return getAvailableVehicles['/temp/admin/trips/available-vehicles'].url({
        query: { date, excluded_trip_id: excludedTripId },
    });
}
function rowNames(wrapper: ReturnType<typeof mountFields>): string[] {
    return wrapper
        .findAll('[data-testid="trip-checkpoint"]')
        .map(
            (row) =>
                (
                    row.find('input[name$="[checkpoint_type]"]')
                        .element as HTMLInputElement
                ).name,
        );
}

describe('StoreTripFormFields', () => {
    beforeEach(() => {
        getMock.mockReset();
        getMock.mockResolvedValue(vehicles);
    });
    afterEach(() => {
        i18n.global.locale.value = 'en';
        document.body.innerHTML = '';
    });

    it.each(['en', 'ar'] as const)(
        'renders every static label and the native constraints in %s',
        async (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountFields();
            await wrapper
                .findAll('button')
                .find(
                    (button) =>
                        button.text() === t('store.trips.form.addOrder'),
                )!
                .trigger('click');
            await wrapper
                .findAll('button')
                .find(
                    (button) =>
                        button.text() === t('store.trips.form.addCustomer'),
                )!
                .trigger('click');

            for (const key of [
                'dueDate',
                'driver',
                'selectDriver',
                'truck',
                'selectTruck',
                'notes',
                'checkpoints',
                'checkpointsHelp',
                'order',
                'customer',
                'selectOrder',
                'selectCustomer',
                'checkpointNotes',
                'addOrder',
                'addCustomer',
                'types.delivery',
                'types.other',
            ]) {
                expect(wrapper.text()).toContain(t(`store.trips.form.${key}`));
            }

            expect(wrapper.text()).toContain(
                t('store.trips.form.position', { position: 2 }),
            );
            expect(
                wrapper
                    .findAll('button[aria-label]')
                    .map((button) => button.attributes('aria-label')),
            ).toEqual([
                t('store.trips.form.moveUp', { position: 1 }),
                t('store.trips.form.moveDown', { position: 1 }),
                t('store.trips.form.remove', { position: 1 }),
                t('store.trips.form.moveUp', { position: 2 }),
                t('store.trips.form.moveDown', { position: 2 }),
                t('store.trips.form.remove', { position: 2 }),
            ]);

            const dueDate = wrapper.get('#due_date');
            expect(dueDate.attributes('type')).toBe('date');
            expect(dueDate.attributes('required')).toBeDefined();
            expect(
                wrapper.get('#truck_id').attributes('required'),
            ).toBeDefined();
            expect(
                wrapper.get('#driver_id').attributes('required'),
            ).toBeUndefined();
            expect(wrapper.get('#notes').attributes('maxlength')).toBe('255');

            for (const select of wrapper.findAll(
                'select[name$="[checkpoint_related_id]"]',
            )) {
                expect(select.attributes('required')).toBeDefined();
            }

            for (const notes of wrapper.findAll(
                'textarea[name$="[checkpoint_notes]"]',
            )) {
                expect(notes.attributes('maxlength')).toBe('1000');
                expect(notes.attributes('required')).toBeUndefined();
            }
        },
    );

    it('starts a new trip with no checkpoints, no vehicles and a required row marker', () => {
        const wrapper = mountFields();

        expect(getMock).not.toHaveBeenCalled();
        expect(wrapper.getComponent(FormField).props()).toMatchObject({
            id: 'due_date',
            type: 'date',
            value: '',
        });
        expect(wrapper.text()).toContain(t('store.trips.form.empty'));
        const marker = wrapper.get('input.sr-only');
        expect(marker.attributes('required')).toBeDefined();
        expect(marker.attributes('aria-label')).toBe(
            t('store.trips.form.checkpointsRequired'),
        );
        marker.element.dispatchEvent(new Event('invalid'));
        expect((marker.element as HTMLInputElement).validationMessage).toBe(
            t('store.trips.form.checkpointsRequired'),
        );
        expect(
            wrapper
                .get('#driver_id')
                .findAll('option')
                .map((o) => o.text()),
        ).toEqual([t('store.trips.form.selectDriver')]);
        expect(wrapper.find('[role="status"]').exists()).toBe(false);
    });

    it('loads the free drivers and trucks for the chosen date and offers them', async () => {
        const wrapper = mountFields();
        const dueDate = wrapper.get('#due_date');
        (dueDate.element as HTMLInputElement).value = '2026-10-20';
        await dueDate.trigger('change');
        await flushPromises();

        expect(getMock).toHaveBeenCalledWith(vehiclesUrl('2026-10-20', 0));
        expect(
            wrapper
                .get('#driver_id')
                .findAll('option')
                .map((o) => o.text()),
        ).toEqual([
            t('store.trips.form.selectDriver'),
            'Sam Driver',
            'Noor Driver',
        ]);
        expect(
            wrapper
                .get('#truck_id')
                .findAll('option')
                .map((o) => o.text()),
        ).toEqual([
            t('store.trips.form.selectTruck'),
            'Cargo truck Volvo',
            'Van',
        ]);
        expect(wrapper.find('[role="status"]').exists()).toBe(false);
    });

    it('shows the date hint when no driver or truck is free or the lookup fails', async () => {
        getMock.mockResolvedValueOnce({ drivers: [], trucks: [] });
        const wrapper = mountFields(storeTripForm());
        await flushPromises();

        expect(wrapper.get('[role="status"]').text()).toBe(
            t('store.trips.form.noVehicles'),
        );
        expect(wrapper.text()).toContain(t('store.trips.form.noDrivers'));
        expect(wrapper.text()).toContain(t('store.trips.form.noTrucks'));
        expect(
            (wrapper.get('#truck_id').element as HTMLSelectElement).value,
        ).toBe('');

        getMock.mockRejectedValueOnce(new Error('offline'));
        const dueDate = wrapper.get('#due_date');
        (dueDate.element as HTMLInputElement).value = '2026-10-21';
        await dueDate.trigger('change');
        await flushPromises();
        expect(wrapper.get('[role="status"]').text()).toBe(
            t('store.trips.form.noVehicles'),
        );
        expect(wrapper.text()).not.toContain(t('store.trips.form.noDrivers'));
    });

    it("loads the trip's own date on edit, keeps its driver and truck and clears ones no longer free", async () => {
        const trip = storeTripForm();
        const wrapper = mountFields(trip);
        await flushPromises();

        expect(getMock).toHaveBeenCalledWith(vehiclesUrl('2026-10-14', 41));
        expect(wrapper.getComponent(FormField).props('value')).toBe(
            '2026-10-14',
        );
        expect(
            (wrapper.get('#driver_id').element as HTMLSelectElement).value,
        ).toBe('31');
        expect(
            (wrapper.get('#truck_id').element as HTMLSelectElement).value,
        ).toBe('11');
        expect(
            (wrapper.get('#notes').element as HTMLTextAreaElement).value,
        ).toBe('Ring at the back');

        getMock.mockResolvedValueOnce({
            drivers: [{ id: 32, name: 'Noor Driver' }],
            trucks: vehicles.trucks,
        });
        const dueDate = wrapper.get('#due_date');
        (dueDate.element as HTMLInputElement).value = '2026-10-15';
        await dueDate.trigger('change');
        await flushPromises();

        expect(getMock).toHaveBeenLastCalledWith(vehiclesUrl('2026-10-15', 41));
        expect(
            (wrapper.get('#driver_id').element as HTMLSelectElement).value,
        ).toBe('');
        expect(
            (wrapper.get('#truck_id').element as HTMLSelectElement).value,
        ).toBe('11');
    });

    it('shows stored checkpoints as text with their identity in hidden fields and editable notes', () => {
        const wrapper = mountFields(storeTripForm());
        const rows = wrapper.findAll('[data-testid="trip-checkpoint"]');

        expect(rows).toHaveLength(2);
        expect(rows[0].find('select').exists()).toBe(false);
        expect(rows[0].text()).toContain('Warehouse');
        expect(rows[0].text()).toContain(t('store.trips.form.types.other'));
        expect(rows[1].text()).toContain(
            t('store.trips.form.orderOption', {
                code: '20261013-0042',
                customer: 'Alma Customer',
            }),
        );
        const hidden = Object.fromEntries(
            rows[1]
                .findAll('input[type="hidden"]')
                .map((input) => [
                    input.attributes('name'),
                    input.attributes('value'),
                ]),
        );
        expect(hidden).toEqual({
            'checkpoints_array[1][id]': '502',
            'checkpoints_array[1][checkpoint_type]': 'delivery',
            'checkpoints_array[1][checkpoint_related_id]': '77',
        });
        const notes = rows[1].get('textarea');
        expect(notes.attributes('name')).toBe(
            'checkpoints_array[1][checkpoint_notes]',
        );
        expect((notes.element as HTMLTextAreaElement).value).toBe('Back door');
        expect(wrapper.find('input.sr-only').exists()).toBe(false);
    });

    it('adds order and customer rows with their choices and labels an order without a customer', async () => {
        const wrapper = mountFields(storeTripForm({ checkpoints: [] }));
        const buttons = wrapper.findAll('button');
        await buttons
            .find((button) => button.text() === t('store.trips.form.addOrder'))!
            .trigger('click');
        await buttons
            .find(
                (button) => button.text() === t('store.trips.form.addCustomer'),
            )!
            .trigger('click');

        const selects = wrapper.findAll(
            'select[name$="[checkpoint_related_id]"]',
        );
        expect(selects.map((select) => select.attributes('name'))).toEqual([
            'checkpoints_array[0][checkpoint_related_id]',
            'checkpoints_array[1][checkpoint_related_id]',
        ]);
        expect(selects[0].findAll('option').map((o) => o.text())).toEqual([
            t('store.trips.form.selectOrder'),
            t('store.trips.form.orderOption', {
                code: '20261013-0042',
                customer: 'Alma Customer',
            }),
            t('store.trips.form.orderOption', {
                code: '20261013-0041',
                customer: t('store.trips.form.unknownCustomer'),
            }),
        ]);
        expect(selects[1].findAll('option').map((o) => o.text())).toEqual([
            t('store.trips.form.selectCustomer'),
            'Warehouse',
            'Bram Customer',
        ]);
        expect(
            wrapper
                .findAll('input[name$="[id]"]')
                .map((input) => input.attributes('value')),
        ).toEqual(['0', '0']);
    });

    it('moves and removes rows, renumbering their field names and keeping their values', async () => {
        const wrapper = mountFields(storeTripForm());
        await wrapper
            .get('textarea[name="checkpoints_array[1][checkpoint_notes]"]')
            .setValue('Side door');
        const moveUp = wrapper.get(
            `button[aria-label="${t('store.trips.form.moveUp', { position: 2 })}"]`,
        );
        expect(
            wrapper
                .get(
                    `button[aria-label="${t('store.trips.form.moveUp', { position: 1 })}"]`,
                )
                .attributes('disabled'),
        ).toBeDefined();
        await moveUp.trigger('click');

        expect(rowNames(wrapper)).toEqual([
            'checkpoints_array[0][checkpoint_type]',
            'checkpoints_array[1][checkpoint_type]',
        ]);
        expect(
            wrapper
                .get('input[name="checkpoints_array[0][id]"]')
                .attributes('value'),
        ).toBe('502');
        expect(
            (
                wrapper.get(
                    'textarea[name="checkpoints_array[0][checkpoint_notes]"]',
                ).element as HTMLTextAreaElement
            ).value,
        ).toBe('Side door');

        await wrapper
            .get(
                `button[aria-label="${t('store.trips.form.remove', { position: 1 })}"]`,
            )
            .trigger('click');
        expect(
            wrapper
                .get('input[name="checkpoints_array[0][id]"]')
                .attributes('value'),
        ).toBe('501');
        expect(wrapper.findAll('[data-testid="trip-checkpoint"]')).toHaveLength(
            1,
        );
    });

    it('shows the field, row, checkpoint list and departure errors', () => {
        const wrapper = mountFields(storeTripForm(), {
            due_date: 'Due date missing',
            driver_id: 'Driver unknown',
            truck_id: 'Truck taken',
            notes: 'Notes too long',
            'checkpoints_array.1.checkpoint_related_id': 'Order unknown',
            departure: 'Departure missing',
        });
        const messages = wrapper
            .findAllComponents(FieldError)
            .map((error) => error.props('message'))
            .filter(Boolean);

        expect(messages).toEqual([
            'Due date missing',
            'Driver unknown',
            'Truck taken',
            'Notes too long',
            'Departure missing',
            'Order unknown',
        ]);
        expect(
            wrapper.findAll('[data-testid="trip-checkpoint"]')[1].text(),
        ).toContain('Order unknown');
    });
    it.each([undefined, storeTripForm()])(
        'marks editable checkpoint rows and keeps optional trip controls unmarked',
        async (trip) => {
            const wrapper = mountFields(trip);
            await flushPromises();

            for (const id of ['due_date', 'truck_id']) {
                expect(
                    wrapper.get(`#${id}`).attributes('required'),
                ).toBeDefined();
                expect(
                    wrapper
                        .find(`label[for="${id}"] [data-required-mark]`)
                        .exists(),
                ).toBe(true);
            }

            for (const id of ['driver_id', 'notes']) {
                expect(
                    wrapper.get(`#${id}`).attributes('required'),
                ).toBeUndefined();
                expect(
                    wrapper
                        .get(`label[for="${id}"]`)
                        .find('[data-required-mark]')
                        .exists(),
                ).toBe(false);
            }

            expect(wrapper.find('legend [data-required-mark]').exists()).toBe(
                true,
            );
            await wrapper
                .findAll('button')
                .find(
                    (button) =>
                        button.text() === t('store.trips.form.addOrder'),
                )!
                .trigger('click');
            await wrapper
                .findAll('button')
                .find(
                    (button) =>
                        button.text() === t('store.trips.form.addCustomer'),
                )!
                .trigger('click');

            for (const select of wrapper.findAll(
                'select[name$="[checkpoint_related_id]"]',
            )) {
                expect(select.attributes('required')).toBeDefined();
                expect(
                    wrapper
                        .find(
                            `label[for="${select.attributes('id')}"] [data-required-mark]`,
                        )
                        .exists(),
                ).toBe(true);
            }

            for (const textarea of wrapper.findAll(
                'textarea[name$="[checkpoint_notes]"]',
            )) {
                expect(textarea.attributes('required')).toBeUndefined();
                expect(
                    wrapper
                        .get(`label[for="${textarea.attributes('id')}"]`)
                        .find('[data-required-mark]')
                        .exists(),
                ).toBe(false);
            }

            wrapper.unmount();
        },
    );
});

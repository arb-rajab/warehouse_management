import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { updateStatus } from '@/actions/App/Http/Controllers/Store/Admin/TripController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { i18n, t } from '@/lib/i18n';
import { storeTrip } from '@/testing/factories';
import type { StoreTrip, StoreTripListType } from '@/types/store';
import StoreTripStatusDialog from './StoreTripStatusDialog.vue';

const { submitMock, stateMock } = vi.hoisted(() => ({
    submitMock: vi.fn(),
    stateMock: vi.fn(() => ({
        errors: {} as Record<string, string>,
        processing: false,
    })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createUseFormStub } = await import('@/testing/inertiaStubs');

    return { useForm: createUseFormStub(submitMock, stateMock) };
});

function mountDialog(
    trip: StoreTrip | null = storeTrip(),
    list: StoreTripListType = 'all',
) {
    return mount(StoreTripStatusDialog, {
        props: {
            open: true,
            trip,
            list,
            statuses: ['pending', 'started', 'closed'],
        },
    });
}

describe('StoreTripStatusDialog', () => {
    beforeEach(() => {
        submitMock.mockReset();
        stateMock.mockReturnValue({ errors: {}, processing: false });
    });
    afterEach(() => {
        vi.restoreAllMocks();
        i18n.global.locale.value = 'en';
    });

    it.each(['en', 'ar'] as const)(
        'titles the shared dialog with the trip and labels every status in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountDialog();
            expect(wrapper.getComponent(FilterDialog).props()).toMatchObject({
                title: t('store.trips.status.title', {
                    code: '20261014-080000',
                }),
                closeLabel: t('common.close'),
            });
            expect(wrapper.get('label[for="trip-status"]').text()).toBe(
                t('store.trips.status.label'),
            );
            expect(
                wrapper
                    .findAll('#trip-status option')
                    .map((option) => [
                        option.attributes('value'),
                        option.text(),
                    ]),
            ).toEqual(
                ['pending', 'started', 'closed'].map((status) => [
                    status,
                    t(`store.trips.status.${status}`),
                ]),
            );
            expect(wrapper.getComponent(SubmitButton).props()).toMatchObject({
                form: 'trip-status-form',
                label: t('store.trips.status.submit'),
                processingLabel: t('store.trips.status.submitting'),
                processing: false,
            });
            wrapper.unmount();
        },
    );

    it('preselects the trip status and puts the chosen one to its list', async () => {
        const trip = storeTrip({ status: 'pending' });
        const wrapper = mountDialog(trip, 'today');
        const select = wrapper.get<HTMLSelectElement>('#trip-status');
        expect(select.element.value).toBe('pending');
        expect(select.attributes('required')).toBeDefined();

        await select.setValue('closed');
        await wrapper.get('form').trigger('submit');

        expect(submitMock).toHaveBeenCalledWith(
            updateStatus(trip, { mergeQuery: { list: 'today' } }),
            expect.objectContaining({ status: 'closed' }),
            expect.objectContaining({ preserveScroll: true }),
        );
        expect(wrapper.emitted('update:open')).toBeUndefined();
        (submitMock.mock.calls[0][2] as { onSuccess: () => void }).onSuccess();
        expect(wrapper.emitted('update:open')).toEqual([[false]]);
        wrapper.unmount();
    });

    it('shows a refused transition and the processing state', () => {
        stateMock.mockReturnValue({
            errors: { truck_id: 'The truck is on another open trip.' },
            processing: true,
        });
        const wrapper = mountDialog();
        expect(wrapper.getComponent(ActionErrorBanner).props('message')).toBe(
            'The truck is on another open trip.',
        );
        expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(
            true,
        );
        wrapper.unmount();
    });

    it('resets to the next trip status when another trip opens it', async () => {
        const wrapper = mountDialog(storeTrip({ status: 'pending' }));
        await wrapper.get('#trip-status').setValue('closed');
        await wrapper.setProps({
            trip: storeTrip({ id: 42, status: 'started' }),
        });
        expect(
            wrapper.get<HTMLSelectElement>('#trip-status').element.value,
        ).toBe('started');
        wrapper.unmount();
    });

    it('submits nothing without a trip', async () => {
        const wrapper = mountDialog(null);
        await wrapper.get('form').trigger('submit');
        expect(submitMock).not.toHaveBeenCalled();
        wrapper.unmount();
    });
});

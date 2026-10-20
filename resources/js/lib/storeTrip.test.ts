import { afterEach, describe, expect, it } from 'vitest';
import { i18n, t } from '@/lib/i18n';
import { tripCheckpointLabel } from '@/lib/storeTrip';

describe('tripCheckpointLabel', () => {
    afterEach(() => {
        i18n.global.locale.value = 'en';
    });

    it.each(['en', 'ar'] as const)(
        'labels a delivery by its order and customer and another stop by its customer in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            expect(tripCheckpointLabel('delivery', '0042', 'Alma')).toBe(
                t('store.trips.form.orderOption', {
                    code: '0042',
                    customer: 'Alma',
                }),
            );
            expect(tripCheckpointLabel('other', null, 'Alma')).toBe('Alma');
        },
    );

    it('falls back for a missing order or customer', () => {
        expect(tripCheckpointLabel('delivery', null, null)).toBe(
            `#- ${t('store.trips.form.unknownCustomer')}`,
        );
        expect(tripCheckpointLabel('delivery', 77, null)).toBe(
            `#77 ${t('store.trips.form.unknownCustomer')}`,
        );
        expect(tripCheckpointLabel('other', '0042', null)).toBe(
            t('store.trips.form.unknownCustomer'),
        );
    });
});

import { t } from '@/lib/i18n';
import type { StoreTripCheckpointType } from '@/types/store';

/**
 * What a stored checkpoint shows for its stop, on the trip edit form and the
 * trip detail: the order's code and customer for a delivery, otherwise the
 * customer (.ai/archive/store-backoffice/changes-20261015.md §25).
 */
export function tripCheckpointLabel(
    type: StoreTripCheckpointType,
    orderCode: string | number | null,
    customer: string | null,
): string {
    const name = customer ?? t('store.trips.form.unknownCustomer');

    return type === 'delivery'
        ? t('store.trips.form.orderOption', {
              code: orderCode ?? '-',
              customer: name,
          })
        : name;
}

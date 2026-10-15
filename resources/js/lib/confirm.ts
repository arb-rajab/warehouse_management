import { t } from '@/lib/i18n';

/**
 * Ask before an irreversible delete. `label` names the thing being deleted, e.g.
 * `row Z` or a user's name, and is interpolated into the shared wording.
 */
export function confirmDelete(label: string): boolean {
    return confirm(t('common.confirmDelete', { label }));
}

/**
 * Ask before moving orders to another delivery status, which the trip orders
 * list does at once. `label` names the orders and `status` the new status.
 */
export function confirmDeliveryStatus(label: string, status: string): boolean {
    return confirm(t('store.trips.orders.confirmStatus', { label, status }));
}

/**
 * Ask before removing a user. Unlike `confirmDelete` this isn't a "cannot be
 * undone" wording: a removed user keeps their activity history, they only lose
 * access.
 */
export function confirmDeleteUser(name: string): boolean {
    return confirm(t('users.index.confirmDelete', { label: name }));
}

/**
 * Ask before logging out, to guard against an accidental click on the nav's logout link.
 */
export function confirmLogout(): boolean {
    return confirm(t('nav.confirmLogout'));
}

/**
 * Ask before accepting a verification report, which rewrites what the system
 * holds for `slot` (e.g. `A3·2`) to match what the worker reported.
 */
export function confirmAcceptVerificationReport(slot: string): boolean {
    return confirm(t('cellVerificationReport.acceptConfirm', { slot }));
}

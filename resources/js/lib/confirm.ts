import { t } from '@/lib/i18n';

/**
 * Ask before an irreversible delete. `label` names the thing being deleted, e.g.
 * `row Z` or a user's name, and is interpolated into the shared wording.
 */
export function confirmDelete(label: string): boolean {
    return confirm(t('common.confirmDelete', { label }));
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

import { t } from '@/lib/i18n';

/**
 * Ask before an irreversible delete. `label` names the thing being deleted, e.g.
 * `row Z` or a user's name, and is interpolated into the shared wording.
 */
export function confirmDelete(label: string): boolean {
    return confirm(t('common.confirmDelete', { label }));
}

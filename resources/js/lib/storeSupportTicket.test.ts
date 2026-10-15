import { afterEach, describe, expect, it } from 'vitest';
import { i18n, t } from '@/lib/i18n';
import {
    isTicketStatus,
    ticketStatusLabel,
    ticketStatuses,
} from './storeSupportTicket';

describe('storeSupportTicket', () => {
    afterEach(() => {
        i18n.global.locale.value = 'en';
    });

    it('lists the statuses a reply may set', () => {
        expect(ticketStatuses).toEqual(['pending', 'open', 'solved']);
        expect(ticketStatuses.map(isTicketStatus)).toEqual([true, true, true]);
        expect(isTicketStatus('closed')).toBe(false);
        expect(isTicketStatus('')).toBe(false);
    });

    it.each(['en', 'ar'] as const)(
        'labels each status in %s and reads anything else as solved',
        (locale) => {
            i18n.global.locale.value = locale;
            expect(ticketStatusLabel('pending')).toBe(
                t('store.support.pending'),
            );
            expect(ticketStatusLabel('open')).toBe(t('store.support.open'));
            expect(ticketStatusLabel('solved')).toBe(t('store.support.solved'));
            expect(ticketStatusLabel('closed')).toBe(t('store.support.solved'));
        },
    );
});

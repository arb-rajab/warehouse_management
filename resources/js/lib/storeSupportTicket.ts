import { t } from '@/lib/i18n';

/** The statuses a staff reply may set (ReplySupportTicketRequest). */
export const ticketStatuses = ['pending', 'open', 'solved'] as const;

export type TicketStatus = (typeof ticketStatuses)[number];

export function isTicketStatus(status: string): status is TicketStatus {
    return (ticketStatuses as readonly string[]).includes(status);
}

/** Any status other than pending or open reads as solved, as the list shows it. */
export function ticketStatusLabel(status: string): string {
    return t(
        `store.support.${status === 'pending' || status === 'open' ? status : 'solved'}`,
    );
}

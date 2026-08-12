import { afterEach, describe, expect, it } from 'vitest';
import { formatDate, formatDateTime, formatDuration } from './date';
import { i18n } from './i18n';

describe('formatDate', () => {
    afterEach(() => {
        i18n.global.locale.value = 'en';
    });

    it('formats a date-only string without a time component', () => {
        expect(formatDate('2026-08-20')).toBe('Aug 20, 2026');
    });

    it('formats with Arabic month names and Western digits when the locale is Arabic', () => {
        i18n.global.locale.value = 'ar';

        const formatted = formatDate('2026-08-20');

        expect(formatted).toContain('أغسطس');
        expect(formatted).toContain('2026');
        expect(formatted).not.toMatch(/[٠-٩]/);
    });
});

describe('formatDateTime', () => {
    afterEach(() => {
        i18n.global.locale.value = 'en';
    });

    it('formats an ISO timestamp with both date and time components', () => {
        const formatted = formatDateTime('2026-08-20T15:45:00Z');

        expect(formatted).toContain('Aug 20, 2026');
        expect(formatted).toMatch(/\d{1,2}:\d{2}/);
    });

    it('shares the same date formatting as formatDate', () => {
        const dateOnly = formatDate('2026-08-20');
        const dateTime = formatDateTime('2026-08-20T00:00:00');

        expect(dateTime.startsWith(dateOnly)).toBe(true);
    });

    it('formats with Arabic month names and Western digits when the locale is Arabic', () => {
        i18n.global.locale.value = 'ar';

        const formatted = formatDateTime('2026-08-20T15:45:00Z');

        expect(formatted).toContain('أغسطس');
        expect(formatted).not.toMatch(/[٠-٩]/);
    });
});

describe('formatDuration', () => {
    afterEach(() => {
        i18n.global.locale.value = 'en';
    });

    it('formats days and hours when at least a day has passed', () => {
        expect(formatDuration(2 * 86400 + 3 * 3600)).toBe('2d 3h');
    });

    it('formats hours and minutes when under a day', () => {
        expect(formatDuration(3 * 3600 + 15 * 60)).toBe('3h 15m');
    });

    it('formats minutes when under an hour', () => {
        expect(formatDuration(45 * 60)).toBe('45m');
    });

    it('formats seconds when under a minute', () => {
        expect(formatDuration(30)).toBe('30s');
    });

    it('translates the unit suffixes with Western digits when the locale is Arabic', () => {
        i18n.global.locale.value = 'ar';

        expect(formatDuration(2 * 86400 + 3 * 3600)).toBe('2ي 3س');
        expect(formatDuration(3 * 3600 + 15 * 60)).toBe('3س 15د');
        expect(formatDuration(45 * 60)).toBe('45د');
        expect(formatDuration(30)).toBe('30ث');
    });
});

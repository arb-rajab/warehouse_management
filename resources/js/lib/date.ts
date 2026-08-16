import { i18n, t } from './i18n';

const DATE_OPTIONS: Intl.DateTimeFormatOptions = {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
};

const DATE_TIME_OPTIONS: Intl.DateTimeFormatOptions = {
    ...DATE_OPTIONS,
    hour: 'numeric',
    minute: '2-digit',
};

/**
 * Intl locale tag for the app's current i18n locale. Arabic keeps Western
 * (`latn`) digits via the `-u-nu-latn` extension — the default `ar` numbering
 * system renders Arabic-Indic digits, which this app's UI doesn't otherwise use.
 */
function currentLocaleTag(): string {
    return i18n.global.locale.value === 'ar' ? 'ar-u-nu-latn' : 'en-US';
}

export function formatDate(value: string): string {
    return new Date(value).toLocaleDateString(currentLocaleTag(), DATE_OPTIONS);
}

export function formatDateTime(value: string): string {
    return new Date(value).toLocaleString(
        currentLocaleTag(),
        DATE_TIME_OPTIONS,
    );
}

/**
 * Subtracts `days` from a `YYYY-MM-DD` date string, returning a `YYYY-MM-DD`
 * string — plain date arithmetic anchored on the given date, never the
 * browser clock (the caller passes in the server-provided "today").
 */
export function subtractDays(dateString: string, days: number): string {
    const date = new Date(`${dateString}T00:00:00Z`);
    date.setUTCDate(date.getUTCDate() - days);

    return date.toISOString().slice(0, 10);
}

/**
 * Adds `days` to a `YYYY-MM-DD` date string, returning a `YYYY-MM-DD`
 * string — plain date arithmetic anchored on the given date, never the
 * browser clock (the caller passes in the server-provided "today").
 */
export function addDays(dateString: string, days: number): string {
    const date = new Date(`${dateString}T00:00:00Z`);
    date.setUTCDate(date.getUTCDate() + days);

    return date.toISOString().slice(0, 10);
}

/**
 * A short "2d 3h" / "45m" / "30s" label for a duration in seconds — the two
 * largest non-zero units, dropping to a single unit once it's the smallest.
 * Unit suffixes are translated (see `common.duration.*`); Arabic keeps
 * Western digits, matching `formatDate`/`formatDateTime`.
 */
export function formatDuration(seconds: number): string {
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    const day = t('common.duration.day');
    const hour = t('common.duration.hour');
    const minute = t('common.duration.minute');
    const second = t('common.duration.second');

    if (days > 0) {
        return `${days}${day} ${hours}${hour}`;
    }

    if (hours > 0) {
        return `${hours}${hour} ${minutes}${minute}`;
    }

    if (minutes > 0) {
        return `${minutes}${minute}`;
    }

    return `${seconds}${second}`;
}

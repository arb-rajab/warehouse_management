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

export function formatDate(value: string): string {
    return new Date(value).toLocaleDateString(undefined, DATE_OPTIONS);
}

export function formatDateTime(value: string): string {
    return new Date(value).toLocaleString(undefined, DATE_TIME_OPTIONS);
}

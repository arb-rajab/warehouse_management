import { currentLocaleTag } from '@/lib/date';
import { t } from '@/lib/i18n';
import type { StoreUpload, StoreUploadSort } from '@/types/store';

/** FilterUploadsRequest::SORTS, in the order the sort menus offer them. */
export const STORE_UPLOAD_SORTS: StoreUploadSort[] = [
    'newest',
    'oldest',
    'smallest',
    'largest',
];

const FILE_SIZE_UNITS = [
    'byte',
    'kilobyte',
    'megabyte',
    'gigabyte',
    'terabyte',
] as const;

/**
 * A file size in the largest 1024-based unit that keeps it at or above one,
 * as the Blade library's `formatBytes()` printed it, with the unit named in
 * the current language.
 */
export function formatFileSize(bytes: number | null): string {
    let value = Math.max(bytes ?? 0, 0);
    let unit = 0;

    while (value >= 1024 && unit < FILE_SIZE_UNITS.length - 1) {
        value /= 1024;
        unit++;
    }

    return new Intl.NumberFormat(currentLocaleTag(), {
        style: 'unit',
        unit: FILE_SIZE_UNITS[unit],
        unitDisplay: 'short',
        maximumFractionDigits: 2,
    }).format(value);
}

/** The file's name with its extension, or "Unknown" when it came without one. */
export function uploadDisplayName(upload: StoreUpload): string {
    const name = upload.name ?? t('store.uploads.unknownName');

    return upload.extension ? `${name}.${upload.extension}` : name;
}

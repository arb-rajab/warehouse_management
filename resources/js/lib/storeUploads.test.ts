import { afterEach, describe, expect, it } from 'vitest';
import { i18n, t } from '@/lib/i18n';
import {
    formatFileSize,
    STORE_UPLOAD_SORTS,
    uploadDisplayName,
} from '@/lib/storeUploads';
import { storeUpload } from '@/testing/factories';

describe('formatFileSize', () => {
    afterEach(() => {
        i18n.global.locale.value = 'en';
    });

    it('prints bytes in the largest 1024-based unit that keeps the value at least one', () => {
        expect(formatFileSize(0)).toBe('0 byte');
        expect(formatFileSize(512)).toBe('512 byte');
        expect(formatFileSize(1024)).toBe('1 kB');
        expect(formatFileSize(1536)).toBe('1.5 kB');
        expect(formatFileSize(5 * 1024 * 1024)).toBe('5 MB');
        expect(formatFileSize(3 * 1024 ** 3)).toBe('3 GB');
        expect(formatFileSize(2 * 1024 ** 5)).toBe('2,048 TB');
    });

    it('treats a missing or negative size as zero', () => {
        expect(formatFileSize(null)).toBe('0 byte');
        expect(formatFileSize(-5)).toBe('0 byte');
    });

    it('names the unit in Arabic with Western digits', () => {
        i18n.global.locale.value = 'ar';

        const formatted = formatFileSize(1536);

        expect(formatted).toContain('1.5');
        expect(formatted).not.toContain('kB');
        expect(formatted).not.toMatch(/[٠-٩]/);
    });
});

describe('uploadDisplayName', () => {
    it('joins the name and the extension', () => {
        expect(uploadDisplayName(storeUpload())).toBe('shop front.png');
    });

    it('says Unknown for a file uploaded without a name', () => {
        expect(uploadDisplayName(storeUpload({ name: null }))).toBe(
            `${t('store.uploads.unknownName')}.png`,
        );
        expect(t('store.uploads.unknownName')).toBe('Unknown');
    });

    it('leaves off a missing extension', () => {
        expect(uploadDisplayName(storeUpload({ extension: null }))).toBe(
            'shop front',
        );
    });
});

describe('STORE_UPLOAD_SORTS', () => {
    it('lists FilterUploadsRequest::SORTS, newest first', () => {
        expect(STORE_UPLOAD_SORTS).toEqual([
            'newest',
            'oldest',
            'smallest',
            'largest',
        ]);
    });
});

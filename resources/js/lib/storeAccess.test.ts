import { beforeEach, describe, expect, it, vi } from 'vitest';
import { storeAccess, useStoreAccess } from '@/lib/storeAccess';
import { storeSharedProps } from '@/testing/factories';

const { usePageMock } = vi.hoisted(() => ({ usePageMock: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({ usePage: usePageMock }));

describe('storeAccess', () => {
    it('allows a permission the user holds, and nothing else', () => {
        const access = storeAccess(
            storeSharedProps({ permissions: ['view_all_staffs'] }),
        );

        expect(access.can('view_all_staffs')).toBe(true);
        expect(access.can('delete_staff')).toBe(false);
    });

    it('allows every permission to a Super Admin', () => {
        const access = storeAccess(storeSharedProps({ isSuperAdmin: true }));

        expect(access.can('delete_staff')).toBe(true);
    });

    it('reads the raizer admin flag and the section settings', () => {
        const access = storeAccess(
            storeSharedProps({
                isRaizerAdmin: true,
                settings: { Staffs_section: true, Blog_Section: false },
            }),
        );

        expect(access.isRaizerAdmin).toBe(true);
        expect(access.setting('Staffs_section')).toBe(true);
        expect(access.setting('Blog_Section')).toBe(false);
        expect(access.setting('Unknown_section')).toBe(false);
    });

    it('allows nothing outside the store, where the props are absent', () => {
        const access = storeAccess(undefined);

        expect(access.can('view_all_staffs')).toBe(false);
        expect(access.isRaizerAdmin).toBe(false);
        expect(access.setting('Staffs_section')).toBe(false);
    });
});

describe('useStoreAccess', () => {
    beforeEach(() => {
        usePageMock.mockReset();
    });

    it("reads the current page's store props", () => {
        usePageMock.mockReturnValue({
            props: { store: storeSharedProps({ permissions: ['add_staff'] }) },
        });

        expect(useStoreAccess().value.can('add_staff')).toBe(true);
    });
});

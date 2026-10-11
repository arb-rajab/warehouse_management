import { describe, expect, it } from 'vitest';
import {
    classifiedProduct,
    cell,
    cellPallet,
    cellLog,
    cellVerificationReport,
    cellVerificationRound,
    cellWithLocation,
    paginated,
    permissionGroup,
    row,
    staffMember,
    storeRole,
    storeSharedProps,
    user,
} from './factories';

describe('row', () => {
    it('applies default fields', () => {
        expect(row()).toMatchObject({
            id: 1,
            letter: 'A',
            cells_count: 5,
            flats_count: 7,
            has_pallets: false,
        });
    });

    it('merges overrides on top of the defaults, without dropping untouched fields', () => {
        expect(row({ letter: 'B', has_pallets: true })).toMatchObject({
            id: 1,
            letter: 'B',
            cells_count: 5,
            flats_count: 7,
            has_pallets: true,
        });
    });
});

describe('cell', () => {
    it('applies default fields', () => {
        expect(cell()).toMatchObject({
            id: 1,
            cell_number: 1,
            flat_number: 1,
            state: 'empty',
            is_active: true,
            pallet: null,
        });
    });

    it('merges overrides on top of the defaults, without dropping untouched fields', () => {
        expect(cell({ state: 'full', is_active: false })).toMatchObject({
            id: 1,
            cell_number: 1,
            state: 'full',
            is_active: false,
        });
    });
});

describe('user', () => {
    it('applies default fields', () => {
        expect(user()).toMatchObject({
            id: 7,
            name: 'Jane Doe',
            email: 'jane@example.com',
            is_admin: false,
        });
    });

    it('merges overrides on top of the defaults, without dropping untouched fields', () => {
        expect(user({ is_admin: true })).toMatchObject({
            id: 7,
            name: 'Jane Doe',
            is_admin: true,
        });
    });
});

describe('cellVerificationReport', () => {
    it('applies default fields', () => {
        const result = cellVerificationReport();

        expect(result).toMatchObject({
            id: 1,
            cell_verification_round_id: 1,
            is_correct: true,
            cell: { row_letter: 'A', cell_number: 3, flat_number: 2 },
        });
        expect(result.expected).toEqual(result.reported);
    });

    it('merges overrides on top of the defaults, without dropping untouched fields', () => {
        const result = cellVerificationReport({
            is_correct: false,
            note: 'Mismatch',
        });

        expect(result).toMatchObject({
            id: 1,
            is_correct: false,
            note: 'Mismatch',
        });
        expect(result.expected).toBeDefined();
    });
});

describe('cellVerificationRound', () => {
    it('applies default fields', () => {
        expect(cellVerificationRound()).toMatchObject({
            id: 1,
            reports_count: 3,
            user: { id: 7, name: 'Jane Doe' },
        });
    });

    it('merges overrides on top of the defaults, without dropping untouched fields', () => {
        expect(cellVerificationRound({ reports_count: 9 })).toMatchObject({
            id: 1,
            reports_count: 9,
            user: { id: 7, name: 'Jane Doe' },
        });
    });
});

describe('cellLog', () => {
    it('applies default fields', () => {
        expect(cellLog()).toMatchObject({
            id: 1,
            action: 'stored',
            from_state: 'empty',
            to_state: 'full',
            flagged: false,
            flags: [],
        });
    });

    it('merges overrides on top of the defaults, without dropping untouched fields', () => {
        expect(
            cellLog({ flagged: true, action: 'boxes_removed' }),
        ).toMatchObject({
            id: 1,
            action: 'boxes_removed',
            flagged: true,
            to_state: 'full',
        });
    });
});

describe('paginated', () => {
    it('sets from/to/total for non-empty data', () => {
        const result = paginated(['a', 'b', 'c'], 20);

        expect(result.meta).toMatchObject({
            current_page: 1,
            last_page: 1,
            per_page: 20,
            total: 3,
            from: 1,
            to: 3,
        });
    });

    it('sets from to null and to/total to 0 for empty data', () => {
        const result = paginated([]);

        expect(result.meta).toMatchObject({
            total: 0,
            from: null,
            to: 0,
        });
    });
});

describe('paginated page overrides', () => {
    it('sets the current and last page', () => {
        const result = paginated(['a'], 20, { currentPage: 1, lastPage: 3 });

        expect(result.meta).toMatchObject({ current_page: 1, last_page: 3 });
    });
});

describe('cellWithLocation', () => {
    it('adds a row letter to the base cell and applies overrides', () => {
        expect(cellWithLocation()).toEqual({ ...cell(), row_letter: 'A' });
        expect(
            cellWithLocation({ row_letter: 'B', cell_number: 4 }),
        ).toMatchObject({ row_letter: 'B', cell_number: 4 });
    });
});

describe('cellPallet', () => {
    it('builds a pallet and applies overrides', () => {
        expect(cellPallet()).toMatchObject({ id: 42, remaining_boxes: 10 });
        expect(cellPallet({ remaining_boxes: 3 }).remaining_boxes).toBe(3);
    });
});

describe('storeSharedProps', () => {
    it('applies default fields', () => {
        expect(storeSharedProps()).toEqual({
            rtl: false,
            permissions: [],
            isSuperAdmin: false,
            isRaizerAdmin: false,
            settings: {},
        });
    });

    it('merges overrides on top of the defaults, without dropping untouched fields', () => {
        expect(storeSharedProps({ permissions: ['add_staff'] })).toEqual({
            rtl: false,
            permissions: ['add_staff'],
            isSuperAdmin: false,
            isRaizerAdmin: false,
            settings: {},
        });
    });
});

describe('storeRole', () => {
    it('applies default fields', () => {
        expect(storeRole()).toEqual({ id: 3, name: 'Clerk' });
    });

    it('merges overrides on top of the defaults, without dropping untouched fields', () => {
        expect(storeRole({ name: 'Manager' })).toEqual({
            id: 3,
            name: 'Manager',
        });
    });
});

describe('staffMember', () => {
    it('applies default fields', () => {
        expect(staffMember()).toEqual({
            id: 11,
            user_id: 21,
            name: 'Sam Staff',
            email: 'sam@example.com',
            phone: '0612345678',
            role: { id: 3, name: 'Clerk' },
            is_super_admin: false,
        });
    });

    it('merges overrides on top of the defaults, without dropping untouched fields', () => {
        expect(staffMember({ role: null, is_super_admin: true })).toMatchObject(
            {
                id: 11,
                name: 'Sam Staff',
                role: null,
                is_super_admin: true,
            },
        );
    });
});

describe('permissionGroup', () => {
    it('applies default fields', () => {
        expect(permissionGroup()).toEqual({
            section: 'staff',
            label: 'Staff',
            permissions: [
                { id: 1, label: 'View All Staffs' },
                { id: 2, label: 'Add Staff' },
            ],
        });
    });

    it('merges overrides on top of the defaults, without dropping untouched fields', () => {
        expect(permissionGroup({ label: 'Orders' })).toMatchObject({
            section: 'staff',
            label: 'Orders',
        });
    });
});

describe('classifiedProduct', () => {
    it('supplies the full administrative shape and allows overrides', () => {
        expect(classifiedProduct({ published: true })).toEqual({
            id: 1,
            name: 'Customer advert',
            public_url: '/temp/customer-product/customer-advert',
            added_by: 'customer',
            status: true,
            published: true,
            thumbnail_url: null,
        });
    });
});

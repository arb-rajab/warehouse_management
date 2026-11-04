import { formatDate, formatDateTime, currentLocaleTag } from '@/lib/date';
import { t } from '@/lib/i18n';
import type { StoreCustomer } from '@/types/storeCustomers';

/** One toggleable column of the customers / representatives list. */
export interface CustomerColumn {
    /** The `StoreCustomer` field the cell shows (or a special cell: `package`, `status`). */
    key: string;
    /** The translation key of the header and of the chooser's checkbox. */
    label: string;
    /** Shown until the admin chooses otherwise. */
    defaultVisible: boolean;
    /** Only the representatives list has this column. */
    representativesOnly?: boolean;
    /** Only an admin who may edit customers receives the data (`edit_customer`). */
    restricted?: boolean;
    /** Long free text: kept to a narrow, truncated cell. */
    longText?: boolean;
}

const field = (key: string) => `store.customers.fields.${key}`;

/**
 * Every column the list can show, in table order. Select and Actions are not
 * listed: they are always shown. A column's data comes from
 * `CustomerListResource`; secrets (password hash, tokens, verification codes)
 * are never part of it.
 */
export const customerColumns: CustomerColumn[] = [
    { key: 'id', label: field('id'), defaultVisible: true },
    { key: 'name', label: field('name'), defaultVisible: true },
    { key: 'company_name', label: field('company_name'), defaultVisible: true },
    { key: 'email', label: field('email'), defaultVisible: true },
    { key: 'phone', label: field('phone'), defaultVisible: true },
    {
        key: 'package',
        label: 'store.customers.package',
        defaultVisible: true,
        representativesOnly: true,
    },
    { key: 'profits', label: 'store.customers.profits', defaultVisible: true },
    { key: 'status', label: 'store.customers.status', defaultVisible: true },
    { key: 'country', label: field('country'), defaultVisible: false },
    {
        key: 'tax_number',
        label: field('tax_number'),
        defaultVisible: false,
        restricted: true,
    },
    {
        key: 'trade_license',
        label: 'store.customers.tradeLicense',
        defaultVisible: false,
        restricted: true,
    },
    {
        key: 'bank_account_number',
        label: field('bank_account_number'),
        defaultVisible: false,
        restricted: true,
    },
    {
        key: 'company_address',
        label: field('company_address'),
        defaultVisible: false,
        longText: true,
    },
    {
        key: 'shipping_address',
        label: field('shipping_address'),
        defaultVisible: false,
        longText: true,
    },
    {
        key: 'member_serial',
        label: field('member_serial'),
        defaultVisible: false,
    },
    { key: 'AccSysID', label: field('AccSysID'), defaultVisible: false },
    { key: 'rep_serial', label: field('rep_serial'), defaultVisible: false },
    { key: 'rep_id', label: field('rep_id'), defaultVisible: false },
    {
        key: 'admin_notes',
        label: 'store.customers.notes',
        defaultVisible: false,
        longText: true,
    },
    { key: 'user_type', label: field('user_type'), defaultVisible: false },
    {
        key: 'activity_status',
        label: 'store.customers.activity',
        defaultVisible: false,
    },
    {
        key: 'registration_completed',
        label: field('registration_completed'),
        defaultVisible: false,
    },
    { key: 'from_api', label: field('from_api'), defaultVisible: false },
    {
        key: 'email_verified_at',
        label: field('email_verified_at'),
        defaultVisible: false,
    },
    {
        key: 'admin_verified_at',
        label: field('admin_verified_at'),
        defaultVisible: false,
    },
    {
        key: 'last_order_at',
        label: field('last_order_at'),
        defaultVisible: false,
    },
    { key: 'created_at', label: field('created_at'), defaultVisible: false },
    { key: 'updated_at', label: field('updated_at'), defaultVisible: false },
    {
        key: 'rep_discount_percentage',
        label: field('rep_discount_percentage'),
        defaultVisible: false,
        representativesOnly: true,
    },
    {
        key: 'balance',
        label: field('balance'),
        defaultVisible: false,
        restricted: true,
    },
    {
        key: 'address',
        label: field('address'),
        defaultVisible: false,
        longText: true,
    },
    { key: 'state', label: field('state'), defaultVisible: false },
    { key: 'city', label: field('city'), defaultVisible: false },
    { key: 'postal_code', label: field('postal_code'), defaultVisible: false },
];

/** The columns this list and this admin can have at all. */
export function availableCustomerColumns(
    representatives: boolean,
    canSeeRestricted: boolean,
): CustomerColumn[] {
    return customerColumns.filter(
        (column) =>
            (representatives || !column.representativesOnly) &&
            (canSeeRestricted || !column.restricted),
    );
}

export function defaultColumnKeys(available: CustomerColumn[]): string[] {
    return available
        .filter((column) => column.defaultVisible)
        .map((column) => column.key);
}

/**
 * The remembered choice for a list, limited to columns that are available
 * now. Storage can be empty, blocked or hold junk: the defaults then apply.
 */
export function loadColumnKeys(
    storageKey: string,
    available: CustomerColumn[],
): string[] {
    try {
        const stored: unknown = JSON.parse(
            window.localStorage.getItem(storageKey) ?? 'null',
        );

        if (Array.isArray(stored)) {
            const keys = available
                .map((column) => column.key)
                .filter((key) => stored.includes(key));

            if (keys.length > 0) {
                return keys;
            }
        }
    } catch {
        // Unreadable storage: fall through to the defaults.
    }

    return defaultColumnKeys(available);
}

export function saveColumnKeys(storageKey: string, keys: string[]): void {
    try {
        window.localStorage.setItem(storageKey, JSON.stringify(keys));
    } catch {
        // Blocked storage: the choice just lasts for this page view.
    }
}

/** A profit total with two decimals in the current locale's tag. */
export function formatProfit(value: number): string {
    return value.toLocaleString(currentLocaleTag(), {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

const dateTimeKeys = ['email_verified_at', 'created_at', 'updated_at'];
const dateKeys = ['admin_verified_at', 'last_order_at'];
const flagKeys = ['registration_completed', 'from_api'];

/** The plain text a generic column's cell shows for a customer. */
export function customerCellText(customer: StoreCustomer, key: string): string {
    const value = (customer as unknown as Record<string, unknown>)[key];

    if (value === null || value === undefined || value === '') {
        return '';
    }

    if (flagKeys.includes(key)) {
        return t(value ? 'store.customers.yes' : 'store.customers.no');
    }

    if (dateTimeKeys.includes(key)) {
        return formatDateTime(String(value));
    }

    if (dateKeys.includes(key)) {
        return formatDate(String(value));
    }

    if (key === 'activity_status') {
        return t(
            value === 'has_been_contacted'
                ? 'store.customers.contacted'
                : 'store.customers.newUser',
        );
    }

    if (key === 'rep_discount_percentage') {
        return `${value}%`;
    }

    if (key === 'profits') {
        return formatProfit(Number(value));
    }

    return String(value);
}

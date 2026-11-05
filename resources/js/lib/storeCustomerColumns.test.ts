import { expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import {
    availableCustomerColumns,
    customerCellText,
    customerColumns,
    formatProfit,
} from '@/lib/storeCustomerColumns';
import { storeCustomer } from '@/testing/factories';

it('lists each column once and never a secret', () => {
    const keys = customerColumns.map((column) => column.key);
    expect(new Set(keys).size).toBe(keys.length);

    for (const secret of [
        'password',
        'remember_token',
        'verification_code',
        'device_token',
        'device_key',
        'provider_id',
        'access_token',
        'refresh_token',
    ]) {
        expect(keys).not.toContain(secret);
    }
});

it('limits the columns to the list and to what the viewer may see', () => {
    const keys = (representatives: boolean, restricted: boolean) =>
        availableCustomerColumns(representatives, restricted).map(
            (column) => column.key,
        );
    expect(keys(false, false)).not.toContain('package');
    expect(keys(false, false)).not.toContain('rep_discount_percentage');
    expect(keys(true, false)).toContain('package');
    expect(keys(true, false)).toContain('rep_discount_percentage');

    for (const key of [
        'tax_number',
        'trade_license',
        'bank_account_number',
        'balance',
    ]) {
        expect(keys(false, false)).not.toContain(key);
        expect(keys(false, true)).toContain(key);
    }
});

const defaultKeys = (representatives: boolean, restricted: boolean) =>
    availableCustomerColumns(representatives, restricted)
        .filter((column) => column.defaultVisible)
        .map((column) => column.key);
it('shows the core columns by default and always keeps the name', () => {
    expect(defaultKeys(false, true)).toEqual([
        'id',
        'name',
        'company_name',
        'email',
        'phone',
        'profits',
        'status',
    ]);
    expect(defaultKeys(true, true)).toContain('package');
    expect(customerColumns.filter((column) => column.required)).toEqual([
        expect.objectContaining({ key: 'name' }),
    ]);
});

it('formats profits with two decimals', () => {
    expect(formatProfit(0)).toBe('0.00');
    expect(formatProfit(1234.5)).toBe('1,234.50');
});

it('renders each kind of cell as text', () => {
    const customer = storeCustomer({
        country: 'Netherlands',
        registration_completed: true,
        from_api: false,
        activity_status: 'has_been_contacted',
        rep_discount_percentage: 12.5,
        last_order_at: '2026-10-30',
        created_at: '2026-11-01T12:00:00+00:00',
        profits: 7,
        admin_notes: null,
        balance: '9.00',
    });
    expect(customerCellText(customer, 'country')).toBe('Netherlands');
    expect(customerCellText(customer, 'registration_completed')).toBe(
        t('store.customers.yes'),
    );
    expect(customerCellText(customer, 'from_api')).toBe(
        t('store.customers.no'),
    );
    expect(customerCellText(customer, 'activity_status')).toBe(
        t('store.customers.contacted'),
    );
    expect(
        customerCellText(
            { ...customer, activity_status: 'new_user' },
            'activity_status',
        ),
    ).toBe(t('store.customers.newUser'));
    expect(customerCellText(customer, 'rep_discount_percentage')).toBe('12.5%');
    expect(customerCellText(customer, 'last_order_at')).toContain('2026');
    expect(customerCellText(customer, 'created_at')).toContain('2026');
    expect(customerCellText(customer, 'profits')).toBe('7.00');
    expect(customerCellText(customer, 'balance')).toBe('9.00');
    expect(customerCellText(customer, 'admin_notes')).toBe('');
    expect(customerCellText(customer, 'state')).toBe('');
});

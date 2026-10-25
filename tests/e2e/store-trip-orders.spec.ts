import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { php } from './support/editor';
import { seedStoreFixtures } from './support/storeFixtures';

/** An order of the seeded browser buyer with `id`, `code` and `status`. */
function seedOrder(id: number, code: string, status: string): void {
    seedStoreFixtures(String.raw`
        $buyer = App\Models\StoreUser::query()->where('email', 'browser-buyer@example.com')->firstOrFail();
        $order = new App\Models\Order;
        $order->forceFill([
            'id' => ${id},
            'user_id' => $buyer->id,
            'shipping_type' => 'home_delivery',
            'shipping_address' => json_encode(['address' => 'Main street 5', 'country' => 'Netherlands', 'phone' => '0611111111']),
            'payment_type' => 'cash_on_delivery',
            'payment_status' => 'unpaid',
            'grand_total' => 5,
            'code' => ${php(code)},
            'date' => now()->timestamp,
            'delivery_status' => ${php(status)},
        ])->save();
        $truck = App\Models\Truck::create(['name' => 'D10 order truck']);
        $trip = App\Models\Trip::create(['code' => 'D10-${id}', 'truck_id' => $truck->id, 'due_date' => now()->toDateString(), 'status' => 'started', 'active' => true]);
        $trip->checkpoints()->create(['relationable_type' => App\Models\Order::class, 'relationable_id' => $order->id, 'type' => 'delivery', 'status' => 'pending', 'sort_order_in_trip' => 1]);
    `);
}

/** Fails the spec unless `$order` (the order with `code`) satisfies `$check`. */
function expectStoredOrder(code: string, check: string): void {
    seedStoreFixtures(String.raw`
        $order = App\Models\Order::query()->where('code', ${php(code)})->first();
        if (!(${check})) {
            throw new RuntimeException('Unexpected stored order');
        }
    `);
}

test('the trip orders list saves an invoice number on change and confirms status changes natively', async ({
    page,
}) => {
    const stamp = Date.now();
    const code = `E2E-ORD-${stamp}`;
    const other = `E2E-ORD2-${stamp}`;
    seedOrder(700000 + (stamp % 100000), code, 'confirmed');
    seedOrder(800000 + (stamp % 100000), other, 'confirmed');
    await loginAsStoreAdmin(page);
    await page.goto(`/temp/admin/trips/orders?search=E2E-ORD`);
    await expect(
        page.getByRole('heading', { name: 'Trip orders', exact: true }),
    ).toBeVisible();
    const row = page.getByRole('row', { name: new RegExp(code) });
    await expect(row).toContainText('Confirmed');

    const invoice = row.getByRole('textbox', {
        name: `Invoice number for order ${code}`,
    });
    await invoice.fill('INV-E2E-1');
    await invoice.blur();
    await expect(row.getByRole('status')).toHaveText('Invoice number saved.');
    await expect(row).toContainText('Out for delivery');
    await expect(page).toHaveURL(
        /\/temp\/admin\/trips\/orders\?search=E2E-ORD$/,
    );
    expectStoredOrder(
        code,
        "$order?->invoice_number === 'INV-E2E-1' && $order->delivery_status === 'on_delivery'",
    );

    page.once('dialog', (confirm) => {
        expect(confirm.message()).toBe(`Mark order ${code} as Delivered?`);
        void confirm.dismiss();
    });
    await row.getByRole('button', { name: 'Mark as delivered' }).click();
    await expect(row).toContainText('Out for delivery');
    expectStoredOrder(code, "$order?->delivery_status === 'on_delivery'");

    page.once('dialog', (confirm) => confirm.accept());
    await row.getByRole('button', { name: 'Mark as delivered' }).click();
    await expect(row).toContainText('Delivered');
    await expect(
        row.getByRole('button', { name: 'Mark as delivered' }),
    ).toHaveCount(0);
    await expect(page).toHaveURL(
        /\/temp\/admin\/trips\/orders\?search=E2E-ORD$/,
    );
    expectStoredOrder(code, "$order?->delivery_status === 'delivered'");

    const otherRow = page.getByRole('row', { name: new RegExp(other) });
    await otherRow
        .getByRole('checkbox', { name: `Select order ${other}` })
        .check();
    page.once('dialog', (confirm) => {
        expect(confirm.message()).toBe(
            'Mark 1 selected orders as Ready for delivery?',
        );
        void confirm.accept();
    });
    await page
        .getByRole('button', {
            name: 'Mark selection as Ready for delivery',
        })
        .click();
    await expect(otherRow).toContainText('Ready for delivery');
    await expect(
        otherRow.getByRole('checkbox', { name: `Select order ${other}` }),
    ).not.toBeChecked();
    expectStoredOrder(
        other,
        "$order?->delivery_status === 'ready_for_delivery'",
    );
    expectStoredOrder(code, "$order?->delivery_status === 'delivered'");
});

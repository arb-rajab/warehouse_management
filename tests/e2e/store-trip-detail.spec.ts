import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { php } from './support/editor';
import { seedStoreFixtures } from './support/storeFixtures';

test('the trip detail confirms a status natively, saves notes on change, prints without the sidebar and reports a missing CMR file', async ({
    page,
}) => {
    const code = `E2E-DETAIL-${Date.now()}`;
    const customer = `Detail customer ${code}`;
    seedStoreFixtures(String.raw`
        $truck = App\Models\Truck::create(['name' => ${php(`Detail truck ${code}`)}, 'is_available' => false]);
        $trip = App\Models\Trip::create(['code' => ${php(code)}, 'truck_id' => $truck->id, 'due_date' => now()->toDateString(), 'status' => 'pending', 'active' => true]);
        $customer = new App\Models\StoreUser;
        $customer->forceFill(['name' => ${php(customer)}, 'email' => ${php(`${code}@example.com`)}, 'phone' => '0612345671', 'password' => 'unused-password', 'user_type' => 'customer'])->save();
        $trip->checkpoints()->create(['relationable_type' => App\Models\StoreUser::class, 'relationable_id' => $customer->id, 'type' => 'other', 'status' => 'pending', 'sort_order_in_trip' => 1, 'cmr_file' => '999999999']);
    `);
    const expectStoredStop = (check: string): void =>
        seedStoreFixtures(String.raw`
            $checkpoint = App\Models\Trip::query()->where('code', ${php(code)})->first()?->checkpoints()->first();
            if (!(${check})) {
                throw new RuntimeException('Unexpected stored checkpoint');
            }
        `);
    await loginAsStoreAdmin(page);
    await page.goto(`/temp/admin/trips/index?search=${code}`);
    await page.getByRole('link', { name: code, exact: true }).click();
    await expect(
        page.getByRole('heading', { name: `Trip ${code}`, exact: true }),
    ).toBeVisible();
    await expect(page).toHaveURL(/\/temp\/admin\/trips\/show\/\d+$/);

    const row = page.getByRole('row', { name: new RegExp(customer) });
    const status = row.getByRole('combobox', { name: 'Status of stop 1' });
    page.once('dialog', (dialog) => {
        expect(dialog.type()).toBe('confirm');
        expect(dialog.message()).toBe('Mark stop 1 as Delivered?');
        void dialog.dismiss();
    });
    await status.selectOption('delivered');
    await expect(status).toHaveValue('pending');
    expectStoredStop(
        "$checkpoint?->status === 'pending' && $checkpoint->arrived_at === null",
    );

    page.once('dialog', (dialog) => void dialog.accept());
    const saved = page.waitForResponse(
        (response) =>
            response.request().method() === 'PUT' &&
            /\/checkpoints\/\d+\/status/.test(response.url()),
    );
    await status.selectOption('delivered');
    await saved;
    await expect(status).toHaveValue('delivered');
    expectStoredStop(
        "$checkpoint?->status === 'delivered' && $checkpoint->arrived_at !== null",
    );

    const notes = row.getByRole('textbox', { name: 'Notes for stop 1' });
    await notes.fill('Leave at the back door');
    await notes.blur();
    await expect(row.getByRole('status')).toHaveText('Notes saved.');
    expectStoredStop("$checkpoint?->notes === 'Leave at the back door'");

    await page.emulateMedia({ media: 'print' });
    await expect(page.locator('aside')).toBeHidden();
    await expect(page.getByRole('button', { name: 'Print' })).toBeHidden();
    await expect(row).toBeVisible();
    await page.emulateMedia({ media: 'screen' });
    await expect(page.locator('aside')).toBeVisible();

    await row.getByRole('link', { name: 'Print CMR' }).click();
    await expect(page.getByRole('alert')).toHaveText(
        'This checkpoint has no CMR file to open.',
    );
    await expect(page).toHaveURL(/\/temp\/admin\/trips\/show\/\d+$/);
});

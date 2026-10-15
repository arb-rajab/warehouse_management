import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';
import { php } from './support/editor';
import { seedStoreFixtures } from './support/storeFixtures';

/** A pending trip due today on its own busy truck. */
function seedTrip(code: string, truck: string): void {
    seedStoreFixtures(String.raw`
        $truck = App\Models\Truck::create(['name' => ${php(truck)}, 'is_available' => false]);
        App\Models\Trip::create(['code' => ${php(code)}, 'truck_id' => $truck->id, 'due_date' => now()->toDateString(), 'status' => 'pending', 'active' => true]);
    `);
}

/** Fails the spec unless `$trip` (the trip with `code`, or null) satisfies `$check`. */
function expectStoredTrip(code: string, check: string): void {
    seedStoreFixtures(String.raw`
        $trip = App\Models\Trip::query()->where('code', ${php(code)})->first();
        if (!(${check})) {
            throw new RuntimeException('Unexpected stored trip');
        }
    `);
}

test('the trip list confirms deletion natively, changes status in its dialog and saves notes on change', async ({
    page,
}) => {
    const code = `E2E-${Date.now()}`;
    seedTrip(code, `Trip truck ${code}`);
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/trips/today-trips');
    await expect(
        page.getByRole('heading', { name: "Today's trips", exact: true }),
    ).toBeVisible();
    const row = page.getByRole('row', { name: new RegExp(code) });
    await expect(row).toBeVisible();

    await row
        .getByRole('button', {
            name: `Update the status of trip ${code}`,
            exact: true,
        })
        .click();
    const dialog = page.getByRole('dialog', {
        name: `Update the status of trip ${code}`,
    });
    await expect(dialog.locator('#trip-status')).toHaveValue('pending');
    await dialog.locator('#trip-status').selectOption('started');
    await dialog.getByRole('button', { name: 'Update status' }).click();
    await expect(dialog).toHaveCount(0);
    await expect(row).toContainText('Started');
    await expect(page).toHaveURL(/\/temp\/admin\/trips\/today-trips$/);
    expectStoredTrip(
        code,
        "$trip?->status === 'started' && $trip->started_at !== null",
    );

    const notes = row.getByRole('textbox', { name: `Notes for trip ${code}` });
    await notes.fill('Gate code 1234');
    await notes.blur();
    await expect(row.getByRole('status')).toHaveText('Notes saved.');
    expectStoredTrip(code, "$trip?->notes === 'Gate code 1234'");

    page.once('dialog', (confirm) => confirm.dismiss());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(row).toBeVisible();
    expectStoredTrip(code, '$trip !== null');

    page.once('dialog', (confirm) => confirm.accept());
    await row.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(page.getByRole('row', { name: new RegExp(code) })).toHaveCount(
        0,
    );
    await expect(page).toHaveURL(/\/temp\/admin\/trips\/today-trips$/);
    expectStoredTrip(code, '$trip === null');
});

test("the Blade map picker deletes a truck's open trip through the spoofed DELETE and returns to the picker", async ({
    page,
}) => {
    const code = `E2E-MAP-${Date.now()}`;
    const truck = `Map truck ${code}`;
    seedTrip(code, truck);
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/trips/create-from-map');
    const card = page.locator('.truck-card', { hasText: truck });
    await expect(card).toBeVisible();

    await card.locator('.confirm-delete').click();
    const modal = page.locator('#delete-modal');
    await expect(modal).toBeVisible();
    await expect(modal.locator('#delete-link')).toHaveAttribute(
        'data-method',
        'delete',
    );
    await modal.locator('#delete-link').click();

    await expect(page).toHaveURL(/\/temp\/admin\/trips\/create-from-map$/);
    await expect(page.locator('.truck-card', { hasText: truck })).toBeVisible();
    expectStoredTrip(code, '$trip === null');
    seedStoreFixtures(String.raw`
        if (!App\Models\Truck::query()->where('name', ${php(truck)})->value('is_available')) {
            throw new RuntimeException('The truck was not freed');
        }
    `);
});

test('the trip form loads free vehicles for its date, needs a checkpoint natively and lands on the Blade checkpoints page', async ({
    page,
}) => {
    const stamp = Date.now();
    const truck = `Form truck ${stamp}`;
    const customer = `Form customer ${stamp}`;
    seedStoreFixtures(String.raw`
        if (App\Models\StoreUser::query()->whereKey(9)->doesntExist()) {
            (new App\Models\StoreUser)->forceFill(['id' => 9, 'name' => 'E2E departure', 'email' => 'e2e-departure@example.com', 'phone' => '0600000009', 'password' => 'unused-password', 'user_type' => 'staff'])->save();
        }
        App\Models\Truck::create(['name' => ${php(truck)}, 'model' => 'E2E', 'is_available' => true]);
        (new App\Models\StoreUser)->forceFill(['name' => ${php(customer)}, 'email' => ${php(`trip-form-${stamp}@example.com`)}, 'phone' => '0612345670', 'password' => 'unused-password', 'user_type' => 'customer'])->save();
    `);
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/trips/create');
    await expect(
        page.getByRole('heading', { name: 'Create trip', exact: true }),
    ).toBeVisible();

    await page.locator('#due_date').fill('2031-01-15');
    await expect(
        page.locator('#truck_id option', { hasText: truck }),
    ).toHaveCount(1);
    await page.locator('#truck_id').selectOption({ label: `${truck} E2E` });

    await page
        .getByRole('button', { name: 'Create trip', exact: true })
        .click();
    await expect(page).toHaveURL(/\/temp\/admin\/trips\/create$/);
    expect(
        await page
            .locator('input.sr-only[required]')
            .evaluate((input: HTMLInputElement) => input.validationMessage),
    ).toBe('Add at least one checkpoint.');

    await page
        .getByRole('button', { name: 'Add customer checkpoint', exact: true })
        .click();
    await page
        .getByLabel('Customer', { exact: true })
        .selectOption({ label: customer });
    await page
        .getByLabel('Checkpoint notes', { exact: true })
        .fill('Ring twice');
    await page
        .getByRole('button', { name: 'Create trip', exact: true })
        .click();

    await expect(page).toHaveURL(/\/temp\/admin\/trips\/show\/\d+$/);
    await expect(page.locator('#app')).toHaveCount(0);
    const tripId = page.url().match(/\/show\/(\d+)$/)?.[1];
    seedStoreFixtures(String.raw`
        $trip = App\Models\Trip::query()->with('checkpoints')->find(${php(String(tripId))});
        $truck = App\Models\Truck::query()->where('name', ${php(truck)})->first();
        $customer = App\Models\StoreUser::query()->where('name', ${php(customer)})->value('id');
        if ($trip === null || $trip->truck_id !== $truck->id || $truck->is_available || $trip->due_date->toDateString() !== '2031-01-15'
            || $trip->checkpoints->pluck('relationable_id')->sort()->values()->all() !== collect([9, $customer])->sort()->values()->all()
            || $trip->checkpoints->firstWhere('relationable_id', $customer)?->notes !== 'Ring twice') {
            throw new RuntimeException('Unexpected stored trip');
        }
    `);

    await page.goto(`/temp/admin/trips/${tripId}/edit`);
    await expect(page.locator('#truck_id')).toHaveValue(/\d+/);
    await expect(page.locator('#truck_id option:checked')).toHaveText(
        `${truck} E2E`,
    );
    await expect(page.getByTestId('trip-checkpoint')).toHaveCount(2);
    await page
        .getByRole('button', { name: 'Move stop 2 up', exact: true })
        .click();
    await page.locator('#notes').fill('Edited in the browser');
    await page.getByRole('button', { name: 'Save trip', exact: true }).click();
    await expect(page).toHaveURL(
        new RegExp(`/temp/admin/trips/show/${tripId}$`),
    );
    seedStoreFixtures(String.raw`
        $trip = App\Models\Trip::query()->find(${php(String(tripId))});
        if ($trip?->notes !== 'Edited in the browser' || $trip->checkpoints()->count() !== 2) {
            throw new RuntimeException('Unexpected updated trip');
        }
    `);
});

import { expect, test } from '@playwright/test';
import { php } from './support/editor';
import { seedStoreFixtures } from './support/storeFixtures';

/**
 * The storefront's "New Address" form in a real browser
 * (.ai/archive/store-backoffice/changes-20261021.md §45): a customer sees the legend and the
 * marks, the browser holds back an empty required field, and a complete
 * address is saved and listed on the profile.
 */
const email = 'address-browser@example.com';
const street = `Damrak ${Date.now() % 100000}`;

test.beforeAll(() => {
    seedStoreFixtures(String.raw`
        Illuminate\Support\Facades\DB::table('countries')->updateOrInsert(['code' => 'NL'], ['name' => 'Netherlands', 'status' => 1]);
        $user = App\Models\StoreUser::query()->firstOrNew(['email' => ${php(email)}]);
        $user->forceFill(['name' => 'Address Browser', 'phone' => '0612345678', 'user_type' => 'customer', 'email_verified_at' => now(), 'banned' => 0]);
        $user->password = 'Password123!';
        $user->save();
        App\Models\Address::query()->where('user_id', $user->id)->delete();
    `);
});

test('a customer saves a new address from the profile', async ({ page }) => {
    await page.goto('/temp/login');
    // The storefront's header carries a second, hidden login form.
    const login = page
        .locator('form')
        .filter({ has: page.getByRole('button', { name: 'Login' }) });
    await login.locator('#email').fill(email);
    await login.locator('#password').fill('Password123!');
    await login.getByRole('button', { name: 'Login' }).click();
    await page.waitForURL(/\/temp\/dashboard/);

    await page.goto('/temp/profile');
    await page.getByText('Add New Address').click();
    const modal = page.locator('#new-address-modal');
    await expect(modal).toBeVisible();
    await expect(modal.locator('[data-required-legend]')).toHaveText(
        '* Required fields',
    );
    await expect(modal.locator('#new_address_address')).toHaveAccessibleName(
        'Address required',
    );
    await expect(
        modal.locator('#new_address_postal_code'),
    ).toHaveAccessibleName('Postal Code required');

    await modal.locator('#new_address_address').fill(street);
    await modal.locator('#new_address_phone').fill('+31612345678');
    await modal.locator('button[data-id="new_address_country"]').click();
    await modal
        .locator('.dropdown-menu.show .dropdown-item', {
            hasText: 'Netherlands',
        })
        .click();

    // The postal code is still empty: the browser keeps the form.
    await modal.getByRole('button', { name: 'Save' }).click();
    await expect(modal).toBeVisible();
    expect(
        await modal
            .locator('#new_address_postal_code')
            .evaluate((input: HTMLInputElement) => input.validity.valueMissing),
    ).toBe(true);

    await modal.locator('#new_address_postal_code').fill('1012 LG');
    await modal.getByRole('button', { name: 'Save' }).click();

    await expect(page).toHaveURL(/\/temp\/profile/);
    await expect(page.getByText(street)).toBeVisible();
    await expect(page.getByText('1012 LG').first()).toBeVisible();
});

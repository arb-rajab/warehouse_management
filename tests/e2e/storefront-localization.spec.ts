import { expect, test } from '@playwright/test';
import { seedStoreFixtures } from './support/storeFixtures';

test.beforeAll(() => {
    seedStoreFixtures(String.raw`
        // Earlier header-editor specs leave database-owned menu content behind.
        // Isolate static UI rendering from that deliberately unmigrated content.
        Illuminate\Support\Facades\DB::table('business_settings')->where('type', 'header_menu_labels')->delete();
        Illuminate\Support\Facades\DB::table('translations')->delete();
        $language = App\Models\Language::query()->where('code', 'sa')->first() ?? new App\Models\Language;
        $language->forceFill(['code' => 'sa', 'name' => 'العربية', 'app_lang_code' => 'ar', 'rtl' => 1, 'status' => 1])->save();
    `);
});

for (const path of ['/temp/login', '/temp/users/login']) {
    test(`Arabic login labels and JavaScript work without database translations at ${path}`, async ({
        page,
    }) => {
        const errors: string[] = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.goto(path);
        await page.evaluate(async () => {
            const token = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content');
            const response = await fetch('/temp/language', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token ?? '',
                },
                body: JSON.stringify({ locale: 'sa' }),
            });

            if (!response.ok) {
                throw new Error('Language selection failed');
            }
        });
        await page.goto(path);
        await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(
            page.getByRole('heading', { name: 'مرحبًا بعودتك!' }),
        ).toBeVisible();
        const login = page.locator('form').filter({
            has: page.getByRole('button', {
                name: 'تسجيل الدخول',
                exact: true,
            }),
        });
        await expect(login.locator('label[for="email"]')).toHaveText(
            'البريد الإلكتروني',
        );
        await expect(login.locator('label[for="password"]')).toHaveText(
            'كلمة المرور',
        );
        await login.locator('#email').fill('missing@example.com');
        await login.locator('#password').fill('incorrect-password');
        await login
            .getByRole('button', { name: 'تسجيل الدخول', exact: true })
            .click();
        await expect(page.locator('.alert')).toContainText(
            'بيانات الدخول غير صحيحة',
        );
        await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
        expect(errors).toEqual([]);
        seedStoreFixtures(String.raw`
            if (Illuminate\Support\Facades\DB::table('translations')->exists()) {
                throw new RuntimeException('Static storefront text created translation records');
            }
        `);
    });
}

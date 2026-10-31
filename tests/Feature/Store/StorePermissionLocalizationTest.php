<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\StoreLocale;
use App\Support\StorePermissions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['app.locale' => 'ar', 'app.fallback_locale' => 'en', 'app.default_language' => 'en']);
    $this->withoutVite();
    Mail::fake();
});

test('the Arabic role form uses file labels for every application permission without translation queries', function () {
    actingAsStoreUser(createStoreAdmin());
    expect(DB::table('translations')->count())->toBe(0);
    startStorefrontTranslationQueryLog();
    $this->get(route('roles.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Store/Admin/Staff/Roles/Create')
        ->where('permissionGroups', function (Collection $groups): bool {
            $listed = collect($groups)->flatMap(fn (array $group) => $group['permissions'])->keyBy('id');
            foreach (StorePermissions::all() as $name => $section) {
                $permission = Permission::findByName($name, 'store');
                expect($listed[$permission->id]['label'])->toBe(__('store_permissions.permissions.'.$name));
                expect(collect($groups)->firstWhere('section', $section)['label'])->toBe(__('store_permissions.sections.'.$section));
            }

            return true;
        }));
    expectNoStorefrontTranslationQueries();
    expect(DB::table('translations')->count())->toBe(0);
});

test('application permission catalog labels match the canonical identifiers and ignore stored overrides', function () {
    $canonical = StorePermissions::all();
    foreach (['en', 'ar'] as $locale) {
        $catalog = require lang_path($locale.'/store_permissions.php');
        expect(array_keys($catalog['permissions']))->toBe(array_keys($canonical));
        expect(array_keys($catalog['sections']))->toBe(array_values(array_unique($canonical)));
    }
    DB::table('translations')->insert([
        ['lang' => 'en', 'lang_key' => 'add_brand', 'lang_value' => 'DB brand'],
        ['lang' => 'ar', 'lang_key' => 'add_brand', 'lang_value' => 'DB Arabic brand'],
        ['lang' => 'ar', 'lang_key' => 'product', 'lang_value' => 'DB product section'],
    ]);
    $before = DB::table('translations')->get()->toJson();
    app('translator')->setLocale('ar');
    startStorefrontTranslationQueryLog();
    expect(StorePermissions::displayName('add_brand'))->toBe('إضافة علامة تجارية')
        ->and(StorePermissions::displaySection('product'))->toBe('المنتجات');
    app('translator')->setLocale('en');
    expect(StorePermissions::displayName('add_brand'))->toBe('Add Brand');
    expectNoStorefrontTranslationQueries();
    expect(DB::table('translations')->get()->toJson())->toBe($before);
});

test('custom permission sections labels assignments and role content remain database owned', function () {
    DB::table('languages')->insert(['name' => 'Arabic', 'code' => 'sa', 'app_lang_code' => 'ar', 'rtl' => 1, 'status' => 1]);
    $custom = Permission::findOrCreate('approve_custom_shipment', 'store');
    $custom->forceFill(['section' => 'custom_logistics'])->save();
    $role = Role::findOrCreate('Custom dispatcher', 'store');
    $role->givePermissionTo($custom);
    DB::table('role_translations')->insert(['role_id' => $role->id, 'lang' => 'sa', 'name' => 'منسق مخصص']);
    DB::table('translations')->insert([
        ['lang' => 'en', 'lang_key' => 'approve_custom_shipment', 'lang_value' => 'Approve Custom Shipment'],
        ['lang' => 'sa', 'lang_key' => 'approve_custom_shipment', 'lang_value' => 'اعتماد الشحنة المخصصة'],
        ['lang' => 'en', 'lang_key' => 'custom_logistics', 'lang_value' => 'Custom Logistics'],
        ['lang' => 'sa', 'lang_key' => 'custom_logistics', 'lang_value' => 'لوجستيات مخصصة'],
    ]);
    actingAsStoreUser(createStoreAdmin());
    $this->withSession(['locale' => 'sa'])->get(route('roles.create'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('locale', 'ar')
        ->where('permissionGroups', fn (Collection $groups) => collect($groups)->contains(fn (array $group) => $group === [
            'section' => 'custom_logistics', 'label' => 'لوجستيات مخصصة',
            'permissions' => [['id' => $custom->id, 'label' => 'اعتماد الشحنة المخصصة']],
        ])));
    $this->get(route('roles.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('roles.data.0', ['id' => $role->id, 'name' => 'منسق مخصص']));
    expect($role->fresh()->hasPermissionTo('approve_custom_shipment'))->toBeTrue()
        ->and($custom->fresh()->name)->toBe('approve_custom_shipment')->and($custom->fresh()->section)->toBe('custom_logistics');
});

test('known payment methods use files and custom payment names keep content translations', function () {
    app()->setLocale('ar');
    startStorefrontTranslationQueryLog();
    expect(StoreLocale::paymentMethod('cash_on_delivery'))->toBe('الدفع عند الاستلام')
        ->and(StoreLocale::paymentMethod('cash'))->toBe('نقدًا');
    expectNoStorefrontTranslationQueries();
    DB::table('translations')->insert([
        ['lang' => 'en', 'lang_key' => 'custom_transfer', 'lang_value' => 'Custom transfer'],
        ['lang' => 'ar', 'lang_key' => 'custom_transfer', 'lang_value' => 'تحويل مخصص'],
    ]);
    expect(StoreLocale::paymentMethod('custom_transfer'))->toBe('تحويل مخصص');
});

test('the admin SMTP refusal is Arabic with an empty translations table and retains its validation key', function () {
    $customer = createStoreUser();
    actingAsStoreUser(createStoreStaff('send_newsletter'));
    config(['mail.mailers.smtp.username' => null]);
    startStorefrontTranslationQueryLog();
    $this->postJson(route('newsletters.send'), ['subject' => 'Custom subject', 'content' => 'Custom body', 'user_emails' => [$customer->email]])
        ->assertUnprocessable()->assertJsonPath('errors.smtp', ['يرجى إعداد SMTP أولًا']);
    expectNoStorefrontTranslationQueries();
    expect(DB::table('translations')->count())->toBe(0);
    Mail::assertNothingQueued();
});

<?php

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Store\Admin\CurrencyController;
use App\Http\Controllers\Store\Admin\DigitalProductController;
use App\Http\Controllers\Store\Admin\ProductController;
use App\Http\Controllers\Store\Admin\StaffController;
use App\Support\RequiredFields\BladeForms;
use App\Support\RequiredFields\RequiredFieldsAudit;
use App\Support\RequiredFields\ServerRules;
use Illuminate\Support\Facades\File;

/**
 * `php artisan forms:audit-required` (App\Support\RequiredFields): the Blade
 * scanner, the server rule extraction and the A-F classification, on fixtures
 * and on real endpoints whose rules are known. The ratchet over the store back
 * office is RequiredFieldsRatchetTest.
 */
function auditControl(string $name, bool|string $required, bool|string $mark, string $type = 'text'): array
{
    return ['name' => $name, 'name_resolved' => true, 'type' => $type, 'required' => $required, 'mark' => $mark, 'label' => ucfirst($name), 'disabled' => false, 'conditional' => false, 'file' => 'resources/js/components/Fixture.vue', 'line' => 1];
}

function auditVueForm(array $target, array $controls, string $action = 'store()'): array
{
    return ['file' => 'resources/js/pages/Fixture.vue', 'line' => 3, 'module' => 'Fixture', 'tag' => 'ResourceFormPage', 'action' => $action, 'target' => $target, 'controls' => $controls];
}

/**
 * @return array<string, string> field => class, for one form's findings
 */
function auditClasses(array $result): array
{
    $classes = [];
    foreach ($result['findings'] as $finding) {
        $classes[$finding['field']] = trim(($classes[$finding['field']] ?? '').$finding['class']);
    }
    ksort($classes);

    return $classes;
}

test('the Blade scanner reads a form\'s route, verb, controls, required attributes and marks', function () {
    $source = <<<'BLADE'
        <form action="{{ route('addresses.store') }}" method="POST">
            @csrf
            @method('PUT')
            {{-- <input name="commented" required> --}}
            <label>Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control{{ $errors->has('name') ? ' is-invalid' : '' }}" name="name" required>
            <label for="city">{{ translate('City') }}<x-required-mark /></label>
            <input id="city" name="city">
            <label>Notes <x-optional-mark /></label>
            <textarea name="notes" @if($strict) required @endif></textarea>
            <select name="country_id" required></select>
            <input type="hidden" name="shop_id" value="{{ $shop->id }}">
            <script>var row = '<input name="from_script" required>';</script>
            <button type="submit">Save</button>
        </form>
        BLADE;

    [$form] = (new BladeForms(base_path()))->scanSource($source, 'resources/views/shop/frontend/fixture.blade.php');

    expect($form['target'])->toBe(['route' => 'addresses.store', 'verb' => 'PUT'])
        ->and($form['module'])->toBe('storefront')
        ->and(array_map(fn (array $control) => [$control['name'], $control['type'], $control['required'], $control['mark'], $control['label'], $control['line']], $form['controls']))->toBe([
            ['name', 'text', true, true, 'Name *', 6],
            ['city', 'text', false, true, 'City*', 8],
            ['notes', 'textarea', 'dynamic', false, 'Notes (optional)', 10],
            ['country_id', 'select', true, false, null, 11],
            ['shop_id', 'hidden', false, false, null, 12],
        ]);
});

test('the Blade scanner takes a fieldset\'s legend as the label of its radio and checkbox group', function () {
    $source = <<<'BLADE'
        <form action="{{ route('checkout.store_shipping_infostore') }}" method="POST">
            <fieldset>
                <legend>{{ translate('Shipping address') }}<x-required-mark /></legend>
                <label class="aiz-megabox"><input type="radio" name="address_id" value="1" required><span>Damrak 1</span></label>
                <label for="note">Note</label>
                <input id="note" name="note">
            </fieldset>
            <fieldset>
                <legend>Extras</legend>
                <label><input type="checkbox" name="gift"> Gift wrap</label>
            </fieldset>
        </form>
        BLADE;

    [$form] = (new BladeForms(base_path()))->scanSource($source, 'resources/views/shop/frontend/fixture.blade.php');

    expect(array_map(fn (array $control) => [$control['name'], $control['mark'], $control['label']], $form['controls']))->toBe([
        ['address_id', true, 'Shipping address*'],
        ['note', false, 'Note'],
        ['gift', false, 'Extras'],
    ]);
});

test('the Vue scanner resolves forms by their Wayfinder action and follows the field components', function () {
    $forms = collect((new RequiredFieldsAudit(new ServerRules(base_path()), base_path()))->vueForms());
    $staff = $forms->firstWhere('file', 'resources/js/pages/Store/Admin/Staff/Staffs/Create.vue');

    expect($staff['target'])->toBe(['controller' => 'App\\Http\\Controllers\\Store\\Admin\\StaffController', 'method' => 'store'])
        ->and(array_map(fn (array $control) => [$control['name'], $control['required'], $control['label'], $control['file']], $staff['controls']))->toBe([
            ['name', true, 'Name', 'resources/js/components/StoreStaffFormFields.vue'],
            ['email', true, 'Email', 'resources/js/components/StoreStaffFormFields.vue'],
            ['mobile', true, 'Phone', 'resources/js/components/StoreStaffFormFields.vue'],
            ['role_id', true, 'Role', 'resources/js/components/StoreStaffFormFields.vue'],
            ['password', true, 'Password', 'resources/js/components/StoreStaffFormFields.vue'],
            ['password_confirmation', true, 'Confirm password', 'resources/js/components/StoreStaffFormFields.vue'],
        ]);

    // Each control is reported where it is written: a FormField at its usage, a raw select at its tag.
    $source = file(base_path('resources/js/components/StoreStaffFormFields.vue'));
    expect(array_map(fn (array $control) => trim($source[$control['line'] - 1]), $staff['controls']))
        ->toBe(['<FormField', '<FormField', '<FormField', '<select', '<FormField', '<FormField']);

    // A ternary action is one form per branch, each with its branch's fields;
    // forms in ResourceFormPage's #after-form slot are forms of their own.
    expect($forms->where('file', 'resources/js/pages/Store/Admin/Customers/Customers/Create.vue')->map(fn (array $form) => $form['target']['method'].':'.count($form['controls']))->values()->all())->toBe(['repStore:5', 'store:14'])
        ->and($forms->where('file', 'resources/js/pages/Store/Admin/Customers/Customers/Edit.vue')->map(fn (array $form) => $form['target']['method'])->values()->all())
        ->toBe(['update', 'updateNotes', 'changeActivityStatus', 'sendAgreement', 'update', 'store']);
});

test('the server side reads an inline validator behind a helper method, with the confirmation it implies', function () {
    $form = (new ServerRules(base_path()))->forAction(RegisterController::class, 'register');

    expect($form['source'])->toBe(RegisterController::class.'@register (inline)')
        ->and($form['unparsed'])->toBeNull()
        ->and(array_map(fn (array $field) => $field['presence'], $form['fields']))->toBe([
            'name' => 'required',
            'company_name' => 'required',
            'password' => 'required',
            'email' => 'required',
            'country' => 'required',
            'phone' => 'required',
            'g-recaptcha-response' => 'optional',
            'password_confirmation' => 'required',
        ])
        ->and($form['fields']['password_confirmation']['derived'])->toBeTrue()
        ->and($form['fields']['name']['file'].':'.$form['fields']['name']['line'])->toBe('app/Http/Controllers/Auth/RegisterController.php:104');
});

test('the server side calls a FormRequest\'s rules() with its route model and tells required from optional', function () {
    $server = new ServerRules(base_path());
    $store = $server->forAction(StaffController::class, 'store');
    $update = $server->forAction(StaffController::class, 'update');

    expect(array_map(fn (array $field) => $field['presence'], $store['fields']))->toBe([
        'name' => 'required', 'email' => 'required', 'mobile' => 'required', 'role_id' => 'required', 'password' => 'required', 'password_confirmation' => 'required',
    ])
        ->and($update['source'])->toBe('App\Http\Requests\Store\Admin\UpdateStaffRequest')
        ->and($update['fields']['password']['presence'])->toBe('optional')
        ->and($update['fields']['password_confirmation']['presence'])->toBe('conditional')
        ->and($update['fields']['role_id']['file'].':'.$update['fields']['role_id']['line'])->toBe('app/Http/Requests/Concerns/ValidatesStoreStaff.php:31');
});

test('required_with and required_without rules are conditional', function () {
    $fields = (new ServerRules(base_path()))->forAction(CurrencyController::class, 'configure')['fields'];

    expect($fields['decimal_separator']['presence'])->toBe('conditional')
        ->and($fields['symbol_format']['presence'])->toBe('conditional');
});

test('each finding class is raised by exactly the mismatch it names', function () {
    $audit = new RequiredFieldsAudit(new ServerRules(base_path()), base_path());
    $staff = ['controller' => StaffController::class, 'method' => 'store'];

    $result = $audit->run([
        auditVueForm($staff, [
            auditControl('name', true, true),
            auditControl('email', false, false, 'email'),
            auditControl('mobile', true, false, 'tel'),
            auditControl('role_id', false, false, 'hidden'),
            auditControl('password', 'dynamic', 'dynamic', 'password'),
            auditControl('nickname', true, false),
            auditControl('notes', false, false),
        ]),
    ], [], withInventory: false);

    expect(auditClasses($result))->toBe([
        'email' => 'AB',
        'mobile' => 'B',
        'nickname' => 'C',
        'notes' => 'E',
        'password_confirmation' => 'A',
    ]);
    $missing = collect($result['findings'])->firstWhere('field', 'password_confirmation');
    expect($missing['detail'])->toContain('the form has no control for it')
        ->and($missing['server'])->toBe('app/Http/Requests/Concerns/ValidatesStoreStaff.php:37')
        ->and($missing['severity'])->toBe('high')
        ->and($missing['priority'])->toBe('P3')
        ->and($missing['fingerprint'])->toBe('A|resources/js/pages/Fixture.vue|StaffController@store|password_confirmation');
});

test('a conditional rule with a static mark is D, an optional rule with one is C, and a hidden fallback satisfies a required checkbox', function () {
    $audit = new RequiredFieldsAudit(new ServerRules(base_path()), base_path());

    $result = $audit->run([
        auditVueForm(['controller' => CurrencyController::class, 'method' => 'configure'], [
            auditControl('decimal_separator', true, true, 'select'),
            auditControl('no_of_decimals', 'dynamic', false, 'select'),
        ]),
        auditVueForm(['controller' => StaffController::class, 'method' => 'update'], [
            auditControl('name', true, true),
            auditControl('email', true, true),
            auditControl('mobile', true, true),
            auditControl('role_id', false, true, 'hidden'),
            auditControl('role_id', true, false, 'checkbox'),
            auditControl('password', true, true, 'password'),
        ], 'update(staff)'),
    ], [], withInventory: false);

    expect(collect($result['findings'])->map(fn (array $finding) => $finding['class'].' '.$finding['field'])->all())->toBe([
        'C password',
        'D decimal_separator',
    ]);
});

test('a required item rule binds each row input, but not a checkbox list or multi-select', function () {
    $audit = new RequiredFieldsAudit(new ServerRules(base_path()), base_path());
    $colors = [...auditControl('colors[]', false, false, 'select'), 'multiple' => true];

    $result = $audit->run([
        auditVueForm(['controller' => DigitalProductController::class, 'method' => 'store'], [
            auditControl('tax[]', true, false, 'number'),
            auditControl('tax_type[]', false, false, 'select'),
        ]),
        auditVueForm(['controller' => ProductController::class, 'method' => 'store'], [
            auditControl('colors_active', false, false, 'checkbox'),
            $colors,
        ]),
    ], [], withInventory: false);
    $classes = collect($result['findings'])
        ->filter(fn (array $finding) => in_array($finding['field'], ['tax[]', 'tax_type[]', 'colors[]', 'colors_active'], true))
        ->map(fn (array $finding) => $finding['class'].' '.$finding['field'])
        ->values()
        ->all();

    expect($classes)->toBe(['A tax_type[]', 'B tax[]', 'B tax_type[]']);
});

test('an unresolved action is F, and a Blade GET form with no action is a filter and raises nothing', function () {
    $audit = new RequiredFieldsAudit(new ServerRules(base_path()), base_path());
    $blade = new BladeForms(base_path());

    $result = $audit->run(
        [auditVueForm([], [auditControl('name', true, true)], 'action')],
        [
            ...$blade->scanSource('<form class="search"><input name="keyword" required></form>', 'resources/views/shop/frontend/search.blade.php'),
            ...$blade->scanSource('<form action="{{ $url }}" method="POST"><input name="code" required></form>', 'resources/views/shop/frontend/pay.blade.php'),
        ],
        withInventory: false,
    );

    expect(collect($result['findings'])->map(fn (array $finding) => [$finding['class'], $finding['form'], $finding['detail']])->all())->toBe([
        ['F', 'resources/js/pages/Fixture.vue', 'form action not resolved: action'],
        ['F', 'resources/views/shop/frontend/pay.blade.php', 'form action not resolved: {{ $url }}'],
    ]);
});

test('field names map to validation keys', function (string $name, string $key) {
    expect((new RequiredFieldsAudit(new ServerRules(base_path()), base_path()))->normalize($name))->toBe($key);
})->with([
    ['name', 'name'],
    ['permissions[]', 'permissions.*'],
    ['items[0][qty]', 'items.*.qty'],
    ['address[city]', 'address.city'],
]);

test('the command refuses to run in production and writes nothing', function () {
    $directory = storage_path('framework/testing/required-fields-audit-refused');
    File::deleteDirectory($directory);
    File::ensureDirectoryExists($directory);
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('forms:audit-required', ['--output' => 'storage/framework/testing/required-fields-audit-refused'])
        ->expectsOutputToContain('development tool')
        ->assertFailed();

    expect(File::files($directory))->toBe([]);
});

test('the command writes the markdown and json reports for the given date', function () {
    $directory = 'storage/framework/testing/required-fields-audit-run';
    File::deleteDirectory(base_path($directory));
    File::ensureDirectoryExists(base_path($directory));

    $this->artisan('forms:audit-required', ['--output' => $directory, '--date' => '20000101'])->assertSuccessful();

    $json = json_decode(File::get(base_path("{$directory}/required-fields-audit-20000101.json")), true);
    expect(File::get(base_path("{$directory}/required-fields-audit-20000101.md")))->toStartWith('# Required-fields audit (20000101)')
        ->and(array_keys($json['totals']['by_class']))->toBe(['A', 'B', 'C', 'D', 'E', 'F'])
        ->and($json['totals']['forms']['vue'])->toBeGreaterThan(100)
        ->and($json['totals']['forms']['blade'])->toBeGreaterThan(50)
        ->and($json['top'])->toHaveCount(20)
        ->and(collect($json['forms'])->firstWhere('file', 'resources/views/shop/auth/register.blade.php')['findings'])->toBe(['E' => 5]);

    File::deleteDirectory(base_path($directory));
});

test('literal hidden section values select server rules without leaking between forms', function () {
    $source = <<<'BLADE'
    <form action="{{ route('seller.shop.update') }}" method="POST">
        <input type="hidden" name="section" value="basic">
        <label for="name">Name<x-required-mark /></label><input id="name" name="name" required>
        <label for="address">Address<x-required-mark /></label><input id="address" name="address" required>
    </form>
    <form action="{{ route('seller.shop.update') }}" method="POST">
        <input type="hidden" name="section" value="social">
        <label for="facebook">Facebook</label><input id="facebook" name="facebook">
    </form>
    BLADE;
    $blade = new BladeForms(base_path());
    $forms = $blade->scanSource($source, 'resources/views/shop/seller/fixture.blade.php');
    $audit = RequiredFieldsAudit::make();
    expect($audit->run([], $forms, false)['findings'])->toBe([]);
    $forms[0]['controls'][1]['required'] = false;
    expect(auditClasses($audit->run([], $forms, false)))->toBe(['name' => 'A']);
});

test('the Blade scanner keeps conditional marks and requirements dynamic', function () {
    [$form] = (new BladeForms(base_path()))->scanSource(<<<'BLADE'
    <form><label for="cost">Cost@if($shipping)<x-required-mark />@endif</label>
    <input id="cost" name="cost" @required($shipping)></form>
    BLADE, 'resources/views/shop/seller/fixture.blade.php');
    expect($form['controls'][0]['required'])->toBe('dynamic')->and($form['controls'][0]['mark'])->toBe('dynamic');
});

test('the Blade scanner follows literal field partials and keeps their source location', function () {
    [$form] = (new BladeForms(base_path()))->scanSource(<<<'BLADE'
    <form action="{{ route('seller.coupon.store') }}" method="POST">
        <label for="type">Type<x-required-mark /></label><input id="type" name="type" required>
        @include('shop.seller.coupons.fields')
    </form>
    BLADE, 'resources/views/shop/seller/fixture.blade.php');
    $code = collect($form['controls'])->firstWhere('name', 'code');
    expect($code)->not->toBeNull()->and($code['file'])->toBe('resources/views/shop/seller/coupons/fields.blade.php');
    expect(RequiredFieldsAudit::make()->run([], [$form], false)['findings'])->toBe([]);
});

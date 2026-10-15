<?php

use App\Models\RepresentativePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use SimpleSoftwareIO\QrCode\Generator;

function adminRepresentativePayment(array $attributes = []): RepresentativePayment
{
    $id = (string) random_int(10000, 99999999);
    DB::table('representative_payments')->insert([
        'id' => $id, 'rep_id' => createStoreUser('customer')->id, 'amount' => '125.50', 'receipt_code' => 'R-'.$id,
        'status' => 'pending', 'date' => '2026-10-08 10:00:00', 'created_at' => now(), 'updated_at' => now(), ...$attributes,
    ]);

    return RepresentativePayment::findOrFail($attributes['id'] ?? $id);
}

test('the representative payment list renders', function () {
    actingAsStoreUser(createStoreStaff('view_representative_payments'));
    $this->get('/temp/admin/admin/representatives/payments/index')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Store/Admin/Representatives/Payments/Index'));
});

test('payments return a complete narrow payload including orphan customer fallback', function () {
    actingAsStoreUser(createStoreStaff('view_representative_payments'));
    $rep = createStoreUser('customer');
    $payment = adminRepresentativePayment(['rep_id' => $rep->id, 'customer_name' => 'External customer', 'otajer_id' => 42, 'invoice_code' => 123, 'notes' => '<script>alert(1)</script>']);
    $this->get('/temp/admin/admin/representatives/payments/index')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('filters', ['per_page' => 20, 'search' => '', 'accepted' => false, 'rejected' => false, 'pending' => false])
        ->has('payments.data.0', fn (Assert $item) => $item
            ->where('id', (string) $payment->id)->where('amount', '125.5')->where('receipt_code', $payment->receipt_code)
            ->where('invoice_code', 123)->where('notes', '<script>alert(1)</script>')->where('status', 'pending')
            ->where('date', '2026-10-08 10:00:00')->where('customer_name', 'External customer')->where('account_id', 42)
            ->has('representative', fn (Assert $user) => $user->where('id', $rep->id)->where('name', $rep->name))->where('accountant', null)));
});

test('payment lists paginate and validate page size and search', function () {
    actingAsStoreUser(createStoreStaff('view_representative_payments'));
    foreach (range(1, 22) as $i) {
        adminRepresentativePayment();
    }
    $this->get('/temp/admin/admin/representatives/payments/index')->assertOk()->assertInertia(fn (Assert $page) => $page->has('payments.data', 20)->where('payments.meta.total', 22));
    $this->get('/temp/admin/admin/representatives/payments/index?per_page=10')->assertOk()->assertInertia(fn (Assert $page) => $page->has('payments.data', 10));
    $this->getJson('/temp/admin/admin/representatives/payments/index?per_page=999')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    $this->getJson('/temp/admin/admin/representatives/payments/index?search='.str_repeat('a', 256))->assertUnprocessable()->assertJsonValidationErrors('search');
    $this->getJson('/temp/admin/admin/representatives/payments/index?pending=invalid')->assertUnprocessable()->assertJsonValidationErrors('pending');
});

test('status union never leaks rows outside the search and false flags do not filter', function () {
    actingAsStoreUser(createStoreStaff('view_representative_payments'));
    $accepted = adminRepresentativePayment(['receipt_code' => 'MATCH-A', 'status' => 'accepted']);
    $rejected = adminRepresentativePayment(['receipt_code' => 'MATCH-R', 'status' => 'rejected']);
    adminRepresentativePayment(['receipt_code' => 'OTHER', 'status' => 'rejected']);
    adminRepresentativePayment(['receipt_code' => 'MATCH-P', 'status' => 'pending']);
    $this->get('/temp/admin/admin/representatives/payments/index?search=MATCH&accepted=true&rejected=1&pending=false')->assertOk()->assertInertia(fn (Assert $page) => $page->has('payments.data', 2)->where('payments.meta.total', 2));
    $this->get('/temp/admin/admin/representatives/payments/index?pending=false')->assertOk()->assertInertia(fn (Assert $page) => $page->has('payments.data', 4));
});

foreach (['accept' => 'accepted', 'reject' => 'rejected'] as $action => $status) {
    test('payment '.$action.' records the store accountant without changing financial fields', function () use ($action, $status) {
        $staff = createStoreStaff('approve_representative_payment');
        actingAsStoreUser($staff);
        $payment = adminRepresentativePayment();
        $noise = adminRepresentativePayment();
        $this->post('/temp/admin/admin/representatives/payments/'.$action.'/'.$payment->id.'?page=2&pending=1')->assertRedirect(route('admin.rep.payments.index', ['page' => 2, 'pending' => 1]));
        expect($payment->fresh()->status)->toBe($status)->and($payment->fresh()->admin_id)->toBe($staff->id)
            ->and($payment->fresh()->amount)->toEqual(125.5)->and($noise->fresh()->status)->toBe('pending');
    });

    test('payment '.$action.' refuses a previously decided receipt', function (string $existing) use ($action) {
        actingAsStoreUser(createStoreStaff('approve_representative_payment'));
        $priorAdmin = createStoreUser('staff');
        $payment = adminRepresentativePayment(['status' => $existing, 'admin_id' => $priorAdmin->id]);
        $this->post('/temp/admin/admin/representatives/payments/'.$action.'/'.$payment->id)->assertConflict();
        expect($payment->fresh()->status)->toBe($existing)->and($payment->fresh()->admin_id)->toBe($priorAdmin->id)->and($payment->fresh()->amount)->toEqual(125.5);
    })->with(['accepted', 'rejected']);

    test('payment '.$action.' returns 404 for a missing receipt', function () use ($action) {
        actingAsStoreUser(createStoreStaff('approve_representative_payment'));
        $this->post('/temp/admin/admin/representatives/payments/'.$action.'/missing')->assertNotFound();
    });

    test('payment '.$action.' refuses unauthorized callers without writes', function (string $caller) use ($action) {
        $payment = adminRepresentativePayment();
        actingAsRefusedStoreCaller($caller);
        assertStoreCallerRefused($this->post('/temp/admin/admin/representatives/payments/'.$action.'/'.$payment->id), $caller);
        expect($payment->fresh()->status)->toBe('pending')->and($payment->fresh()->admin_id)->toBeNull()->and($payment->fresh()->amount)->toEqual(125.5);
    })->with('refused store admin callers');
}

test('read payment permission cannot approve reject or export receipts', function () {
    actingAsStoreUser(createStoreStaff('view_representative_payments'));
    $payment = adminRepresentativePayment();
    $this->post('/temp/admin/admin/representatives/payments/accept/'.$payment->id)->assertForbidden();
    $this->post('/temp/admin/admin/representatives/payments/reject/'.$payment->id)->assertForbidden();
    $this->get('/temp/admin/admin/representatives/payments/export/'.$payment->id)->assertForbidden();
    expect($payment->fresh()->status)->toBe('pending')->and($payment->fresh()->admin_id)->toBeNull();
});

test('payment export remains a server rendered PDF with a signed QR target', function () {
    actingAsStoreUser(createStoreStaff('export_representative_payment'));
    $payment = adminRepresentativePayment();
    $qr = Mockery::mock(Generator::class);
    app()->instance(Generator::class, $qr);
    $qr->shouldReceive('size')->once()->with(200)->andReturnSelf();
    $qr->shouldReceive('generate')->once()->withArgs(function (string $url) use ($payment) {
        return str_contains($url, '/payments/show/'.$payment->id) && URL::hasValidSignature(Request::create($url));
    })->andReturn('<svg></svg>');
    PDF::shouldReceive('loadView')->once()->withArgs(fn (string $view, array $data) => $view === 'shop.frontend.user.export-rep-payments' && (string) $data['payment']->id === (string) $payment->id && $data['qr'] === '<svg></svg>')->andReturnSelf();
    PDF::shouldReceive('download')->once()->with('payment-'.$payment->id.'.pdf')->andReturn(response('%PDF-test', 200, ['Content-Type' => 'application/pdf']));
    $this->get('/temp/admin/admin/representatives/payments/export/'.$payment->id)->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('export of a missing receipt returns 404', function () {
    actingAsStoreUser(createStoreStaff('export_representative_payment'));
    $this->get('/temp/admin/admin/representatives/payments/export/missing')->assertNotFound();
});

foreach (['index', 'export'] as $action) {
    test('payment '.$action.' refuses unauthorized callers', function (string $caller) use ($action) {
        $payment = adminRepresentativePayment();
        actingAsRefusedStoreCaller($caller);
        assertStoreCallerRefused($this->get('/temp/admin/admin/representatives/payments/'.$action.($action === 'export' ? '/'.$payment->id : '')), $caller);
    })->with('refused store admin callers');
}

test('alphanumeric receipt keys bind correctly for approval', function () {
    actingAsStoreUser(createStoreStaff('approve_representative_payment'));
    $payment = adminRepresentativePayment(['id' => 'receipt-001']);
    $this->post('/temp/admin/admin/representatives/payments/accept/receipt-001')->assertRedirect(route('admin.rep.payments.index'));
    expect($payment->fresh()->id)->toBe('receipt-001')->and($payment->fresh()->status)->toBe('accepted');
});

test('payment customer and accountant summaries expose exactly the loaded names and account code', function () {
    actingAsStoreUser(createStoreStaff('view_representative_payments'));
    $rep = createStoreUser('customer');
    $customer = createStoreUser('customer');
    $customer->forceFill(['name' => 'Customer Account', 'AccSysID' => 73])->save();
    $accountant = createStoreUser('staff');
    $payment = adminRepresentativePayment(['rep_id' => $rep->id, 'customer_id' => $customer->id, 'admin_id' => $accountant->id, 'customer_name' => 'Old name', 'status' => 'accepted']);
    $this->get('/temp/admin/admin/representatives/payments/index')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('payments.data.0', fn (Assert $item) => $item
            ->where('id', $payment->id)->where('amount', '125.5')->where('receipt_code', $payment->receipt_code)
            ->where('invoice_code', null)->where('notes', null)->where('status', 'accepted')
            ->where('date', '2026-10-08 10:00:00')->where('customer_name', 'Customer Account')->where('account_id', 73)
            ->has('representative', fn (Assert $user) => $user->where('id', $rep->id)->where('name', $rep->name))
            ->has('accountant', fn (Assert $user) => $user->where('id', $accountant->id)->where('name', $accountant->name))));
});

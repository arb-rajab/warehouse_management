<?php

use App\Models\Receipt;
use App\Models\ReceiptFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(ReceiptFile::DISK);
});

/**
 * A bill file record whose bytes exist on the (fake) private disk.
 */
function adminBillFile(Receipt $receipt, string $contents = 'bill bytes', bool $pdf = false): ReceiptFile
{
    $file = ReceiptFile::factory()->for($receipt)->when($pdf, fn ($factory) => $factory->pdf())->create();
    Storage::disk(ReceiptFile::DISK)->put($file->path, $contents);

    return $file;
}

test('an admin views a bill file inline with its type and name, never cached or sniffed', function () {
    actingAsAdmin();
    $receipt = Receipt::factory()->create();
    $file = adminBillFile($receipt, '%PDF-1.4 the bill', pdf: true);
    $file->update(['original_name' => 'delivery-note.pdf']);

    $response = $this->get("/admin/receipts/{$receipt->id}/files/{$file->id}");

    $response->assertOk();
    expect($response->streamedContent())->toBe('%PDF-1.4 the bill')
        ->and($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('inline;')->toContain('delivery-note.pdf')
        ->and($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

test('a photo is served with its image type', function () {
    actingAsAdmin();
    $receipt = Receipt::factory()->create();
    $file = adminBillFile($receipt, 'jpeg bytes');

    $this->get("/admin/receipts/{$receipt->id}/files/{$file->id}")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
});

test('the url the receipt page hands out is the route that serves the file', function () {
    actingAsAdmin();
    $receipt = Receipt::factory()->create();
    adminBillFile($receipt, 'the bytes');

    $url = $this->get("/admin/receipts/{$receipt->id}")->inertiaProps('receipt.files.0.url');

    expect($this->get($url)->assertOk()->streamedContent())->toBe('the bytes');
});

test('a file of a finalized receipt can still be viewed', function () {
    actingAsAdmin();
    $receipt = Receipt::factory()->finalized()->create();
    $file = adminBillFile($receipt, 'kept');

    expect($this->get("/admin/receipts/{$receipt->id}/files/{$file->id}")->assertOk()->streamedContent())->toBe('kept');
});

test('viewing a file through another receipt is a 404', function () {
    actingAsAdmin();
    $receipt = Receipt::factory()->create();
    $other = Receipt::factory()->create();
    $file = adminBillFile($other);

    $this->get("/admin/receipts/{$receipt->id}/files/{$file->id}")->assertNotFound();
    $this->get("/admin/receipts/{$other->id}/files/{$file->id}")->assertOk();
});

test('viewing a file whose bytes are gone from the disk is a 404', function () {
    actingAsAdmin();
    $receipt = Receipt::factory()->create();
    $file = ReceiptFile::factory()->for($receipt)->create();

    $this->get("/admin/receipts/{$receipt->id}/files/{$file->id}")->assertNotFound();
});

test('viewing a file or a receipt that does not exist is a 404', function () {
    actingAsAdmin();
    $receipt = Receipt::factory()->create();
    $file = adminBillFile($receipt);

    $this->get("/admin/receipts/{$receipt->id}/files/999999")->assertNotFound();
    $this->get("/admin/receipts/999999/files/{$file->id}")->assertNotFound();
});

test('an unauthenticated caller is redirected to login when viewing a bill file', function () {
    $receipt = Receipt::factory()->create();
    $file = adminBillFile($receipt);

    $this->get("/admin/receipts/{$receipt->id}/files/{$file->id}")->assertRedirect(route('login'));
});

test('a mobile app user cannot view a bill file through the admin route', function () {
    actingAsMobilePanelUser();
    $receipt = Receipt::factory()->create();
    $file = adminBillFile($receipt);

    $this->get("/admin/receipts/{$receipt->id}/files/{$file->id}")->assertForbidden();
});

test('the admin and the API downloads both serve the file through ReceiptFile::inlineResponse, with the same bytes and headers', function () {
    $receipt = Receipt::factory()->create();
    $file = adminBillFile($receipt, '%PDF-1.4 shared', pdf: true);
    $headers = ['Content-Type', 'Content-Disposition', 'X-Content-Type-Options'];

    actingAsAdmin();
    $admin = $this->get("/admin/receipts/{$receipt->id}/files/{$file->id}")->assertOk();
    $adminBody = $admin->streamedContent();

    actingAsAdminMobileUser();
    $api = $this->get("/api/v1/receipts/{$receipt->id}/files/{$file->id}")->assertOk();

    expect($api->streamedContent())->toBe($adminBody)->toBe('%PDF-1.4 shared')
        ->and($api->headers->get('Cache-Control'))->toBe($admin->headers->get('Cache-Control'));

    foreach ($headers as $header) {
        expect($api->headers->get($header))->toBe($admin->headers->get($header), $header);
    }
});

<?php

use App\Models\Receipt;
use App\Models\ReceiptFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('a receipt file belongs to its receipt', function () {
    $receipt = Receipt::factory()->create();
    $file = ReceiptFile::factory()->for($receipt)->create();
    Receipt::factory()->create(); // noise

    expect($file->receipt->id)->toBe($receipt->id);
});

test('the size is cast to an integer', function () {
    $file = ReceiptFile::factory()->create(['size' => 2048]);

    expect(ReceiptFile::query()->findOrFail($file->id)->size)->toBeInt()->toBe(2048);
});

test('bills are written to the private disk under their own directory', function () {
    expect(ReceiptFile::DISK)->toBe('local')
        ->and(config('filesystems.disks.'.ReceiptFile::DISK.'.root'))->toContain('private')
        ->and(ReceiptFile::DIRECTORY)->toBe('receipt-bills');
});

test('the accepted types are photos and PDFs', function () {
    expect(ReceiptFile::MIMES)->toBe(['jpg', 'jpeg', 'png', 'webp', 'pdf'])
        ->and(ReceiptFile::MAX_KILOBYTES)->toBe(10240)
        ->and(ReceiptFile::MAX_PER_RECEIPT)->toBe(10);
});

test('the factory builds a JPEG record by default and a PDF with the pdf state', function () {
    $photo = ReceiptFile::factory()->create();
    $pdf = ReceiptFile::factory()->pdf()->create();

    expect($photo->mime_type)->toBe('image/jpeg')
        ->and($photo->path)->toStartWith('receipt-bills/')->toEndWith('.jpg')
        ->and($pdf->mime_type)->toBe('application/pdf')
        ->and($pdf->path)->toEndWith('.pdf');
});

test('inlineResponse streams the stored bytes inline with its type and original name, never cached or sniffed', function () {
    Storage::fake(ReceiptFile::DISK);
    $file = ReceiptFile::factory()->pdf()->create(['original_name' => 'delivery-note.pdf']);
    Storage::disk(ReceiptFile::DISK)->put($file->path, '%PDF-1.4 bill');

    $response = $file->inlineResponse();

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    expect($body)->toBe('%PDF-1.4 bill')
        ->and($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('inline;')->toContain('delivery-note.pdf')
        ->and($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

test('inlineResponse answers 404 when the bytes are gone from the disk', function () {
    Storage::fake(ReceiptFile::DISK);
    $file = ReceiptFile::factory()->create();

    try {
        $file->inlineResponse();
        $this->fail('Expected a 404.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(404);
    }
});

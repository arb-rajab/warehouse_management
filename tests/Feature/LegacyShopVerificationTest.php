<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The shop-verification form's file fields stored whatever was sent, unvalidated,
 * on the private disk, and the admin's review page linked a public URL for them,
 * so no reviewer could open one. ShopVerificationStorage keeps a content-checked
 * document under a random name, and a download route for the staff who may view
 * sellers opens it.
 */
function sellerWithVerificationForm(): array
{
    $seller = createStoreUser('seller');
    $shopId = DB::table('shops')->insertGetId(['user_id' => $seller->id, 'name' => 'Shop', 'slug' => 'shop-1']);
    DB::table('business_settings')->insert([
        'type' => 'verification_form',
        'value' => json_encode([['type' => 'text', 'label' => 'Company'], ['type' => 'file', 'label' => 'Chamber of commerce extract']]),
    ]);

    return [$seller, $shopId];
}

function verificationPdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('extract.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
}

test('a seller attaches a verification document, which is stored privately under a random name', function () {
    Storage::fake('local');
    [$seller, $shopId] = sellerWithVerificationForm();
    actingAsStoreUser($seller);

    $this->post(route('seller.shop.verify.store'), ['element_0' => 'Acme BV', 'element_1' => verificationPdf()]);

    $info = json_decode(DB::table('shops')->where('id', $shopId)->value('verification_info'), true);

    expect($info[1]['value'])->toMatch('/^[A-Za-z0-9]{40}\.pdf$/');
    Storage::disk('local')->assertExists("uploads/verification_form/{$info[1]['value']}");
});

test('a verification document that is not a PDF, Word document or image is refused and the request is not recorded', function (string $filename, string $contents) {
    Storage::fake('local');
    [$seller, $shopId] = sellerWithVerificationForm();
    actingAsStoreUser($seller);

    $this->post(route('seller.shop.verify.store'), ['element_0' => 'Acme BV', 'element_1' => UploadedFile::fake()->createWithContent($filename, $contents)])
        ->assertSessionHasErrorsIn('sellerVerification', ['element_1']);

    expect(DB::table('shops')->where('id', $shopId)->value('verification_info'))->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'html called pdf' => ['extract.pdf', '<html><script>alert(1)</script></html>'],
    'php called png' => ['extract.png', '<?php system($_GET[1]); ?>'],
]);

<?php

use App\Models\DynamicPopup;
use App\Models\Offer;
use App\Models\OfferProduct;

/**
 * An offer whose base product the popup's button links to.
 *
 * @return array{offer: Offer, slug: string}
 */
function offerWithBaseProduct(): array
{
    $product = createStoreProduct();
    $offer = Offer::forceCreate(['title' => 'Spring offer']);
    OfferProduct::forceCreate(['offer_id' => $offer->id, 'product_id' => $product->id, 'is_base_product' => 1]);

    return ['offer' => $offer, 'slug' => $product->slug];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function popupForm(int $offerId, array $overrides = []): array
{
    return [
        'title' => 'Welcome',
        'summary' => 'Take a look',
        'banner' => '12',
        'delay_seconds' => 5,
        'btn_text' => 'Open',
        'offer_id' => $offerId,
        'show_page' => 1,
        ...$overrides,
    ];
}

test('storing a popup derives the button columns and ignores forged ones', function () {
    ['offer' => $offer, 'slug' => $slug] = offerWithBaseProduct();
    actingAsStoreUser(createStoreAdmin());

    $this->post('/temp/admin/dynamic-popups', popupForm($offer->id, [
        'status' => 1,
        'btn_link' => 'https://evil.example',
        'btn_background_color' => '#ff0000',
        'btn_text_color' => 'pink',
        'id' => 999,
    ]))->assertRedirect(route('dynamic-popups.index'));

    $popup = DynamicPopup::firstOrFail();
    expect($popup->id)->not->toBe(999)
        ->and($popup->status)->toBe(0)
        ->and($popup->btn_link)->toBe(route('product', ['slug' => $slug]))
        ->and($popup->btn_background_color)->toBe('#000000')
        ->and($popup->btn_text_color)->toBe('white')
        ->and($popup->title)->toBe('Welcome');
});

test('updating a popup cannot flip its status or button columns through the form', function () {
    ['offer' => $offer, 'slug' => $slug] = offerWithBaseProduct();
    actingAsStoreUser(createStoreAdmin());
    $popup = DynamicPopup::forceCreate(['title' => 'Old', 'status' => 0, 'btn_link' => 'x', 'show_page' => 1]);
    $noise = DynamicPopup::forceCreate(['title' => 'Noise', 'status' => 0, 'show_page' => 1]);

    $this->put("/temp/admin/dynamic-popups/{$popup->id}", popupForm($offer->id, ['title' => 'New', 'status' => 1, 'btn_link' => 'https://evil.example']))
        ->assertRedirect(route('dynamic-popups.index'));

    expect($popup->fresh()->title)->toBe('New')
        ->and($popup->fresh()->status)->toBe(0)
        ->and($popup->fresh()->btn_link)->toBe(route('product', ['slug' => $slug]))
        ->and($noise->fresh()->title)->toBe('Noise');
});

test('a popup for an offer that does not exist is rejected and nothing is stored', function () {
    actingAsStoreUser(createStoreAdmin());

    $this->post('/temp/admin/dynamic-popups', popupForm(9999))->assertSessionHasErrors('offer_id');

    expect(DynamicPopup::count())->toBe(0);
});

test('a customer cannot store or update popups', function () {
    ['offer' => $offer] = offerWithBaseProduct();
    $popup = DynamicPopup::forceCreate(['title' => 'Old', 'show_page' => 1]);
    actingAsStoreUser(createStoreUser('customer'));

    $this->post('/temp/admin/dynamic-popups', popupForm($offer->id))->assertNotFound();
    $this->put("/temp/admin/dynamic-popups/{$popup->id}", popupForm($offer->id, ['title' => 'Hijacked']))->assertNotFound();

    expect(DynamicPopup::count())->toBe(1)
        ->and($popup->fresh()->title)->toBe('Old');
});

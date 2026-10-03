<?php

namespace App\Http\Resources\V2;

use App\Models\Shop;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A shop as any visitor may read it: no payout balance, bank details, sales
 * figures, package, contact details or owner e-mail. The owner's own view of
 * the shop is ShopDetailsCollection.
 *
 * @mixin Shop
 */
class PublicShopDetailsCollection extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => intval($this->user_id),
            'name' => $this->name,
            'title' => $this->meta_title,
            'description' => $this->meta_description,
            'logo' => uploaded_asset($this->logo),
            'sliders' => get_images_path($this->sliders),
            'address' => $this->address,
            'facebook' => $this->facebook,
            'google' => $this->google,
            'twitter' => $this->twitter,
            'instagram' => $this->instagram,
            'youtube' => $this->youtube,
            'rating' => (float) $this->rating,
            'verified' => $this->verification_status == 1,
            'verified_img' => static_asset('assets/img/verified.png'),
            'verify_text' => translate('Verified seller'),
            'products' => $this->user->products()->where('published', 1)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function with($request)
    {
        return [
            'success' => true,
            'status' => 200,
        ];
    }
}

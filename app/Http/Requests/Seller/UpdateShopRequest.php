<?php

namespace App\Http\Requests\Seller;

use App\Http\Requests\Concerns\RejectsUnexpectedInput;
use App\Http\Requests\Concerns\ValidatesStoreUploadReference;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShopRequest extends FormRequest
{
    use RejectsUnexpectedInput;
    use ValidatesStoreUploadReference;

    protected $errorBag = 'sellerShop';

    /** Get the validation rules that apply to the request.
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $section = ['section' => ['sometimes', 'string', Rule::in(['basic', 'pickup', 'banners', 'social'])]];

        return $section + match ($this->section()) {
            'basic' => [
                'name' => ['required', 'string', 'max:200'],
                'address' => ['required', 'string', 'max:500'],
                'phone' => ['nullable', 'string', 'max:255'],
                'meta_title' => ['nullable', 'string', 'max:255'],
                'meta_description' => ['bail', 'nullable', 'string', 'max:65535', function (string $attribute, mixed $value, Closure $fail): void {
                    if (strlen($value) > 65535) {
                        $fail(translate('The meta description may not exceed 65,535 bytes.'));
                    }
                }],
                'logo' => ['nullable', 'integer', Rule::exists('uploads', 'id')->where('type', 'image')->where('user_id', $this->user('store')?->id)],
                'shipping_cost' => get_setting('shipping_type') === 'seller_wise_shipping' ? ['required', 'numeric', 'min:0', 'max:999999999999999999.99'] : ['missing'],
            ],
            'pickup' => [
                'delivery_pickup_latitude' => ['present', 'required_with:delivery_pickup_longitude', 'nullable', 'numeric', 'between:-90,90'],
                'delivery_pickup_longitude' => ['present', 'required_with:delivery_pickup_latitude', 'nullable', 'numeric', 'between:-180,180'],
            ],
            'social' => array_fill_keys(['facebook', 'instagram', 'google', 'twitter', 'youtube'], ['nullable', 'string', 'url:http,https', 'max:255']),
            'banners' => [
                'top_banner' => ['nullable', 'string', 'max:191', $this->retainableImageUploadIdsRule(null, ownOnly: true), 'regex:/^\d+$/'],
                'sliders' => ['nullable', 'string', $this->retainableImageUploadIdsRule(null, ownOnly: true)],
                'banner_full_width_1' => ['nullable', 'string', 'max:191', $this->retainableImageUploadIdsRule(null, ownOnly: true)],
                'banners_half_width' => ['nullable', 'string', 'max:191', $this->retainableImageUploadIdsRule(null, ownOnly: true)],
                'banner_full_width_2' => ['nullable', 'string', 'max:191', $this->retainableImageUploadIdsRule(null, ownOnly: true)],
            ],
            default => [],
        };
    }

    /** Existing clients can still submit the section's original controls. */
    private function section(): mixed
    {
        if ($this->has('section')) {
            return $this->input('section');
        }
        if ($this->hasAny(['name', 'address'])) {
            return 'basic';
        }
        if ($this->hasAny(['delivery_pickup_longitude', 'delivery_pickup_latitude'])) {
            return 'pickup';
        }
        if ($this->hasAny(['facebook', 'google', 'twitter', 'youtube', 'instagram'])) {
            return 'social';
        }

        return $this->hasAny(['top_banner', 'sliders', 'banner_full_width_1', 'banners_half_width', 'banner_full_width_2']) ? 'banners' : 'basic';
    }
}

<?php

namespace App\Models;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StoreUser;
use Auth;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Storage;

//class ProductsImport implements ToModel, WithHeadingRow, WithValidation
class ProductsImport implements ToCollection, WithHeadingRow, WithValidation, ToModel
{
    private $rows = 0;

    public function __construct(private bool $notify = true)
    {
    }

    public function collection(Collection $rows): void
    {
        $canImport = true;
        $user = Auth::user();
        if ($user->user_type == 'seller' && addon_is_activated('seller_subscription')) {
            if ((count($rows) + $user->products()->count()) > $user->shop->product_upload_limit
                || $user->shop->package_invalid_at == null
                || (int) Carbon::now()->diffInDays(Carbon::parse($user->shop->package_invalid_at), false) < 0
            ) {
                $canImport = false;
                flash(translate('Please upgrade your package.'))->warning();
            }
        }

        if ($canImport) {
            foreach ($rows as $row) {
                $approved = 1;
                if ($user->user_type == 'seller' && get_setting('product_approve_by_admin') == 1) {
                    $approved = 0;
                }

                $productId = Product::forceCreate([
                    'name' => $row['name'],
                    'description' => clean_html($row['description']),
                    'added_by' => $user->user_type == 'seller' ? 'seller' : 'admin',
                    'user_id' => $user->user_type == 'seller' ? $user->id : StoreUser::query()->where('user_type', 'admin')->value('id'),
                    'approved' => $approved,
                    'category_id' => $row['category_id'],
                    'brand_id' => $row['brand_id'],
                    'video_provider' => $row['video_provider'],
                    'video_link' => $row['video_link'],
                    'tags' => $row['tags'],
                    'unit_price' => $row['unit_price'],
                    'unit' => $row['unit'],
                    'meta_title' => $row['meta_title'],
                    'meta_description' => $row['meta_description'],
                    'colors' => json_encode([]),
                    'choice_options' => json_encode([]),
                    'variations' => json_encode([]),
                    'slug' => preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', strtolower($row['slug']))) . '-' . Str::random(5),
                    'thumbnail_img' => $this->downloadThumbnail($row['thumbnail_img']),
                    'photos' => $this->downloadGalleryImages($row['photos']),
                ]);
                ProductStock::create([
                    'product_id' => $productId->id,
                    'qty' => $row['current_stock'],
                    'price' => $row['unit_price'],
                    'sku' => $row['sku'],
                    'variant' => '',
                ]);
            }

            if ($this->notify) {
                flash(translate('Products imported successfully'))->success();
            }
        }
    }

    public function model(array $row): ?Model
    {
        ++$this->rows;

        return null;
    }

    public function getRowCount(): int
    {
        return $this->rows;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'current_stock' => ['required', 'integer', 'min:0'],
            'unit' => ['required', 'string', 'max:50'],
            'slug' => ['required', 'string', 'max:200'],
            'sku' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string'],
            'video_provider' => ['nullable', 'string', 'in:youtube,vimeo,dailymotion'],
            'video_link' => ['nullable', 'url:http,https'],
            'meta_title' => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string'],
            'thumbnail_img' => ['nullable', 'url:http,https'],
            'photos' => ['nullable', 'string', function ($attribute, $value, $onFailure) {
                foreach (explode(',', $value) as $url) {
                    if (! filter_var(trim($url), FILTER_VALIDATE_URL) || ! in_array(strtolower((string) parse_url(trim($url), PHP_URL_SCHEME)), ['http', 'https'], true)) {
                        $onFailure(__('validation.url', ['attribute' => $attribute]));
                        return;
                    }
                }
            }],
        ];
    }

    public function downloadThumbnail($url)
    {
        if (blank($url)) {
            return null;
        }
        try {
            $upload = new Upload;
            $upload->external_link = $url;
            $upload->type = 'image';
            $upload->save();

            return $upload->id;
        } catch (\Exception $e) {
        }
        return null;
    }

    public function downloadGalleryImages($urls)
    {
        if (blank($urls)) {
            return null;
        }
        $data = [];
        foreach (explode(',', str_replace(' ', '', $urls)) as $url) {
            $data[] = $this->downloadThumbnail($url);
        }
        return implode(',', $data);
    }
}

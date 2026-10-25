<?php

namespace App\Http\Resources\V2;

use App\Support\StorefrontUrl;

use App\Models\Attribute;
use App\Models\Review;
use Illuminate\Http\Resources\Json\ResourceCollection;


class ClassifiedProductDetailCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function ($data) {

                $photo_paths = get_images_path($data->photos);

                $photos = [];


                if (!empty($photo_paths)) {
                    for ($i = 0; $i < count($photo_paths); $i++) {
                        if ($photo_paths[$i] != "" ) {
                            $item = [];
                            $item['variant'] = "";
                            $item['path'] = $photo_paths[$i];
                            $photos[]= $item;
                        }

                    }

                }

                $brand = [
                    'id'=> 0,
                    'name'=> "",
                    'logo'=> "",
                ];

                if($data->brand != null) {
                    $brand = [
                        'id'=> $data->brand->id,
                        'name'=> $data->brand->getTranslation('name'),
                        'logo'=> uploaded_asset($data->brand->logo),
                    ];
                }


                return [
                    'id' => (int)$data->id,
                    'name' => $data->getTranslation('name'),
                    'added_by' => $data->user->name,
                    'phone' => $data->user->phone??"",
                    'condition'=>$data->conditon,
                    'photos' => $photos,
                    'thumbnail_image' => uploaded_asset($data->thumbnail_img),
                    'tags' => explode(',', $data->tags),
                    'location' =>  $data->location,
                    'unit_price' => single_price($data->unit_price),
                    'unit' => $data->unit??"",
                    'description' => $data->getTranslation('description'),
                    'video_link' => $data->video_link != null ?  $data->video_link : "",
                    'brand' => $brand,
                    'category' => $data->category->name,
                    'link' => StorefrontUrl::forRow('product', $data->slug, (int) $data->id),
                ];
            }),
        ];
    }

    public function with($request)
    {
        return [
            'success' => true,
            'status' => 200,
        ];
    }

    protected function convertToChoiceOptions($data)
    {
        $result = [];
       if($data) {
        foreach ($data as $key => $choice) {
            $item['name'] = $choice->attribute_id;
            $item['title'] = Attribute::find($choice->attribute_id)->getTranslation('name');
            $item['options'] = $choice->values;
            array_push($result, $item);
        }
       }
        return $result;
    }

    protected function convertPhotos($data)
    {
        $result = [];
        foreach ($data as $key => $item) {
            array_push($result, uploaded_asset($item));
        }
        return $result;
    }
}

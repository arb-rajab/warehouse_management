<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\BannerCollection;

class BannerController extends Controller
{

    public function index()
    {
        return new BannerCollection(get_setting('home_banner1_images') != null ? json_decode(get_setting('home_banner1_images'), true) : []);
    }
}

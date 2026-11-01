<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\ColorCollection;
use App\Models\Color;

class ColorController extends Controller
{
    public function index()
    {
        return new ColorCollection(Color::all());
    }
}

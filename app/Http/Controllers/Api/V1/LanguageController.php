<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\LanguageCollection;
use App\Models\Language;
use Cache;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    public function getList(Request $request)
    {
        return new LanguageCollection(Language::where('status', 1)->get());
    }
}

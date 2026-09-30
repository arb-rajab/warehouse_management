<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\LanguageCollection;
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

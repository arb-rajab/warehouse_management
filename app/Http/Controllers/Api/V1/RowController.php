<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RowResource;
use App\Models\Row;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RowController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return RowResource::collection(
            Row::query()->select(['id', 'letter', 'cells_count', 'flats_count'])->paginate(20)
        );
    }
}

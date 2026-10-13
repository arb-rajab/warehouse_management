<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\RowResource;
use App\Models\Row;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseMapController extends Controller
{
    /**
     * The 2D / 3D warehouse map page. It carries no data of its own: the page
     * fetches {@see self::rows()} so that it can revalidate it (ETag) whenever
     * the tab regains focus, without reloading the Inertia page.
     */
    public function index(): Response
    {
        return Inertia::render('Admin/WarehouseMap/Index');
    }

    /**
     * The whole warehouse grid, in exactly the shape of the mobile app's
     * `GET /api/v1/rows/full`, for the session-authenticated panel (the
     * mobile endpoint only accepts Sanctum tokens). The route adds an ETag.
     */
    public function rows(): AnonymousResourceCollection
    {
        return RowResource::collection(Row::query()->withFullGrid()->get());
    }
}

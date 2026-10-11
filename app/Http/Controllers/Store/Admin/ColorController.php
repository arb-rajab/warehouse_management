<?php

namespace App\Http\Controllers\Store\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\Admin\FilterColorsRequest;
use App\Http\Requests\Store\Admin\StoreColorRequest;
use App\Http\Requests\Store\Admin\UpdateColorRequest;
use App\Http\Resources\Store\ColorResource;
use App\Models\Color;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ColorController extends Controller implements HasMiddleware
{
    /** @return array<int, Middleware> */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_colors', only: ['index']),
            new Middleware('permission:edit_color', only: ['store', 'edit', 'update']),
            new Middleware('permission:delete_color', only: ['destroy']),
        ];
    }

    public function index(FilterColorsRequest $request): Response
    {
        $perPage = $this->resolvePerPage($request, 20);
        $search = $request->validated('search') ?? '';

        return Inertia::render('Store/Admin/Product/Colors/Index', [
            'colors' => $this->paginated(ColorResource::collection(
                Color::query()->select(['id', 'name', 'code'])
                    ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                    ->orderByDesc('id')->paginate($perPage)->withQueryString()
            )),
            'filters' => ['search' => $search, 'per_page' => $perPage],
        ]);
    }

    public function store(StoreColorRequest $request): RedirectResponse
    {
        $color = new Color;
        $color->name = Str::replace(' ', '', $request->string('name')->toString());
        $color->code = $request->validated('code');
        $color->save();

        return redirect()->route('colors');
    }

    public function edit(Color $color): Response
    {
        return Inertia::render('Store/Admin/Product/Colors/Edit', ['color' => new ColorResource($color)]);
    }

    public function update(UpdateColorRequest $request, Color $color): RedirectResponse
    {
        $color->name = Str::replace(' ', '', $request->string('name')->toString());
        $color->code = $request->validated('code');
        $color->save();

        return redirect()->route('colors');
    }

    public function destroy(Request $request, Color $color): RedirectResponse
    {
        $color->delete();

        return $this->redirectPreservingQuery('colors', $request);
    }
}
